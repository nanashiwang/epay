<?php
namespace lib;

final class SubscriptionDashboard
{
    public static function report($db,$from,$to,$page=1)
    {
        foreach ([$from,$to] as $date) {
            if (!is_string($date) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$date) || date('Y-m-d',strtotime($date))!==$date) throw new \InvalidArgumentException('日期格式不正确');
        }
        if ($from>$to || strtotime($to)-strtotime($from)>365*86400) throw new \InvalidArgumentException('请选择不超过 366 天的时间段');
        $page=max(1,min(100000,(int)$page));$offset=($page-1)*20;
        return DbTransaction::run($db,function() use($db,$from,$to,$page,$offset) {
            foreach(['subscription_purchase','subscription_event','subscription_resolution'] as $table) if (!MerchantOperations::table($db,$table)) throw new \InvalidArgumentException('请先执行订阅及套餐运营迁移');
            $paid='COALESCE(O.endtime,E.created_at)';
            $base=' FROM pre_subscription_purchase P JOIN pre_order O ON O.trade_no=P.trade_no JOIN pre_subscription_event E ON E.trade_no=P.trade_no AND E.uid=P.uid WHERE O.tid=4 AND O.status=1';
            $where=" AND $paid>=:from AND $paid<DATE_ADD(:to,INTERVAL 1 DAY)";
            $args=[':from'=>$from,':to'=>$to];
            $summary=$db->getRow("SELECT COUNT(*) purchases,COALESCE(SUM(O.money),0) amount,COALESCE(SUM(IF(O.api_trade_no='balance',O.money,0)),0) balance_amount,COALESCE(SUM(IF(COALESCE(O.api_trade_no,'')<>'balance',O.money,0)),0) external_amount".$base.$where,$args);
            $renewals=(int)$db->getColumn("SELECT COUNT(*)".$base.$where." AND EXISTS (SELECT 1 FROM pre_subscription_purchase Q JOIN pre_order X ON X.trade_no=Q.trade_no JOIN pre_subscription_event Y ON Y.trade_no=Q.trade_no AND Y.uid=Q.uid WHERE Q.uid=P.uid AND X.tid=4 AND X.status=1 AND (COALESCE(X.endtime,Y.created_at)<$paid OR (COALESCE(X.endtime,Y.created_at)=$paid AND Q.trade_no<P.trade_no)))",$args);
            $daily=$db->getAll("SELECT DATE($paid) day,COUNT(*) purchases,SUM(O.money) amount".$base.$where." GROUP BY DATE($paid) ORDER BY day",$args);
            $plans=$db->getAll('SELECT P.gid,COUNT(*) purchases,SUM(O.money) amount'.$base.$where.' GROUP BY P.gid ORDER BY amount DESC',$args);
            $groups=$db->getAll('SELECT gid,name,config FROM pre_group');$ids=[];$names=[];
            foreach($groups as $g){$names[$g['gid']]=$g['name'];if(MerchantSubscription::policy($g)['enabled'])$ids[]=(int)$g['gid'];}
            foreach($plans as &$plan)$plan['name']=$names[$plan['gid']]??'已删除套餐';unset($plan);
            $scope='gid IN ('.implode(',',$ids?:[-1]).') AND status=1 AND endtime IS NOT NULL';
            $members=$db->getRow('SELECT COUNT(IF(endtime>NOW(),1,NULL)) active,COUNT(IF(endtime>NOW() AND endtime<=DATE_ADD(NOW(),INTERVAL 7 DAY),1,NULL)) expiring,COUNT(IF(endtime<=NOW(),1,NULL)) expired FROM pre_user WHERE '.$scope);
            $expiring=$db->getAll('SELECT uid,gid,endtime FROM pre_user WHERE '.$scope.' AND endtime>NOW() AND endtime<=DATE_ADD(NOW(),INTERVAL 7 DAY) ORDER BY endtime,uid LIMIT '.$offset.',21');
            $more=count($expiring)>20;$expiring=array_slice($expiring,0,20);
            $review=(int)$db->getColumn("SELECT COUNT(*) FROM pre_subscription_event E JOIN pre_order O ON O.trade_no=E.trade_no WHERE E.state='review' AND O.status=1");
            return compact('from','to','summary','renewals','daily','plans','members','expiring','review','page','more');
        });
    }
}
