<?php
namespace lib;

/** Merchant-owned Alipay accounts. Public reads never include credential material. */
final class CollectionAccount
{
    public static $settling = false;
    public static $effects = [];
    private $db;
    public function __construct($db) { $this->db = $db; }

    public static function keyPath()
    {
        return getenv('EPAY_COLLECTION_KEY_FILE') ?: dirname(ROOT).'/epay-collection.key';
    }

    private static function key()
    {
        $key = @file_get_contents(self::keyPath());
        if ($key === false || strlen($key) !== 32) throw new \RuntimeException('收款账号加密服务尚未配置');
        return $key;
    }

    public static function encrypt(array $data, $uid)
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt(json_encode($data, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'collection:'.$uid);
        if ($cipher === false) throw new \RuntimeException('加密失败');
        return base64_encode($iv.$tag.$cipher);
    }

    public static function decrypt($value, $uid)
    {
        $bytes = base64_decode($value, true);
        if ($bytes === false || strlen($bytes) < 29) throw new \RuntimeException('密钥数据损坏');
        $plain = openssl_decrypt(substr($bytes,28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($bytes,0,12), substr($bytes,12,16), 'collection:'.$uid);
        if ($plain === false) throw new \RuntimeException('无法读取密钥，请联系管理员');
        return json_decode($plain,true,512,JSON_THROW_ON_ERROR);
    }

    public function transaction(callable $fn)
    {
        $pdo = $this->db->db;
        $mode = $pdo->getAttribute(\PDO::ATTR_ERRMODE);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();
        try { $result = $fn(); $pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        finally { $pdo->setAttribute(\PDO::ATTR_ERRMODE,$mode); }
    }

    public function audit($uid,$id,$action,$detail='')
    {
        if (!$this->db->insert('collection_audit',['uid'=>$uid,'account_id'=>$id,'action'=>$action,'detail'=>$detail,'created_at'=>'NOW()'])) throw new \RuntimeException('操作记录保存失败');
    }

    public function owned($uid,$id,$lock=false)
    {
        $row = $this->db->getRow('SELECT C.*,S.name,S.status,S.channel FROM pre_collection_account C JOIN pre_subchannel S ON S.id=C.id AND S.uid=C.uid WHERE C.id=:id AND C.uid=:uid AND C.deleted_at IS NULL'.($lock?' FOR UPDATE':''), [':id'=>$id,':uid'=>$uid]);
        if (!$row) throw new \InvalidArgumentException('收款账号不存在');
        return $row;
    }

    public static function health(array $row,$now)
    {
        if (empty($row['verified_at'])) return '未校验';
        if (!empty($row['last_error'])) return '查询异常';
        if (empty($row['heartbeat_at']) || $now-strtotime($row['heartbeat_at'])>120) return '监测离线';
        if (empty($row['last_ok']) || $now-strtotime($row['last_ok'])>120) return '等待查询';
        return '在线';
    }

    public function listing($uid)
    {
        $rows=$this->db->getAll('SELECT C.id,C.uid,C.alipay_uid,C.appid,C.qr_url,C.verified_at,C.checked_at,C.last_ok,C.heartbeat_at,C.last_error,S.name,S.status,R.account_id default_id FROM pre_collection_account C JOIN pre_subchannel S ON C.id=S.id AND C.uid=S.uid LEFT JOIN pre_collection_route R ON R.uid=C.uid WHERE C.uid=:uid AND C.deleted_at IS NULL ORDER BY C.id DESC',[':uid'=>$uid]);
        if (!is_array($rows)) throw new \RuntimeException('收款账号服务未初始化');
        foreach ($rows as &$r) {
            $r['health']=self::health($r,time());
            $r['is_default']=(int)$r['default_id']===(int)$r['id']; unset($r['default_id']);
            $r['today']=$this->db->getRow('SELECT COUNT(*) count,COALESCE(SUM(realmoney),0) amount FROM pre_order WHERE uid=:uid AND subchannel=:id AND status=1 AND date=CURDATE()',[':uid'=>$uid,':id'=>$r['id']]);
        }
        return $rows;
    }

    public function save($uid,array $input,$parent)
    {
        require_once ROOT.'plugins/alipaycode/inc/NativeQr.php';
        $id=(int)($input['id']??0); $old=$id?$this->owned($uid,$id):null;
        $name=trim((string)($input['name']??''));
        if ($name==='' || mb_strlen($name)>30) throw new \InvalidArgumentException('账号名称请填写 1–30 个字');
        $alipay=trim((string)($input['alipay_uid']??'')); $appid=trim((string)($input['appid']??''));
        if (!preg_match('/\A2088[0-9]{12}\z/D',$alipay) || !preg_match('/\A20[0-9]{14}\z/D',$appid)) throw new \InvalidArgumentException('请检查支付宝 UID 和 APPID');
        $qr=\AlipayCodeNativeQr::codeUrl($input['qr_url']??'');
        if ($old && ($old['alipay_uid']!==$alipay || $old['qr_url']!==$qr)) throw new \InvalidArgumentException('收款主体和二维码不能直接替换，请另建账号以保留原流水归属');
        $secrets=$old?self::decrypt($old['secret'],$uid):[];
        foreach (['appsecret','appkey'] as $field) if (!empty(trim((string)($input[$field]??'')))) $secrets[$field]=trim($input[$field]);
        foreach (['appsecret','appkey'] as $field) {
            $value=preg_replace('/-----[^-]+-----|\s+/','',$secrets[$field]??'');
            $kind=$field==='appsecret'?'PRIVATE KEY':'PUBLIC KEY';
            $pem="-----BEGIN $kind-----\n".chunk_split($value,64,"\n")."-----END $kind-----";
            $key=$field==='appsecret'?openssl_pkey_get_private($pem):openssl_pkey_get_public($pem);
            if (!$key && $field==='appsecret') $key=openssl_pkey_get_private("-----BEGIN RSA PRIVATE KEY-----\n".chunk_split($value,64,"\n")."-----END RSA PRIVATE KEY-----");
            $details=$key?openssl_pkey_get_details($key):false;
            if (!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048) throw new \InvalidArgumentException('请填写有效的 RSA2 应用私钥及支付宝公钥（至少 2048 位）');
            $secrets[$field]=$value;
        }
        $template=$this->db->getRow("SELECT * FROM pre_channel WHERE id=:id AND plugin='alipaycode' AND mode=1 AND status=0",[':id'=>$parent]);
        if (!$template || (json_decode($template['config'],true)['collection_managed']??null)!==1) throw new \RuntimeException('自助收款模板尚未配置');
        return $this->transaction(function() use($uid,$id,$old,$name,$alipay,$appid,$qr,$secrets,$parent) {
            if (MerchantChannel::selfService($this->db,$uid)) { MerchantChannel::policy($this->db,$uid,true); MerchantChannel::quota($this->db,$uid,false,$id?0:1); }
            if ($old) {
                $current=$this->owned($uid,$id,true);
                if ((int)$current['status']!==0) throw new \InvalidArgumentException('请先停用账号再修改配置');
                $pending=$this->db->getColumn('SELECT COUNT(*) FROM pre_order WHERE subchannel=:id AND status=0 AND addtime>=DATE_SUB(NOW(),INTERVAL 13 MINUTE)',[':id'=>$id]);
                if ($pending) throw new \InvalidArgumentException('还有待确认订单，请在付款和查询窗口结束后修改');
                $this->db->update('subchannel',['name'=>$name],['id'=>$id,'uid'=>$uid]);
                $this->db->update('collection_account',['appid'=>$appid,'secret'=>self::encrypt($secrets,$uid),'verified_at'=>null,'last_error'=>null,'last_ok'=>null,'heartbeat_at'=>null],['id'=>$id,'uid'=>$uid]);
            } else {
                if (!MerchantChannel::selfService($this->db,$uid) && $this->db->getColumn('SELECT COUNT(*) FROM pre_collection_account WHERE uid=:uid AND deleted_at IS NULL',[':uid'=>$uid])>=10) throw new \InvalidArgumentException('每个商户最多添加 10 个收款账号');
                $id=$this->db->insert('subchannel',['channel'=>$parent,'uid'=>$uid,'name'=>$name,'status'=>0,'info'=>'{}','addtime'=>'NOW()','usetime'=>'NOW()']);
                $this->db->insert('collection_account',['id'=>$id,'uid'=>$uid,'alipay_uid'=>$alipay,'appid'=>$appid,'qr_url'=>$qr,'secret'=>self::encrypt($secrets,$uid),'created_at'=>'NOW()']);
            }
            $this->audit($uid,$id,$old?'更新配置':'新增账号'); return (int)$id;
        });
    }

    public function config(array $row)
    {
        return array_merge(self::decrypt($row['secret'],$row['uid']),['appid'=>$row['appid'],'appmchid'=>$row['alipay_uid'],'appurl'=>$row['qr_url'],'appswitch'=>'2','apptoken'=>'','collection_managed'=>1,'collection_uid'=>$row['uid']]);
    }

    public function client(array $row)
    {
        $c=$this->config($row);
        return new \Alipay\AlipayBillService(['app_id'=>$c['appid'],'app_private_key'=>$c['appsecret'],'alipay_public_key'=>$c['appkey'],'sign_type'=>'RSA2','charset'=>'UTF-8','gateway_url'=>'https://openapi.alipay.com/gateway.do']);
    }

    public function verify($uid,$id)
    {
        if (MerchantChannel::selfService($this->db,$uid)) MerchantChannel::policy($this->db,$uid);
        $row=$this->owned($uid,$id);
        if ($row['checked_at'] && time()-strtotime($row['checked_at'])<15) throw new \InvalidArgumentException('请稍等 15 秒后再校验');
        $this->db->update('collection_account',['checked_at'=>'NOW()'],['id'=>$id]);
        try { $this->client($row)->accountlogQuery(date('Y-m-d H:i:s',time()-60),date('Y-m-d H:i:s'),1,1000,$row['alipay_uid']); }
        catch (\Throwable $e) { $this->db->update('collection_account',['verified_at'=>null,'last_error'=>'接口校验失败，请检查应用上线状态、密钥及账单权限'],['id'=>$id]); throw new \RuntimeException('接口校验失败，请检查应用上线状态、密钥及账单权限'); }
        $this->db->update('collection_account',['verified_at'=>'NOW()','last_error'=>null],['id'=>$id]);
        $this->audit($uid,$id,'接口校验通过');
    }

    public function action($uid,$id,$action)
    {
        return $this->transaction(function() use($uid,$id,$action) {
            if (MerchantChannel::selfService($this->db,$uid)) MerchantSubscription::current($this->db,$uid,true);
            $r=$this->owned($uid,$id,true);
            if (in_array($action,['enable','default'],true) && MerchantChannel::selfService($this->db,$uid)) MerchantChannel::quota($this->db,$uid,true,$action==='enable' && !$r['status']?1:0);
            if ($action==='enable') {
                if (empty($r['verified_at'])) throw new \InvalidArgumentException('请先校验接口');
                $this->db->update('subchannel',['status'=>1],['id'=>$id,'uid'=>$uid]);
            } elseif ($action==='disable') {
                // Keep the explicit route: fail closed instead of silently changing the recipient.
                $this->db->update('subchannel',['status'=>0],['id'=>$id,'uid'=>$uid]);
            } elseif ($action==='default') {
                if ((int)$r['status']!==1 || self::health($r,time())!=='在线') throw new \InvalidArgumentException('只有已启用且监测在线的账号可设为默认');
                if (MerchantChannel::installed()) {
                    $type=$this->db->findColumn('channel','type',['id'=>$r['channel']]);
                    $this->db->delete('merchant_channel_route',['uid'=>$uid,'type'=>$type]);
                }
                $this->db->exec('INSERT INTO pre_collection_route (uid,account_id) VALUES (:uid,:id) ON DUPLICATE KEY UPDATE account_id=VALUES(account_id)',[':uid'=>$uid,':id'=>$id]);
            } elseif ($action==='unroute') {
                $this->db->delete('collection_route',['uid'=>$uid,'account_id'=>$id]);
            } elseif ($action==='archive') {
                if ($r['status'] || $this->db->getColumn('SELECT COUNT(*) FROM pre_collection_route WHERE uid=:uid AND account_id=:id',[':uid'=>$uid,':id'=>$id])) throw new \InvalidArgumentException('请先停用并取消默认收款');
                if ($this->db->getColumn('SELECT COUNT(*) FROM pre_order WHERE subchannel=:id AND status=0 AND addtime>=DATE_SUB(NOW(),INTERVAL 13 MINUTE)',[':id'=>$id])) throw new \InvalidArgumentException('还有待确认订单，请稍后归档');
                $this->db->update('collection_account',['deleted_at'=>'NOW()'],['id'=>$id,'uid'=>$uid]);
            } else throw new \InvalidArgumentException('未知操作');
            $this->audit($uid,$id,$action);
        });
    }

    public static function route($db,$uid,$typeid,$typename,$money,$rate)
    {
        if ($typename!=='alipay' || !empty($GLOBALS['platform_payment'])) return null;
        $routes=$db->getAll('SELECT account_id FROM pre_collection_route WHERE uid=:uid',[':uid'=>$uid]);
        if (!is_array($routes)) return false;
        if (!$routes) return null;
        $route=$routes[0];
        try {
            $direct=MerchantChannel::selfService($db,$uid);
            if ($direct) {
                $policy=MerchantChannel::quota($db,$uid,true);
                if (isset($policy['channels'][$typeid]) && (int)$policy['channels'][$typeid]['channel']===0) return false;
            }
            $r=(new self($db))->owned($uid,$route['account_id']);
            if ($r['status']!=1 || self::health($r,time())!=='在线') return false;
            $c=$db->getRow("SELECT * FROM pre_channel WHERE id=:id AND plugin='alipaycode' AND mode=1",[':id'=>$r['channel']]);
            if (!$c || ($c['daystatus']??0)>0 || (int)$c['type']!==(int)$typeid) return false;
            if ((json_decode($c['config'],true)['collection_managed']??null)!==1) return false;
            if (isset($c['timestart'],$c['timestop']) && ($c['timestart']>0 || $c['timestop']>0)) {
                $hour=(int)date('H');
                if ($c['timestart']<$c['timestop'] ? ($hour<$c['timestart'] || $hour>$c['timestop']) : ($hour<$c['timestart'] && $hour>$c['timestop'])) return false;
            }
            if ($money>0 && (($c['paymin']>0 && $money<$c['paymin']) || ($c['paymax']>0 && $money>$c['paymax']))) return false;
            return ['typeid'=>$typeid,'typename'=>$typename,'plugin'=>'alipaycode','channel'=>$c['id'],'subchannel'=>$r['id'],'subscription_direct'=>$direct?1:0,'rate'=>$direct?100:($rate?:$c['rate']),'apptype'=>$c['apptype'],'mode'=>1,'paymin'=>$c['paymin'],'paymax'=>$c['paymax']];
        } catch (\Throwable $e) { return false; }
    }

    public function reserve(array $order,array $channel)
    {
        require_once ROOT.'plugins/alipaycode/inc/NativeQr.php';
        if (MerchantChannel::selfService($this->db,$order['uid'])) {
            MerchantChannel::quota($this->db,$order['uid'],true);
            if (BepusdtClient::decimal($order['money'],2)!==BepusdtClient::decimal($order['realmoney'],2) || BepusdtClient::decimal($order['money'],2)!==BepusdtClient::decimal($order['getmoney'],2)) throw new \InvalidArgumentException('包月收款金额不一致');
        }
        $r=$this->owned($order['uid'],$channel['subid']);
        if ((int)$r['status']!==1 || self::health($r,time())!=='在线') throw new \RuntimeException('收款账号暂不可用，请联系商户');
        if (!in_array((int)$order['tid'],[0,3],true)) throw new \RuntimeException('自助收款账号仅用于商户订单和收款测试');
        if ((int)($order['profits']??0)>0) throw new \RuntimeException('原生收款码不支持分账订单');
        return $this->transaction(function() use($order,$r) {
            $amount=\AlipayCodeNativeQr::cents($order['realmoney']);
            if (!$amount) throw new \RuntimeException('订单金额无效');
            $this->db->exec('DELETE FROM pre_collection_reservation WHERE account_id=:id AND expires_at<=NOW()',[':id'=>$r['id']]);
            $this->db->exec('INSERT IGNORE INTO pre_collection_reservation (account_id,amount_cents,trade_no,expires_at) VALUES (:id,:amount,:trade,:expires)', [':id'=>$r['id'],':amount'=>$amount,':trade'=>$order['trade_no'],':expires'=>date('Y-m-d H:i:s',strtotime($order['addtime'])+780)]);
            $held=$this->db->getColumn('SELECT trade_no FROM pre_collection_reservation WHERE account_id=:id AND amount_cents=:amount FOR UPDATE',[':id'=>$r['id'],':amount'=>$amount]);
            if ($held!==$order['trade_no']) throw new \RuntimeException('当前有相同金额的订单待确认，请稍后重新下单；请勿扫描旧二维码付款');
        });
    }
}
