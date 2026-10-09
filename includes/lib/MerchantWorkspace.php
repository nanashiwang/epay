<?php
namespace lib;

final class MerchantWorkspace
{
    public static function overview($db,$uid)
    {
        [$u,$g,$policy]=MerchantSubscription::current($db,$uid);
        $today=date('Y-m-d');
        $stats=$db->getRow('SELECT COUNT(*) paid_orders,COALESCE(SUM(money),0) amount FROM pre_order WHERE uid=:uid AND tid=0 AND status=1 AND date=:today',[':uid'=>$uid,':today'=>$today]);
        $pending=0;
        if (MerchantOperations::table($db,'collection_notify')) $pending=(int)$db->getColumn('SELECT COUNT(*) FROM pre_order O WHERE O.uid=:uid AND O.tid=0 AND O.status=1 AND O.notify<>0 AND '.CollectionNotify::scope(),[':uid'=>$uid]);
        $types=Channel::getTypes($uid,$u['gid']);
        $used=0;
        foreach ($policy['self_service']?['merchant_channel_account','bepusdt_account','collection_account']:['bepusdt_account'] as $table) if (MerchantOperations::table($db,$table)) {
            $used+=(int)$db->getColumn('SELECT COUNT(*) FROM pre_'.$table.' WHERE uid=:uid AND deleted_at IS NULL',[':uid'=>$uid]);
        }
        return ['user'=>$u,'group'=>$g,'policy'=>$policy,'stats'=>$stats,'pending'=>$pending,'types'=>$types,'used'=>$used,'notice'=>$policy['enabled']?MerchantOperations::reminder($u):null];
    }
}
