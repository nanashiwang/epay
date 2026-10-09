<?php
namespace lib;

class PaymentReview
{
    public static function ready($db)
    {
        foreach(['payment_review','collection_account','collection_receipt','collection_notify','bepusdt_order','merchant_channel_order'] as $t) if(!MerchantOperations::table($db,$t)) throw new \InvalidArgumentException('请先完成收款、通道及支付核对迁移');
    }
    private static function managed()
    {
        return '(B.account_id>0 OR M.account_id>0 OR EXISTS (SELECT 1 FROM pre_collection_account C WHERE C.id=O.subchannel AND C.uid=O.uid))';
    }
    private static function base()
    {
        return ' FROM pre_order O LEFT JOIN pre_bepusdt_order B ON B.trade_no=O.trade_no AND B.uid=O.uid LEFT JOIN pre_merchant_channel_order M ON M.trade_no=O.trade_no AND M.uid=O.uid WHERE O.tid IN (0,3) AND '.self::managed();
    }
    public static function listing($db,$uid=null,$page=1,$trade='')
    {
        self::ready($db);$page=max(1,min(10000,(int)$page));$offset=($page-1)*20;$args=[];
        $where=$uid===null?'':' AND O.uid=:uid';if($uid!==null)$args[':uid']=$uid;
        if (!is_string($trade) || ($trade!=='' && !preg_match('/\A[0-9]{10,32}\z/D',$trade))) throw new \InvalidArgumentException('请输入完整平台订单号');
        if($trade!==''){$where.=' AND O.trade_no=:trade';$args[':trade']=$trade;}
        else $where.=" AND ((O.status=1 AND O.tid=0 AND O.notify<>0) OR (O.status IN (0,4) AND (B.state='unknown' OR (B.state='creating' AND B.created_at<DATE_SUB(NOW(),INTERVAL 2 MINUTE)) OR O.addtime<DATE_SUB(NOW(),INTERVAL 30 MINUTE))))";
        $rows=$db->getAll('SELECT O.trade_no,O.uid,O.status,O.tid,O.money,O.notify,O.addtime,B.state gateway_state,B.provider_id'.self::base().$where.' ORDER BY O.addtime DESC,O.trade_no DESC LIMIT '.$offset.',21',$args);
        $more=count($rows)>20;$rows=array_slice($rows,0,20);
        foreach($rows as &$r) {
            $r['label']=(int)$r['status']===1?((int)$r['notify']===0?'已付款，通知已完成':'已付款，业务通知未完成'):($r['gateway_state']==='unknown'?'网关创建结果未知':($r['gateway_state']==='creating'?'网关创建中断待核对':'未收到到账确认，需核对'));
            $r['notification']=$db->getRow('SELECT http_code,response_summary,created_at FROM pre_collection_notify WHERE trade_no=:trade AND uid=:uid ORDER BY id DESC LIMIT 1',[':trade'=>$r['trade_no'],':uid'=>$r['uid']]);
            if ((int)$r['status']===1 && (int)$r['tid']===3) $r['label']='测试已付款，无业务通知';
            if (!in_array((int)$r['status'],[0,1,4],true)) $r['label']='订单已处理，请结合订单记录核对';
            $r['history']=$db->getAll('SELECT actor,action,reason,reference,state,result,created_at FROM pre_payment_review WHERE trade_no=:trade AND uid=:uid ORDER BY id DESC LIMIT 5',[':trade'=>$r['trade_no'],':uid'=>$r['uid']]);
        }unset($r);
        $unmatched=(int)$db->getColumn("SELECT COUNT(*) FROM pre_collection_receipt WHERE state IN ('unmatched','ambiguous','late')".($uid===null?'':' AND uid=:uid'),$uid===null?[]:[':uid'=>$uid]);
        return compact('rows','more','page','unmatched','trade');
    }
    protected function inspect($db,array $row)
    {
        $snapshot=$db->find('bepusdt_order','*',['trade_no'=>$row['trade_no'],'uid'=>$row['uid']]);
        if (!$snapshot || !$snapshot['provider_id']) throw new \InvalidArgumentException('该订单不支持在线查单，请到原网关核对并登记凭证');
        $config=GatewaySecrets::decrypt($snapshot['config_snapshot'],$snapshot['uid']);
        return (new BepusdtClient($config['appurl'],$config['appkey']))->inspect($snapshot);
    }
    protected function retry($db,$trade) {return CollectionNotify::retry($db,$trade,true);}
    public function act($db,$uid,$actor,array $input)
    {
        self::ready($db);
        foreach(['trade_no','action','reason','reference','request_key'] as $key) if(!is_string($input[$key]??null)) throw new \InvalidArgumentException('提交字段格式不正确');
        $trade_no=$input['trade_no'];$action=$input['action'];$reason=$input['reason'];$reference=$input['reference'];$request_key=$input['request_key'];
        if(!preg_match('/\A[0-9]{10,32}\z/D',$trade_no) || !preg_match('/\A[a-f0-9]{32,64}\z/D',$request_key) || !in_array($action,['note','inspect','retry'],true) || mb_strlen(trim($reason))<5 || mb_strlen($reason)>500 || mb_strlen($reference)>200) throw new \InvalidArgumentException('请填写有效订单号和至少 5 字的核对依据');
        $lock='payment-review:'.$trade_no;
        if((int)$db->getColumn('SELECT GET_LOCK(:lock,0)',[':lock'=>$lock])!==1) throw new \InvalidArgumentException('该订单正在处理，请稍后查看记录');
        try {
            $args=[':trade'=>$trade_no];$scope=$uid===null?'':' AND O.uid=:uid';if($uid!==null)$args[':uid']=$uid;
            $row=$db->getRow('SELECT O.*'.self::base().' AND O.trade_no=:trade'.$scope,$args);
            if(!$row) throw new \InvalidArgumentException('收款订单不存在或无权访问');
            $key=hash('sha256',$actor.':'.$request_key);
            $prior=$db->find('payment_review','state,result',['request_key'=>$key]);
            if($prior) return '该请求已记录：'.($prior['result']?:'处理中，请核对后再操作');
            $last=$db->getColumn('SELECT MAX(created_at) FROM pre_payment_review WHERE trade_no=:trade',[':trade'=>$trade_no]);
            if($last && time()-strtotime($last)<15) throw new \InvalidArgumentException('请稍等 15 秒后再次处理');
            if($action==='retry' && ((int)$row['status']!==1 || (int)$row['tid']!==0 || (int)$row['notify']===0)) throw new \InvalidArgumentException('仅可重试已付款且通知未完成的业务订单');
            $id=DbTransaction::run($db,fn()=>$db->insert('payment_review',['uid'=>$row['uid'],'trade_no'=>$trade_no,'actor'=>$actor,'action'=>$action,'reason'=>trim($reason),'reference'=>trim($reference),'request_key'=>$key,'state'=>'pending','created_at'=>'NOW()']));
            try {
                $message=$action==='note'?'核对依据已记录，支付状态未变更':($action==='inspect'?$this->inspect($db,$row):($this->retry($db,$trade_no)?'业务通知已成功':'通知未成功或正在重试，请查看通知记录'));
                $state='completed';
            } catch(\Throwable $e) {$message='操作未确认，请到原通道核对；未补记付款，可查看通知记录';$state='uncertain';}
            DbTransaction::run($db,fn()=>$db->update('payment_review',['state'=>$state,'result'=>$message,'completed_at'=>'NOW()'],['id'=>$id]));
            return $message;
        } finally {$db->getColumn('SELECT RELEASE_LOCK(:lock)',[':lock'=>$lock]);}
    }
}
