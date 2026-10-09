<?php
if (PHP_SAPI!=='cli') exit;
$nosession=true;
$_SERVER['HTTP_HOST']=$_SERVER['HTTP_HOST']??(getenv('HTTP_HOST')?:'localhost');
require dirname(__DIR__).'/includes/common.php';
if (in_array('--ready',$argv,true)) {
    if (\lib\MerchantOperations::table($DB,'collection_account') && \lib\MerchantOperations::table($DB,'collection_notify') && is_readable(\lib\CollectionAccount::keyPath()) && filesize(\lib\CollectionAccount::keyPath())===32) echo "READY\n";
    exit;
}
$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$worker=new \lib\CollectionWorker($DB);
if ((int)$DB->getColumn("SELECT GET_LOCK('epay:collection-worker',0)")!==1) exit("监测服务已运行\n");
$lastReminder=0;
do {
    $rows=$DB->getAll('SELECT C.*,S.channel,S.name,S.status FROM pre_collection_account C JOIN pre_subchannel S ON S.id=C.id AND S.uid=C.uid WHERE C.verified_at IS NOT NULL AND (S.status=1 OR EXISTS (SELECT 1 FROM pre_order O WHERE O.subchannel=C.id AND O.status=0 AND O.addtime>=DATE_SUB(NOW(),INTERVAL 8 MINUTE))) ORDER BY C.checked_at ASC');
    if (!is_array($rows)) { fwrite(STDERR,"无法读取监测账号\n"); exit(1); }
    foreach ($rows as $row) {
        $pending=$DB->getColumn('SELECT COUNT(*) FROM pre_order WHERE subchannel=:id AND status=0 AND addtime>=DATE_SUB(NOW(),INTERVAL 8 MINUTE)',[':id'=>$row['id']]);
        if ($pending || !$row['checked_at'] || time()-strtotime($row['checked_at'])>=30) $worker->poll($row);
    }
    $notifications=$DB->getAll('SELECT O.trade_no FROM pre_order O WHERE '.\lib\CollectionNotify::scope().' AND O.status=1 AND O.tid=0 AND O.notify>0 AND O.notifytime<=NOW() ORDER BY O.notifytime LIMIT 10');
    foreach($notifications?:[] as $notification) {
        try { \lib\CollectionNotify::retry($DB,$notification['trade_no']); }
        catch (Throwable $e) { fwrite(STDERR,"业务通知重试失败\n"); }
    }
    if (time()-$lastReminder>=300) {
        $lastReminder=time();
        try {
            $conf['msgconfig_group']=$DB->getColumn("SELECT v FROM pre_config WHERE k='msgconfig_group'");
            if ((int)$conf['msgconfig_group']===1) \lib\MerchantOperations::sendReminders($DB,static fn($u,$g,$n)=>\lib\MsgNotice::subscriptionReminder($u,$g,$n));
        } catch (Throwable $e) { fwrite(STDERR,"套餐提醒任务失败，下次重试\n"); }
    }
    if (in_array('--once',$argv,true)) break;
    sleep(3);
} while(true);
