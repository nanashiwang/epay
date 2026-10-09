<?php
namespace lib;

final class MerchantChannel
{
    private $db;
    public function __construct($db) { $this->db=$db; }
    public static function installed() { return !empty($GLOBALS['conf']['merchant_channels']); }
    public static function policy($db,$uid,$lock=false)
    {
        $p=MerchantSubscription::requireActive($db,$uid,$lock);
        if (!self::installed() || !$p['self_service']) throw new \InvalidArgumentException('当前套餐未开通通道自助配置');
        return $p;
    }
    public static function selfService($db,$uid)
    {
        if ((int)$uid<=0) return false;
        return MerchantSubscription::current($db,$uid)[2]['self_service'];
    }
    public static function count($db,$uid,$enabled=false)
    {
        $count=0;
        foreach (['merchant_channel_account','bepusdt_account','collection_account'] as $table) {
            $count+=(int)$db->getColumn('SELECT COUNT(*) FROM pre_'.$table.' A JOIN pre_subchannel S ON S.id=A.id AND S.uid=A.uid WHERE A.uid=:uid AND A.deleted_at IS NULL'.($enabled?' AND S.status=1':''),[':uid'=>$uid]);
        }
        return $count;
    }
    public static function quota($db,$uid,$enabled=false,$additional=0)
    {
        $p=self::policy($db,$uid);
        if (self::count($db,$uid,$enabled)+$additional>$p['limit']) throw new \InvalidArgumentException('已达到套餐账号总数，请停用或归档闲置账号');
        return $p;
    }
    public function owned($uid,$id,$lock=false,$archived=false)
    {
        $r=$this->db->getRow('SELECT A.*,S.name,S.status,S.channel FROM pre_merchant_channel_account A JOIN pre_subchannel S ON S.id=A.id AND S.uid=A.uid WHERE A.id=:id AND A.uid=:uid'.($archived?'':' AND A.deleted_at IS NULL').($lock?' FOR UPDATE':''),[':id'=>$id,':uid'=>$uid]);
        if (!$r) throw new \InvalidArgumentException('支付通道不存在');
        return $r;
    }
    public function audit($uid,$id,$action,$detail='')
    {
        $this->db->insert('merchant_channel_audit',['uid'=>$uid,'account_id'=>$id,'action'=>$action,'detail'=>$detail,'created_at'=>'NOW()']);
    }
    public function config(array $r)
    {
        $config=GatewaySecrets::decrypt($r['secret'],$r['uid'],'merchant-channel');
        return array_merge(['appurl'=>'','appmchid'=>'','appsecret'=>''], $config, [
            'id'=>(int)$r['channel'],'subid'=>(int)$r['id'],'subname'=>$r['name'],'plugin'=>$r['plugin'],'type'=>(int)$r['type'],
            'merchant_managed'=>1,'owner_uid'=>(int)$r['uid'],'revision'=>(int)$r['revision'],'mode'=>1,'rate'=>100,'costrate'=>0,
            'appswitch'=>0,'appwxmp'=>0,'appwxa'=>0,'subappwxmp'=>0,'subappwxa'=>0,
        ]);
    }
    public function template($plugin,$type)
    {
        $p=$this->db->getRow('SELECT C.*,T.status type_status,T.name typename FROM pre_merchant_channel_template M JOIN pre_channel C ON C.id=M.channel AND C.type=M.type AND C.plugin=M.plugin JOIN pre_type T ON T.id=C.type WHERE M.plugin=:plugin AND M.type=:type',[':plugin'=>$plugin,':type'=>$type]);
        if (!$p || (int)$p['status']!==0 || (int)$p['mode']!==1 || (int)$p['type_status']!==1 || !empty($p['daystatus']) || (json_decode($p['config'],true)['merchant_managed']??null)!==1) throw new \InvalidArgumentException('该支付方式暂未开放');
        if (!in_array($p['typename'],MerchantChannelCatalog::get($plugin)['types'],true)) throw new \InvalidArgumentException('支付方式与插件不匹配');
        return $p;
    }
    private static function typeAllowed(array $policy,$type)
    {
        if (isset($policy['channels'][$type]) && (int)$policy['channels'][$type]['channel']===0) throw new \InvalidArgumentException('套餐未开放该支付方式');
    }
    public function save($uid,array $input)
    {
        return DbTransaction::run($this->db,function() use($uid,$input) {
            $p=self::policy($this->db,$uid,true);
            $id=(int)($input['id']??0); $old=$id?$this->owned($uid,$id,true):null;
            $plugin=$old?$old['plugin']:($input['plugin']??''); $type=$old?(int)$old['type']:(int)($input['type']??0);
            if ($old && (int)$old['status']!==0) throw new \InvalidArgumentException('请先停用通道再编辑');
            $template=$this->template($plugin,$type); self::typeAllowed($p,$type);
            $name=$input['name']??'';
            if (!is_string($name) || trim($name)==='' || mb_strlen($name)>30) throw new \InvalidArgumentException('通道名称请填写 1–30 个字');
            $config=MerchantChannelCatalog::validate($plugin,$input['config']??[], $old?GatewaySecrets::decrypt($old['secret'],$uid,'merchant-channel'):[]);
            $secret=GatewaySecrets::encrypt($config,$uid,'merchant-channel');
            if (!$old) {
                self::quota($this->db,$uid,false,1);
                $id=$this->db->insert('subchannel',['uid'=>$uid,'channel'=>$template['id'],'name'=>trim($name),'status'=>0,'info'=>'{}','addtime'=>'NOW()','usetime'=>'NOW()']);
                $this->db->insert('merchant_channel_account',['id'=>$id,'uid'=>$uid,'plugin'=>$plugin,'type'=>$type,'secret'=>$secret,'created_at'=>'NOW()']);
            } else {
                $this->db->update('subchannel',['name'=>trim($name)],['id'=>$id,'uid'=>$uid]);
                $this->db->update('merchant_channel_account',['secret'=>$secret,'revision'=>(int)$old['revision']+1,'tested_at'=>null],['id'=>$id,'uid'=>$uid]);
            }
            $this->audit($uid,$id,$old?'修改通道':'新增通道'); return (int)$id;
        });
    }
    public function listing($uid)
    {
        $rows=$this->db->getAll('SELECT A.*,S.name,S.status,S.channel,T.showname type_name,R.account_id default_id FROM pre_merchant_channel_account A JOIN pre_subchannel S ON S.id=A.id AND S.uid=A.uid JOIN pre_type T ON T.id=A.type LEFT JOIN pre_merchant_channel_route R ON R.uid=A.uid AND R.type=A.type WHERE A.uid=:uid AND A.deleted_at IS NULL ORDER BY A.id DESC',[':uid'=>$uid]);
        foreach ($rows as &$r) {
            $c=GatewaySecrets::decrypt($r['secret'],$uid,'merchant-channel'); $r['config']=[];
            foreach (MerchantChannelCatalog::get($r['plugin'])['fields'] as $key=>$field) if (!$field['secret']) $r['config'][$key]=$c[$key];
            $r['config']['apptype']=$c['apptype']===''?[]:explode(',',$c['apptype']);
            unset($r['secret']);
        }
        return $rows;
    }
    public function action($uid,$id,$action)
    {
        return DbTransaction::run($this->db,function() use($uid,$id,$action) {
            MerchantSubscription::current($this->db,$uid,true);
            $r=$this->owned($uid,$id,true);
            if (in_array($action,['enable','default'],true)) {
                $p=self::policy($this->db,$uid); self::typeAllowed($p,$r['type']); $this->template($r['plugin'],$r['type']);
                self::quota($this->db,$uid,true,$action==='enable' && !$r['status']?1:0);
                if (!$r['tested_at']) throw new \InvalidArgumentException('请先创建测试订单并确认到账回调');
            }
            if ($action==='enable' || $action==='disable') $this->db->update('subchannel',['status'=>$action==='enable'?1:0],['id'=>$id,'uid'=>$uid]);
            elseif ($action==='default') {
                if (!$r['status']) throw new \InvalidArgumentException('请先启用通道');
                $this->db->exec('INSERT INTO pre_merchant_channel_route (uid,type,account_id) VALUES (:uid,:type,:id) ON DUPLICATE KEY UPDATE account_id=VALUES(account_id)',[':uid'=>$uid,':type'=>$r['type'],':id'=>$id]);
                if ($this->db->findColumn('type','name',['id'=>$r['type']])==='alipay') $this->db->delete('collection_route',['uid'=>$uid]);
            } elseif ($action==='unroute') $this->db->delete('merchant_channel_route',['uid'=>$uid,'account_id'=>$id]);
            elseif ($action==='archive') {
                if ($r['status'] || $this->db->find('merchant_channel_route','uid',['uid'=>$uid,'account_id'=>$id])) throw new \InvalidArgumentException('请先停用并取消默认通道');
                $this->db->update('merchant_channel_account',['deleted_at'=>'NOW()'],['id'=>$id,'uid'=>$uid]);
            } else throw new \InvalidArgumentException('未知操作');
            $this->audit($uid,$id,$action);
        });
    }
    public static function route($db,$uid,$type,$name,$money)
    {
        if (!self::installed() || !empty($GLOBALS['platform_payment'])) return null;
        $route=$db->find('merchant_channel_route','*',['uid'=>$uid,'type'=>$type]);
        if (!$route) return null;
        try {
            $p=self::quota($db,$uid,true); self::typeAllowed($p,$type);
            $svc=new self($db); $r=$svc->owned($uid,$route['account_id']); $t=$svc->template($r['plugin'],$type);
            if (!$r['status'] || !$r['tested_at'] || (int)$r['type']!==(int)$type || $t['typename']!==$name || (int)$r['channel']!==(int)$t['id']) return false;
            if ($money>0 && (($t['paymin']>0 && $money<$t['paymin']) || ($t['paymax']>0 && $money>$t['paymax']))) return false;
            if (!empty($t['timestart']) || !empty($t['timestop'])) {
                $h=(int)date('H'); if ($t['timestart']<$t['timestop']?($h<$t['timestart'] || $h>$t['timestop']):($h<$t['timestart'] && $h>$t['timestop'])) return false;
            }
            return ['typeid'=>$type,'typename'=>$name,'plugin'=>$r['plugin'],'channel'=>$r['channel'],'subchannel'=>$r['id'],'rate'=>100,'mode'=>1,'apptype'=>GatewaySecrets::decrypt($r['secret'],$uid,'merchant-channel')['apptype'],'paymin'=>$t['paymin'],'paymax'=>$t['paymax'],'merchant_managed'=>1,'subscription_direct'=>1];
        } catch (\Throwable $e) { return false; }
    }
    public static function isOrder($db,array $order)
    {
        if (empty($order['subchannel'])) return false;
        if (!self::installed()) {
            $config=$db->getColumn('SELECT C.config FROM pre_subchannel S JOIN pre_channel C ON C.id=S.channel WHERE S.id=:id',[':id'=>$order['subchannel']]);
            if (empty(json_decode($config?:'{}',true)['merchant_managed'])) return false;
        }
        return (bool)$db->find('merchant_channel_account','id',['id'=>$order['subchannel']]);
    }
    public static function prepare($db,array $order,array $channel,$action)
    {
        MerchantChannelCatalog::guard($channel,$action);
        return DbTransaction::run($db,function() use($db,$order,$channel,$action) {
            // Legacy Web/API entry points pass only part of the order. Authorization
            // and test markers always come from the stored order, never those copies.
            $stored=$db->find('order','*',['trade_no'=>$order['trade_no']]);
            if (!$stored) throw new \InvalidArgumentException('收款订单不存在');
            foreach (['uid','subchannel','channel','type'] as $field) {
                if ((int)$stored[$field] !== (int)$order[$field]) throw new \InvalidArgumentException('收款订单身份不一致');
            }
            $order=array_merge($order,$stored);
            $snapshot=$db->find('merchant_channel_order','*',['trade_no'=>$order['trade_no']]);
            if (!$snapshot) {
                if (in_array($action,['notify','return','ok'],true)) throw new \InvalidArgumentException('订单尚未创建收款凭据');
                $p=self::policy($db,$order['uid'],true);
                $svc=new self($db); $r=$svc->owned($order['uid'],$order['subchannel'],true);
                $t=$svc->template($r['plugin'],$r['type']); self::typeAllowed($p,$r['type']);
                $o=$db->getRow('SELECT * FROM pre_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$order['trade_no']]);
                if (!$o || !in_array((int)$o['tid'],[0,3],true) || (int)$o['status']!==0 || (int)$o['uid']!==(int)$r['uid'] || (int)$o['channel']!==(int)$t['id'] || (int)$o['subchannel']!==(int)$r['id'] || (int)$o['type']!==(int)$r['type']) throw new \InvalidArgumentException('商户收款订单不匹配');
                $snapshot=$db->getRow('SELECT * FROM pre_merchant_channel_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$order['trade_no']]);
                if (!$snapshot) {
                $test=(int)$o['tid']===3 && (json_decode($o['param']??'',true)['merchant_channel_test']??null)===1;
                if (!$test) {
                    $route=self::route($db,$o['uid'],$o['type'],$t['typename'],$o['money']);
                    if (!$route || (int)$route['subchannel']!==(int)$r['id']) throw new \InvalidArgumentException('默认收款通道已变更或不可用');
                }
                if (BepusdtClient::decimal($o['money'],2)!==BepusdtClient::decimal($o['realmoney'],2) || BepusdtClient::decimal($o['money'],2)!==BepusdtClient::decimal($o['getmoney'],2) || !empty($o['profits']) || !empty($o['combine'])) throw new \InvalidArgumentException('包月直收订单金额不一致');
                $c=$svc->config($r);
                $db->insert('merchant_channel_order',['trade_no'=>$o['trade_no'],'uid'=>$o['uid'],'account_id'=>$r['id'],'channel_id'=>$t['id'],'type'=>$r['type'],'plugin'=>$r['plugin'],'revision'=>$r['revision'],'config_snapshot'=>GatewaySecrets::encrypt($c,$o['uid'],'merchant-order'),'money'=>$o['money'],'created_at'=>'NOW()']);
                $snapshot=$db->getRow('SELECT * FROM pre_merchant_channel_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$o['trade_no']]);
                }
            }
            if ((int)$snapshot['uid']!==(int)$order['uid'] || (int)$snapshot['account_id']!==(int)$order['subchannel'] || (int)$snapshot['channel_id']!==(int)$order['channel'] || (int)$snapshot['type']!==(int)$order['type'] || !in_array((int)$order['tid'],[0,3],true) || BepusdtClient::decimal($snapshot['money'],2)!==BepusdtClient::decimal($order['realmoney'],2)) throw new \InvalidArgumentException('收款订单快照不一致');
            $config=GatewaySecrets::decrypt($snapshot['config_snapshot'],$snapshot['uid'],'merchant-order');
            MerchantChannelCatalog::guard($config,$action);
            // Old callbacks remain verifiable. Expired/disabled accounts cannot initiate more provider calls.
            if (!in_array($action,['notify','return','ok'],true)) {
                self::policy($db,$order['uid']);
                $r=(new self($db))->owned($order['uid'],$order['subchannel']);
                if ((int)$r['revision']!==(int)$snapshot['revision']) throw new \InvalidArgumentException('配置已更新，请重新创建订单');
                if ((json_decode($order['param']??'',true)['merchant_channel_test']??null)!==1) {
                    $route=self::route($db,$order['uid'],$order['type'],$db->findColumn('type','name',['id'=>$order['type']]),$order['money']);
                    if (!$route || (int)$route['subchannel']!==(int)$r['id']) throw new \InvalidArgumentException('当前收款通道不可用');
                }
            }
            return $config;
        });
    }
    public static function settle($db,array $order,$providerId,$buyer=null)
    {
        $paid=DbTransaction::run($db,function() use($db,$order,$providerId,$buyer) {
            MerchantSubscription::current($db,$order['uid'],true);
            $o=$db->getRow('SELECT * FROM pre_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$order['trade_no']]);
            $s=$db->find('merchant_channel_order','*',['trade_no'=>$order['trade_no']]);
            if (!$o || !$s || !in_array((int)$o['tid'],[0,3],true) || $s['uid']!=$o['uid'] || $s['account_id']!=$o['subchannel'] || $s['channel_id']!=$o['channel'] || $s['type']!=$o['type'] || BepusdtClient::decimal($s['money'],2)!==BepusdtClient::decimal($o['realmoney'],2)) throw new \InvalidArgumentException('商户收款凭证不匹配');
            if (!is_string($providerId) || !preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D',$providerId)) throw new \InvalidArgumentException('支付流水号无效');
            if ($s['paid_at']) {
                if ($s['provider_id']!==$providerId) throw new \InvalidArgumentException('支付流水与已确认订单不一致');
                return false;
            }
            if (!in_array((int)$o['status'],[0,4],true)) throw new \InvalidArgumentException('订单状态不允许确认');
            $c=GatewaySecrets::decrypt($s['config_snapshot'],$o['uid'],'merchant-order');
            $identity=$c['plugin']==='epay'?$c['appurl'].':'.$c['appid']:($c['appmchid']?:$c['appid']);
            $key=hash('sha256',$c['plugin'].':'.$identity.':'.$providerId);
            $db->update('merchant_channel_order',['paid_at'=>'NOW()','provider_id'=>$providerId,'receipt_key'=>$key],['trade_no'=>$o['trade_no']]);
            $db->update('order',['status'=>1,'api_trade_no'=>$providerId,'buyer'=>$buyer,'profitmoney'=>'0.00','endtime'=>'NOW()','date'=>'CURDATE()','notify'=>(int)$o['tid']===0?1:0,'notifytime'=>(int)$o['tid']===0?'NOW()':null],['trade_no'=>$o['trade_no']]);
            $db->update('merchant_channel_account',['last_callback'=>'NOW()'],['id'=>$s['account_id'],'uid'=>$o['uid']]);
            if ((int)$o['tid']===3 && (json_decode($o['param']??'',true)['merchant_channel_test']??null)===1) $db->update('merchant_channel_account',['tested_at'=>'NOW()'],['id'=>$s['account_id'],'uid'=>$o['uid'],'revision'=>$s['revision']]);
            (new self($db))->audit($o['uid'],$s['account_id'],'确认到账',$o['trade_no']);
            return (int)$o['tid']===0;
        });
        if ($paid) CollectionNotify::retry($db,$order['trade_no']);
    }
    public static function receipt(array $data,array $channel,array $order)
    {
        // Called only after the provider SDK verifies the signature/decrypts the event.
        $plugin=$channel['plugin'];
        $same=static fn($a,$b)=>is_scalar($a) && (string)$a===(string)$b;
        if ($plugin==='alipay') $ok=$same($data['app_id']??null,$channel['appid']) && $same($data['seller_id']??null,$channel['appmchid']) && $same($data['out_trade_no']??null,$order['trade_no']) && ($data['trade_status']??'')==='TRADE_SUCCESS' && BepusdtClient::decimal($data['total_amount']??null,2)===BepusdtClient::decimal($order['realmoney'],2);
        elseif ($plugin==='wxpayn') $ok=$same($data['appid']??null,$channel['appid']) && $same($data['mchid']??null,$channel['appmchid']) && $same($data['out_trade_no']??null,$order['trade_no']) && ($data['trade_state']??'')==='SUCCESS' && ($data['amount']['currency']??'')==='CNY' && $same($data['amount']['total']??null,(int)round($order['realmoney']*100)) && !isset($data['combine_out_trade_no']);
        elseif (in_array($plugin,['wxpay','qqpay'],true)) $ok=$same($data['mch_id']??null,$plugin==='qqpay'?$channel['appid']:$channel['appmchid']) && ($plugin==='qqpay' || $same($data['appid']??null,$channel['appid'])) && ($data['result_code']??($plugin==='qqpay'?'SUCCESS':''))==='SUCCESS' && ($data['return_code']??($plugin==='qqpay'?'SUCCESS':''))==='SUCCESS' && $same($data['out_trade_no']??null,$order['trade_no']) && $same($data['total_fee']??null,(int)round($order['realmoney']*100)) && ($data['fee_type']??'CNY')==='CNY';
        elseif ($plugin==='epay') $ok=$same($data['pid']??null,$channel['appid']) && $same($data['type']??null,$order['typename']) && $same($data['out_trade_no']??null,$order['trade_no']) && ($data['trade_status']??'')==='TRADE_SUCCESS' && BepusdtClient::decimal($data['money']??null,2)===BepusdtClient::decimal($order['realmoney'],2);
        else $ok=false;
        if (!$ok) throw new \InvalidArgumentException('支付通知的收款主体、币种或金额不匹配');
    }
}
