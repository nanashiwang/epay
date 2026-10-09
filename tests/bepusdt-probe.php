<?php
require __DIR__.'/bepusdt-bootstrap.php';
$request=json_decode(stream_get_contents(STDIN),true);
if (isset($request['setup'])) { $argv=['bepusdt-setup.php','--apply']; require ROOT.'scripts/bepusdt-setup.php'; exit; }
if (isset($request['settle'])) {
    try { \lib\MerchantSubscription::settle($DB,$request['settle'],'synthetic-paid');echo '1'; }
    catch (Throwable $e) {fwrite(STDERR,$e->getMessage());exit(1);}exit;
}
if (isset($request['notify'])) {
    try {(new \lib\BepusdtGateway($DB))->notify($request['notify'],$request['data']);echo '1';}
    catch (Throwable $e) {fwrite(STDERR,$e->getMessage());exit(1);}exit;
}
if (isset($request['pay_api'])) {
    $conf['payfee_lessthan']=10; $conf['payfee_mincost']=5;
    $conf['pay_payaddstart']=0.5; $conf['pay_payaddmin']=0.01; $conf['pay_payaddmax']=0.09;
    $_POST=['pid'=>'1000','type'=>'usdt.trc20','out_trade_no'=>$request['pay_api'],'notify_url'=>'https://example.invalid/notify','return_url'=>'https://example.invalid/return','name'=>'API 测试','money'=>'1.00','clientip'=>'8.8.8.8','method'=>'jump','device'=>'pc'];
    $_POST['sign']=\lib\Payment::makeSign($_POST,'synthetic-key'); $_POST['sign_type']='MD5';
    \lib\api\Pay::create(); exit;
}
$islogin2=empty($request['anonymous'])?1:0;$userrow=$DB->find('user','*',['uid'=>$request['uid']??1000]);$uid=$userrow['uid'];
$_GET=$request['get']??[];$_POST=$request['post']??[];$_SESSION=['bepusdt_csrf'=>'synthetic-csrf','csrf_token'=>'synthetic-csrf'];
$_SERVER['REQUEST_METHOD']=$request['method']??'POST';
chdir(ROOT.'user');require ROOT.'user/bepusdt_api.php';
