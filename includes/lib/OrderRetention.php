<?php
namespace lib;

final class OrderRetention
{
    public static function protectedWhere($db,$alias='O')
    {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$alias)) throw new \InvalidArgumentException('Invalid alias');
        $parts=["$alias.tid=4","($alias.status=1 AND $alias.notify<>0)"];
        foreach (['subscription_purchase','subscription_event','bepusdt_order','merchant_channel_order','collection_receipt','collection_reservation','collection_notify'] as $table) {
            if (MerchantOperations::table($db,$table)) $parts[]="EXISTS (SELECT 1 FROM pre_$table R WHERE R.trade_no=$alias.trade_no)";
        }
        if (MerchantOperations::table($db,'collection_account')) $parts[]="EXISTS (SELECT 1 FROM pre_collection_account R WHERE R.id=$alias.subchannel AND R.uid=$alias.uid)";
        return '('.implode(' OR ',$parts).')';
    }

    public static function protectedOrder($db,$trade)
    {
        return (bool)$db->getColumn('SELECT 1 FROM pre_order O WHERE O.trade_no=:trade AND '.self::protectedWhere($db),[':trade'=>$trade]);
    }

    public static function deleteBefore($db,$before,$unpaidOnly=false)
    {
        return $db->exec('DELETE O FROM pre_order O WHERE O.addtime<:before '.($unpaidOnly?'AND O.status=0 ':'').'AND NOT '.self::protectedWhere($db),[':before'=>$before]);
    }

    public static function deleteOne($db,$trade)
    {
        return $db->exec('DELETE O FROM pre_order O WHERE O.trade_no=:trade AND NOT '.self::protectedWhere($db),[':trade'=>$trade]);
    }
}
