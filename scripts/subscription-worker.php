<?php
if (PHP_SAPI!=='cli') exit;
$nosession=true;
$_SERVER['HTTP_HOST']=$_SERVER['HTTP_HOST']??(getenv('HTTP_HOST')?:'localhost');
require dirname(__DIR__).'/includes/common.php';
if (in_array('--ready',$argv,true)) {
    if (\lib\MerchantOperations::table($DB,'subscription_reminder')) echo "READY\n";
    exit;
}
$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
if ((int)$DB->getColumn("SELECT GET_LOCK('epay:subscription-worker',0)")!==1) exit("套餐提醒服务已运行\n");
do {
    try {
        $conf=array_merge($conf,$CACHE->pre_fetch());
        $enabled=(int)($conf['msgconfig_group']??0)===1;
        if ($enabled) \lib\MerchantOperations::sendReminders($DB,static fn($u,$g,$n)=>\lib\MsgNotice::subscriptionReminder($u,$g,$n));
        \lib\WorkerRuntime::beat($DB,'subscription',$enabled?'ok':'disabled');
    } catch (Throwable $e) {
        \lib\WorkerRuntime::beat($DB,'subscription','error');
        fwrite(STDERR,"套餐提醒任务失败，下次重试\n");
    }
    if (in_array('--once',$argv,true)) break;
    sleep(300);
} while(true);
