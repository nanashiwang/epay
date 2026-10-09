<?php
require __DIR__.'/bepusdt-bootstrap.php';
require_once SYSTEM_ROOT.'vendor/autoload.php';
$request=json_decode(stream_get_contents(STDIN),true);
$conf=array_merge($CACHE->pre_fetch(),$conf,['merchant_channels'=>1,'admin_user'=>'synthetic-admin'],$request['conf']??[]);
$userrow=$DB->find('user','*',['uid'=>$request['uid']??1000]);$uid=$userrow['uid'];
$islogin=empty($request['admin'])?0:1;$islogin2=empty($request['anonymous'])?1:0;
$_GET=$request['get']??[];$_POST=$request['post']??[];
$_SESSION=['subscription_admin_csrf'=>'synthetic-csrf','csrf_token'=>'synthetic-csrf'];
$_SERVER['REQUEST_METHOD']=$request['method']??'POST';
$_SERVER['HTTP_REFERER']='https://epay.example/user/';
$cdnpublic='/assets/vendor/';$date=date('Y-m-d H:i:s');
define('VERSION','test');define('TEMPLATE_ROOT',ROOT.'template/');
if (!function_exists('is_https')) { function is_https(){return true;} }
$page=$request['page']??'admin-api';
$files=['admin-api'=>'admin/subscriptions_api.php','plan'=>'user/groupbuy.php','home'=>'user/index.php','stats'=>'user/ajax2.php','orders-api'=>'admin/ajax_order.php','onboarding'=>'user/onboarding.php'];
if (!isset($files[$page])) exit;
chdir(dirname(ROOT.$files[$page]));require ROOT.$files[$page];
