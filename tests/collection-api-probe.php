<?php
// CLI-only API boundary probe. It cannot be used as a web authentication bypass.
if (PHP_SAPI!=='cli') exit;
date_default_timezone_set('Asia/Shanghai');
$database=getenv('EPAY_TEST_DB')?:'';
if (!preg_match('/\Aepay_collection_[a-z0-9_]*test\z/D',$database)) exit(2);
define('IN_CRONLITE',true); define('ROOT',dirname(__DIR__).'/'); define('SYSTEM_ROOT',ROOT.'includes/');
require SYSTEM_ROOT.'autoloader.php'; Autoloader::register();
$dbconfig=['host'=>getenv('EPAY_TEST_HOST')?:'localhost','port'=>getenv('EPAY_TEST_PORT')?:3306,'dbname'=>$database,'user'=>getenv('EPAY_TEST_USER')?:'root','pwd'=>getenv('EPAY_TEST_PASSWORD')?:'','dbqz'=>'ct'];
$DB=new \lib\PdoHelper($dbconfig);
$request=json_decode(stream_get_contents(STDIN),true);
$islogin2=empty($request['anonymous'])?1:0;
$userrow=$DB->find('user','*',['uid'=>$request['uid']??1000]);
$conf=['collection_parent'=>1];
$siteurl='https://example.invalid/'; $clientip='127.0.0.1'; $_SERVER['HTTP_HOST']='example.invalid';
$_GET=$request['get']??[]; $_POST=$request['post']??[]; $_SESSION=['collection_csrf'=>'synthetic-csrf'];
$_SERVER['REQUEST_METHOD']=$request['method']??'POST';
if (isset($request['reserve_trade'])) {
    require ROOT.'plugins/alipaycode/inc/NativeQr.php';
    date_default_timezone_set('Asia/Shanghai');
    $o=$DB->find('order','*',['trade_no'=>$request['reserve_trade']]);
    try { (new \lib\CollectionAccount($DB))->reserve($o,\lib\Channel::getSub($o['subchannel'])); echo '1'; }
    catch (Throwable $e) { echo '0'; }
    exit;
}
chdir(ROOT.'user'); require ROOT.'user/collection_api.php';
