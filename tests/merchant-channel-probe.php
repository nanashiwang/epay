<?php
require __DIR__.'/bepusdt-bootstrap.php';
require_once SYSTEM_ROOT.'vendor/autoload.php';
$conf['merchant_channels']=1;
$request=json_decode(stream_get_contents(STDIN),true);
if (!function_exists('is_https')) { function is_https() { return true; } }
define('PAYPAGE_ROOT',SYSTEM_ROOT.'pages/');
define('TEMPLATE_ROOT',ROOT.'template/');
if (isset($request['admin'])) {
    $islogin=1; $_SERVER['HTTP_REFERER']='https://epay.example/admin/channel.php';
    $_GET=$request['get']??[]; $_POST=$request['post']??[];
    chdir(ROOT.'admin'); require ROOT.'admin/ajax_pay.php'; exit;
}
if (isset($request['submit2'])) {
    $conf['payfee_lessthan']=10; $conf['payfee_mincost']=5;
    $conf['pay_payaddstart']=0.5; $conf['pay_payaddmin']=0.01; $conf['pay_payaddmax']=0.09;
    $_GET=['trade_no'=>$request['submit2'],'typeid'=>1]; chdir(ROOT); require ROOT.'submit2.php'; exit;
}
if (!empty($request['setup'])) { $argv=['merchant-channel-setup.php','--apply']; require ROOT.'scripts/merchant-channel-setup.php'; exit; }
if (isset($request['dispatch'])) {
    // Keep the SDK's real RSA2 verifier, replacing only its live paid-order query.
    // No real provider account, order or network request is needed by these tests.
    class SyntheticAlipayTradeService extends \Alipay\AlipayService {}
    class_alias(SyntheticAlipayTradeService::class, 'Alipay\\AlipayTradeService');
    $_GET=$request['get']??[]; $_POST=$request['post']??[]; $method=$request['pay_method']??'';
    try { $result=\lib\Plugin::loadForPay($request['dispatch']); echo json_encode($result); }
    catch (Throwable $e) { echo json_encode(['error'=>$e->getMessage()]); } exit;
}
if (isset($request['pay_api'])) {
    $conf['payfee_lessthan']=10; $conf['payfee_mincost']=5;
    $conf['pay_payaddstart']=0.5; $conf['pay_payaddmin']=0.01; $conf['pay_payaddmax']=0.09;
    $_POST=['pid'=>'1000','type'=>$request['type']??'alipay','out_trade_no'=>$request['pay_api'],'notify_url'=>'https://example.invalid/notify','return_url'=>'https://example.invalid/return','name'=>'API 测试','money'=>'1.00','clientip'=>'8.8.8.8','method'=>$request['pay_method']??'jump','device'=>'pc'];
    $_POST['sign']=\lib\Payment::makeSign($_POST,'synthetic-key'); $_POST['sign_type']='MD5';
    \lib\api\Pay::create(); exit;
}
$islogin2=empty($request['anonymous'])?1:0;$userrow=$DB->find('user','*',['uid'=>$request['uid']??1000]);$uid=$userrow['uid'];
$_GET=$request['get']??[];$_POST=$request['post']??[];$_SESSION=['channels_csrf'=>'synthetic-csrf','csrf_token'=>'synthetic-csrf'];
$_SERVER['REQUEST_METHOD']=$request['method']??'POST';
chdir(ROOT.'user');require ROOT.'user/channels_api.php';
