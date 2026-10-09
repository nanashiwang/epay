<?php
if (PHP_SAPI!=='cli') exit;
$dbName=getenv('EPAY_TEST_DB')?:'';
if (!preg_match('/\Aepay_bepusdt_[a-z0-9_]*test\z/D',$dbName)) exit("Refusing non-test database\n");
define('IN_CRONLITE',true);define('ROOT',dirname(__DIR__).'/');define('SYSTEM_ROOT',ROOT.'includes/');define('PLUGIN_ROOT',ROOT.'plugins/');define('SYS_KEY','synthetic-test-only');
date_default_timezone_set('Asia/Shanghai');error_reporting(E_ERROR|E_PARSE);
require SYSTEM_ROOT.'autoloader.php';Autoloader::register();require SYSTEM_ROOT.'functions.php';
$dbconfig=['host'=>getenv('EPAY_TEST_HOST')?:'localhost','port'=>getenv('EPAY_TEST_PORT')?:3306,'dbname'=>$dbName,'user'=>getenv('EPAY_TEST_USER')?:'root','pwd'=>getenv('EPAY_TEST_PASSWORD')?:'','dbqz'=>'bt'];
$DB=new \lib\PdoHelper($dbconfig);$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$CACHE=new \lib\Cache();$conf=['bepusdt_parent'=>1,'collection_parent'=>1,'group_buy'=>1,'reg_pay_uid'=>1002,'localurl'=>'https://epay.example/','notifyordername'=>0,'black_payact'=>0,'invite_mode'=>1];
$siteurl='https://epay.example/';$clientip='127.0.0.1';$_SERVER['HTTP_HOST']='epay.example';$device='pc';
