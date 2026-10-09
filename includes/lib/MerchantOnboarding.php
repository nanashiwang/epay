<?php
namespace lib;

final class MerchantOnboarding
{
    public static function firstVisit($db,array $u,array $conf)
    {
        if (empty($conf['merchant_channels']) && empty($conf['bepusdt_parent'])) return false;
        if ((int)$u['gid']!==0 || !empty($u['account']) || !empty($u['username'])) return false;
        return !(bool)$db->getColumn('SELECT 1 FROM pre_order WHERE uid=:uid AND tid=0 LIMIT 1',[':uid'=>$u['uid']]);
    }

    public static function progress($db,$uid)
    {
        [$u,$g,$policy]=MerchantSubscription::current($db,$uid);
        $added=0;$tested=0;$nativeVerified=0;
        foreach (['merchant_channel_account'=>'tested_at','bepusdt_account'=>'tested_at','collection_account'=>'verified_at'] as $table=>$field) {
            if (!MerchantOperations::table($db,$table)) continue;
            $r=$db->getRow("SELECT COUNT(*) added,COUNT($field) tested FROM pre_$table WHERE uid=:uid AND deleted_at IS NULL",[':uid'=>$uid]);
            $added+=(int)$r['added'];
            if($table==='collection_account') $nativeVerified=(int)$r['tested'];
            else $tested+=(int)$r['tested'];
        }
        $types=$policy['active']?Channel::getTypes($uid,$u['gid']):[];
        $integrated=MerchantOperations::table($db,'collection_notify') && (bool)$db->getColumn('SELECT 1 FROM pre_collection_notify N JOIN pre_order O ON O.trade_no=N.trade_no AND O.uid=N.uid WHERE N.uid=:uid AND N.success=1 AND O.status=1 AND O.tid=0 LIMIT 1',[':uid'=>$uid]);
        return compact('u','g','policy','added','tested','nativeVerified','types','integrated');
    }
}
