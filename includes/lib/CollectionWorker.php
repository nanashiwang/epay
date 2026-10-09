<?php
namespace lib;

final class CollectionWorker
{
    private $db;
    private $accounts;
    public function __construct($db) { $this->db=$db; $this->accounts=new CollectionAccount($db); require_once ROOT.'plugins/alipaycode/inc/NativeQr.php'; }

    public function poll(array $row)
    {
        $now=time();
        $this->db->update('collection_account',['heartbeat_at'=>'NOW()','checked_at'=>'NOW()'],['id'=>$row['id']]);
        try {
            $aop=$this->accounts->client($row);
            $since=max($now-86400,$row['last_ok']?strtotime($row['last_ok'])-480:$now-480);
            $page=1;
            do {
                $result=$aop->accountlogQuery(date('Y-m-d H:i:s',$since),date('Y-m-d H:i:s',$now),$page,1000,$row['alipay_uid']);
                if (!isset($result['detail_list']) && isset($result['total_size']) && (string)$result['total_size']==='0') $result['detail_list']=[];
                if (!isset($result['detail_list']) || !is_array($result['detail_list'])) throw new \RuntimeException('invalid bill response');
                foreach ($result['detail_list'] as $bill) $this->receipt($row,$bill,$now);
                $more=$page*1000<(int)($result['total_size']??0);
                if ($more && ($page>=20 || empty($result['detail_list']))) throw new \RuntimeException('bill pagination incomplete');
                $page++;
            } while($more);
            $this->db->update('collection_account',['last_ok'=>date('Y-m-d H:i:s',$now),'last_error'=>null],['id'=>$row['id']]);
        } catch (\Throwable $e) {
            // Do not persist SDK messages, signed payloads, credentials or counterparties.
            $this->db->update('collection_account',['last_error'=>'查询或到账处理失败，请重新校验接口并检查监测服务'],['id'=>$row['id']]);
        }
    }

    public function receipt(array $account,array $bill,$now)
    {
        $amount=\AlipayCodeNativeQr::cents($bill['trans_amount']??null);
        $paid=\AlipayCodeNativeQr::timestamp($bill['trans_dt']??null);
        if (($bill['direction']??'')!=='收入' || !$amount || !$paid || $paid>$now || !preg_match('/\A[0-9]{10,64}\z/D',(string)($bill['alipay_order_no']??''))) return false;
        $receipt=$bill['alipay_order_no'];
        if ($this->db->exec('INSERT IGNORE INTO pre_collection_receipt (account_id,uid,receipt_no,amount,paid_at,memo,created_at) VALUES (:account,:uid,:receipt,:amount,:paid,:memo,NOW())',[':account'=>$account['id'],':uid'=>$account['uid'],':receipt'=>$receipt,':amount'=>number_format($amount/100,2,'.',''),':paid'=>$bill['trans_dt'],':memo'=>mb_substr((string)($bill['trans_memo']??''),0,200)])===false) throw new \RuntimeException('receipt write failed');
        $stored=$this->db->getRow('SELECT * FROM pre_collection_receipt WHERE receipt_no=:receipt',[':receipt'=>$receipt]);
        if (!$stored || (int)$stored['account_id']!==(int)$account['id'] || $stored['state']==='matched') return false;
        $lock='alipaycode:'.substr(hash('sha256',$account['alipay_uid']),0,48);
        if ((int)$this->db->getColumn('SELECT GET_LOCK(:name,0)',[':name'=>$lock])!==1) return false;
        try {
            $legacy=[];
            $channels=$this->db->getAll("SELECT id,config FROM pre_channel WHERE plugin='alipaycode'");
            if (!is_array($channels)) throw new \RuntimeException('channel read failed');
            foreach($channels as $c) {
                $cfg=json_decode($c['config'],true);
                if (($cfg['appmchid']??'')===$account['alipay_uid'] || ($cfg['appid']??'')===$account['appid']) $legacy[]=(int)$c['id'];
            }
            $legacySql=$legacy?' OR (O.subchannel=0 AND O.channel IN ('.implode(',',$legacy).'))':'';
            $orders=$this->db->getAll('SELECT O.* FROM pre_order O WHERE ((O.subchannel=:id AND EXISTS (SELECT 1 FROM pre_collection_reservation R WHERE R.trade_no=O.trade_no AND R.account_id=:account))'.$legacySql.') AND O.addtime>=:since', [':id'=>$account['id'],':account'=>$account['id'],':since'=>date('Y-m-d H:i:s',$now-780)]);
            if (!is_array($orders)) throw new \RuntimeException('order read failed');
            $match=\AlipayCodeNativeQr::matchBill($orders,$bill,$now);
            if (!$match || (int)$match['subchannel']!==(int)$account['id']) {
                $state=$paid<$now-480?'late':'unmatched';
                $candidates=0;
                foreach($orders as $o) if (\AlipayCodeNativeQr::cents($o['realmoney'])===$amount && $paid>=strtotime($o['addtime']) && $paid<strtotime($o['addtime'])+300) $candidates++;
                if ($candidates>1) $state='ambiguous';
                $this->db->update('collection_receipt',['state'=>$state],['id'=>$stored['id']]);
                return false;
            }
            return $this->settle($account,$match,$bill,$stored['id']);
        } finally { $this->db->getColumn('SELECT RELEASE_LOCK(:name)',[':name'=>$lock]); }
    }

    public function settle(array $account,array $match,array $bill,$receiptId)
    {
        global $channel;
        $previous=$channel; $channel=Channel::getSub($account['id']);
        if (!$channel || (int)$channel['mode']!==1) throw new \RuntimeException('direct collection required');
        CollectionAccount::$settling=true; CollectionAccount::$effects=[];
        try {
            $done=$this->accounts->transaction(function() use($account,$match,$bill,$receiptId) {
                $receipt=$this->db->getRow('SELECT * FROM pre_collection_receipt WHERE id=:id FOR UPDATE',[':id'=>$receiptId]);
                $order=$this->db->getRow('SELECT O.*,T.name typename,T.showname typeshowname FROM pre_order O JOIN pre_type T ON O.type=T.id WHERE trade_no=:trade FOR UPDATE',[':trade'=>$match['trade_no']]);
                if (!$receipt || !$order || $receipt['state']==='matched' || (int)$receipt['account_id']!==(int)$account['id'] || (int)$order['uid']!==(int)$account['uid'] || (int)$order['subchannel']!==(int)$account['id'] || (int)$order['status']!==0 || !empty($order['api_trade_no']) || !in_array((int)$order['tid'],[0,3],true) || (int)$order['profits']>0 || \AlipayCodeNativeQr::cents($order['realmoney'])!==\AlipayCodeNativeQr::cents($bill['trans_amount'])) return false;
                if ($this->db->getColumn('SELECT COUNT(*) FROM pre_order WHERE api_trade_no=:receipt',[':receipt'=>$bill['alipay_order_no']])) return false;
                $order['plugin']='alipaycode';
                processNotify($order,$bill['alipay_order_no'],$bill['other_account']??null,null,null,$bill['trans_dt']);
                $saved=$this->db->getRow('SELECT status,api_trade_no FROM pre_order WHERE trade_no=:trade',[':trade'=>$order['trade_no']]);
                if ((int)$saved['status']!==1 || $saved['api_trade_no']!==$bill['alipay_order_no']) throw new \RuntimeException('order not settled');
                $this->db->update('collection_receipt',['state'=>'matched','trade_no'=>$order['trade_no']],['id'=>$receiptId]);
                $this->accounts->audit($account['uid'],$account['id'],'自动确认到账',$order['trade_no']);
                return true;
            });
            CollectionAccount::$settling=false;
            if ($done) foreach(CollectionAccount::$effects as $effect) { try { $effect(); } catch (\Throwable $e) {} }
            return $done;
        } finally { CollectionAccount::$settling=false; CollectionAccount::$effects=[]; $channel=$previous; }
    }
}
