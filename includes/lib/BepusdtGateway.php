<?php
namespace lib;

class BepusdtGateway
{
    private $db;
    public function __construct($db) { $this->db=$db; }
    protected function client(array $config) { return new BepusdtClient($config['appurl'],$config['appkey']); }

    public function create(array $order,array $channel)
    {
        global $conf,$siteurl;
        $trade=$order['trade_no']; $lock='bepusdt-create:'.$trade;
        if ((int)$this->db->getColumn('SELECT GET_LOCK(:lock,0)',[':lock'=>$lock])!==1) throw new \RuntimeException('支付订单正在创建，请稍后刷新');
        $pdo=$this->db->db; $errorMode=$pdo->getAttribute(\PDO::ATTR_ERRMODE); $pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        try {
            $order=$this->db->getRow('SELECT O.*,T.name typename,T.status type_status FROM pre_order O JOIN pre_type T ON T.id=O.type WHERE O.trade_no=:trade',[':trade'=>$trade]);
            if (!$order) throw new \InvalidArgumentException('支付订单不存在');
            if (!in_array((int)$order['status'],[0,4],true)) throw new \InvalidArgumentException('订单已处理，请勿再次付款');
            $existing=$this->db->find('bepusdt_order','*',['trade_no'=>$trade]);
            if ($existing) {
                if ((int)$existing['uid']!==(int)$order['uid']) throw new \RuntimeException('订单归属不一致');
                if ($existing['state']==='waiting' && strtotime($existing['expires_at'])>time()) return $existing['payment_url'];
                throw new \RuntimeException($existing['state']==='paid'?'订单已付款':($existing['state']==='unknown' || $existing['state']==='creating'?'创建结果待核对，请联系商户；不要重复付款':'付款窗口已结束，请返回业务站重新下单'));
            }
            if ((int)$order['type_status']!==1) throw new \InvalidArgumentException('支付方式已停用');
            if (!empty($order['status'])) throw new \InvalidArgumentException('订单不可重复支付');
            $account=0;
            if (!empty($channel['bepusdt_managed'])) {
                $policy=MerchantSubscription::requireActive($this->db,$order['uid']);
                if (!in_array((int)$order['tid'],[0,3],true) || (int)($channel['owner_uid']??0)!==(int)$order['uid']) throw new \InvalidArgumentException('商户网关不能用于平台订阅或充值');
                $account=(int)$channel['account_id'];
                $r=(new BepusdtAccount($this->db))->owned($order['uid'],$account);
                if ((int)$order['subchannel']!==$account || (int)$order['channel']!==(int)$r['channel']) throw new \InvalidArgumentException('订单收款账号不一致');
                $channel=array_merge($channel,(new BepusdtAccount($this->db))->config($r));
                $test=(int)$order['tid']===3 && (json_decode($order['param']??'{}',true)['bepusdt_test']??null)===1;
                if (isset($policy['channels'][$order['type']]) && (int)$policy['channels'][$order['type']]['channel']===0) throw new \InvalidArgumentException('套餐已关闭该支付方式');
                if (!$test) {
                    $route=BepusdtAccount::route($this->db,$order['uid'],$order['type'],$order['typename'],$order['money']);
                    if (!$route || (int)$route['subchannel']!==$account) throw new \InvalidArgumentException('默认收款账号已变更，请从业务站重新下单');
                }
                if (!$r['verified_at'] || (!$test && ((int)$r['status']!==1 || !$r['tested_at']))) throw new \InvalidArgumentException('收款账号尚未验收或已停用');
                $enabled=$this->db->getColumn('SELECT COUNT(*) FROM pre_bepusdt_account A JOIN pre_subchannel S ON S.id=A.id WHERE A.uid=:uid AND A.deleted_at IS NULL AND S.status=1',[':uid'=>$order['uid']]);
                if (!$test && $enabled>$policy['limit']) throw new \InvalidArgumentException('启用账号数超过当前套餐，请先停用超额账号');
                if ((int)$channel['mode']!==1 || BepusdtClient::decimal($order['money'],2)!==BepusdtClient::decimal($order['realmoney'],2) || BepusdtClient::decimal($order['money'],2)!==BepusdtClient::decimal($order['getmoney'],2)) throw new \RuntimeException('包月直收订单金额不一致');
            }
            if (!empty($order['cert_info']) || !empty($order['profits'])) throw new \InvalidArgumentException('BEpusdt 暂不支持付款人认证或分账订单');
            if ($account && (!isset(BepusdtNetwork::TYPES[$order['typename']]) || $channel['trade_type']!==$order['typename'])) throw new \InvalidArgumentException('订单币种与网络和收款账号不一致');
            $config=['appurl'=>GatewayHttp::endpoint($channel['appurl']),'appkey'=>$channel['appkey'],'address'=>trim($channel['address']??''),'timeout'=>(int)(($channel['timeout']??0)?:1200),'mode'=>(int)$channel['mode']];
            if ($account) BepusdtNetwork::address($order['typename'],$config['address']);
            if ($config['timeout']<120 || $config['timeout']>3600) throw new \InvalidArgumentException('网关订单超时须为 120–3600 秒');
            $params=['order_id'=>$trade,'amount'=>$order['realmoney'],'fiat'=>'CNY','trade_type'=>$order['typename'],'name'=>$order['name'],'address'=>$config['address'],'timeout'=>$config['timeout'],'notify_url'=>$conf['localurl'].'pay/notify/'.$trade.'/','redirect_url'=>$siteurl.'pay/return/'.$trade.'/'];
            if (!empty($channel['rate']) && !$account) $params['rate']=(string)$channel['rate'];
            $this->db->insert('bepusdt_order',['trade_no'=>$trade,'uid'=>$order['uid'],'account_id'=>$account,'channel_id'=>$order['channel'],'config_snapshot'=>GatewaySecrets::encrypt($config,$order['uid']),'money'=>$order['realmoney'],'network'=>$order['typename'],'state'=>'creating','created_at'=>'NOW()']);
            try {
                $d=$this->client($config)->create($params);
                $this->db->update('bepusdt_order',['provider_id'=>$d['trade_id'],'address'=>$d['token'],'coin_amount'=>$d['actual_amount'],'payment_url'=>$d['payment_url'],'expires_at'=>date('Y-m-d H:i:s',time()+$d['expiration_time']),'state'=>'waiting'],['trade_no'=>$trade]);
                return $d['payment_url'];
            } catch (\Throwable $e) {
                $this->db->update('bepusdt_order',['state'=>'unknown','last_error'=>'网关创建结果未确认，请在网关核对后处理；不会自动重建'],['trade_no'=>$trade]);
                throw $e;
            }
        } finally { $pdo->setAttribute(\PDO::ATTR_ERRMODE,$errorMode); $this->db->getColumn('SELECT RELEASE_LOCK(:lock)',[':lock'=>$lock]); }
    }

    public function notify($trade,array $data)
    {
        $verified=false;
        try { $paid=DbTransaction::run($this->db,function() use($trade,$data,&$verified) {
            $r=$this->db->getRow('SELECT * FROM pre_bepusdt_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$trade]);
            if (!$r) throw new \InvalidArgumentException('unknown order');
            $config=GatewaySecrets::decrypt($r['config_snapshot'],$r['uid']);
            if (!BepusdtClient::verify($data,$config['appkey'])) throw new \InvalidArgumentException('invalid signature');
            $verified=true;
            $o=$this->db->getRow('SELECT * FROM pre_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$trade]);
            if (!$o || (int)$o['uid']!==(int)$r['uid'] || ($data['order_id']??null)!==$trade || ($data['trade_id']??null)!==$r['provider_id'] || !$r['provider_id']) throw new \InvalidArgumentException('order mismatch');
            if (BepusdtClient::decimal($data['amount']??null,2)!==BepusdtClient::decimal($r['money'],2) || BepusdtClient::decimal($o['realmoney'],2)!==BepusdtClient::decimal($r['money'],2) || ($data['token']??null)!==$r['address'] || BepusdtClient::decimal($data['actual_amount']??null)!==BepusdtClient::decimal($r['coin_amount'])) throw new \InvalidArgumentException('payment mismatch');
            // v1.24.2 notifications omit trade_type; if supplied, it must match the snapshot.
            if (isset($data['trade_type']) && $data['trade_type']!==$r['network']) throw new \InvalidArgumentException('payment network mismatch');
            $status=$data['status']??null;
            if (!in_array($status,[1,2,3],true)) throw new \InvalidArgumentException('invalid state');
            if ($r['state']==='paid') {
                if ($status===2 && ($data['block_transaction_id']??null)!==$r['txid']) throw new \InvalidArgumentException('conflicting chain evidence');
                return false;
            }
            if (!in_array($r['state'],['waiting','expired'],true)) throw new \InvalidArgumentException('order requires reconciliation');
            if ($status!==2) {
                if ($status===3) $this->db->update('bepusdt_order',['state'=>'expired'],['trade_no'=>$trade]);
                return false;
            }
            if (!is_string($data['block_transaction_id']??null) || !preg_match('/\A[a-zA-Z0-9_-]{16,128}\z/D',$data['block_transaction_id'])) throw new \InvalidArgumentException('missing chain evidence');
            // Separate networks and receiving addresses; ambiguous reuse at one address is rejected.
            $receipt=hash('sha256',$r['network'].'|'.$r['address'].'|'.$data['block_transaction_id']);
            if (!in_array((int)$o['status'],[0,4],true)) throw new \InvalidArgumentException('local order state mismatch');
            if ($r['account_id']) {
                if (!in_array((int)$o['tid'],[0,3],true) || (int)$o['subchannel']!==(int)$r['account_id'] || (int)$config['mode']!==1) throw new \InvalidArgumentException('merchant gateway cannot credit platform');
                if (BepusdtClient::decimal($o['money'],2)!==BepusdtClient::decimal($o['getmoney'],2)) throw new \InvalidArgumentException('subscription order fee mismatch');
                // Direct receipt: no principal credit, service fee, referral credit or custody entry.
                $this->db->update('order',['status'=>1,'api_trade_no'=>$r['provider_id'],'bill_trade_no'=>$data['block_transaction_id'],'endtime'=>'NOW()','date'=>'CURDATE()','profitmoney'=>'0.00','notify'=>(int)$o['tid']===0?1:0,'notifytime'=>(int)$o['tid']===0?'NOW()':null],['trade_no'=>$trade]);
                $account=$this->db->find('bepusdt_account','*',['id'=>$r['account_id'],'uid'=>$r['uid']]);
                if ($account) {
                    $update=['last_callback'=>'NOW()','last_error'=>null];
                    $current=(new BepusdtAccount($this->db))->config($account);
                    if ((int)$o['tid']===3 && $r['network']===$current['trade_type'] && $config['appurl']===$current['appurl'] && $config['address']===$current['address'] && hash_equals($config['appkey'],$current['appkey'])) $update['tested_at']='NOW()';
                    $this->db->update('bepusdt_account',$update,['id'=>$r['account_id']]);
                }
            } else {
                // Administrator-owned gateway retains existing billing, with external effects deferred.
                $oldChannel=$GLOBALS['channel']??null;
                $GLOBALS['channel']=array_merge($GLOBALS['channel']??[],$config);
                $type=$this->db->find('type','*',['id'=>$o['type']]);
                $o['typename']=$type['name']; $o['typeshowname']=$type['showname'];
                CollectionAccount::$settling=true;
                try { processNotify($o,$r['provider_id'],null,$data['block_transaction_id']); }
                finally { CollectionAccount::$settling=false; $GLOBALS['channel']=$oldChannel; }
            }
            $this->db->update('bepusdt_order',['state'=>'paid','txid'=>$data['block_transaction_id'],'receipt_key'=>$receipt,'paid_at'=>'NOW()','last_error'=>null],['trade_no'=>$trade]);
            return $r['account_id'] && (int)$o['tid']===0;
        }); } catch (\Throwable $e) {
            CollectionAccount::$effects=[];
            if ($verified) {
                try { $this->db->exec("UPDATE pre_bepusdt_order SET last_error='已收到签名有效的通知，但校验或入账未完成，请核对网关订单' WHERE trade_no=:trade AND state<>'paid'",[':trade'=>$trade]); } catch (\Throwable $ignored) {}
            }
            throw $e;
        }
        $effects=CollectionAccount::$effects; CollectionAccount::$effects=[];
        foreach ($effects as $effect) { try { $effect(); } catch (\Throwable $e) { error_log('BEpusdt post-payment effect failed'); } }
        if ($paid) { try { CollectionNotify::retry($this->db,$trade); } catch (\Throwable $e) { error_log('BEpusdt notification queued'); } }
        return true;
    }
}
