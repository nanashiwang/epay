<?php
// Real MySQL integration tests; synthetic identities/keys only. Never load production config.php.
namespace Alipay {
    class AlipayBillService {
        public static $fail=false;
        public static $bills=[];
        public static $emptyWithoutList=false;
        public function __construct($config) {}
        public function accountlogQuery($start,$end,$page,$size,$uid) {
            if (self::$fail) throw new \RuntimeException('synthetic provider failure');
            if (self::$emptyWithoutList) return ['code'=>'10000','total_size'=>'0'];
            return ['total_size'=>count(self::$bills),'detail_list'=>self::$bills];
        }
    }
}
namespace {
if (PHP_SAPI!=='cli') exit;
$dbName=getenv('EPAY_TEST_DB')?:'epay_collection_test';
if (!preg_match('/\Aepay_collection_[a-z0-9_]*test\z/D',$dbName)) exit("Refusing non-test database\n");
define('ROOT',dirname(__DIR__).'/'); define('SYSTEM_ROOT',ROOT.'includes/'); define('SYS_KEY','test-only-not-a-secret');
date_default_timezone_set('Asia/Shanghai'); error_reporting(E_ERROR|E_PARSE);
require SYSTEM_ROOT.'autoloader.php'; Autoloader::register();
require SYSTEM_ROOT.'functions.php';
$dbconfig=['host'=>getenv('EPAY_TEST_HOST')?:'localhost','port'=>getenv('EPAY_TEST_PORT')?:3306,'dbname'=>$dbName,'user'=>getenv('EPAY_TEST_USER')?:'root','pwd'=>getenv('EPAY_TEST_PASSWORD')?:'','dbqz'=>'ct'];
$DB=new \lib\PdoHelper($dbconfig); $DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(explode(';',file_get_contents(ROOT.'install/install.sql')) as $sql) if(trim($sql)!=='') $DB->exec($sql);
foreach(explode(';',file_get_contents(ROOT.'install/collection.sql')) as $sql) if(trim($sql)!=='') $DB->exec($sql);
// The test database is disposable; clear extension rows on rerun after installing fresh core tables.
foreach(['account','route','receipt','reservation','audit','notify'] as $name) $DB->exec('DELETE FROM pre_collection_'.$name);
$keyPath=tempnam(sys_get_temp_dir(),'collection-test-key-'); file_put_contents($keyPath,random_bytes(32)); putenv('EPAY_COLLECTION_KEY_FILE='.$keyPath);
register_shutdown_function(function()use($keyPath){@unlink($keyPath);});
$checks=0;
function check($v,$label) { global $checks; if(!$v) throw new RuntimeException('FAIL: '.$label); $checks++; }
function rejected(callable $f,$label) { try{$f();}catch(Throwable $e){check(true,$label);return;}check(false,$label); }
$svc=new \lib\CollectionAccount($DB);
$CACHE=new \lib\Cache(); $conf=['collection_parent'=>1,'black_payact'=>0,'invite_mode'=>1,'notifyordername'=>0]; $siteurl='https://example.invalid/';
$parent=$DB->insert('channel',['mode'=>1,'type'=>1,'plugin'=>'alipaycode','name'=>'Test template','status'=>0,'rate'=>'99.00','costrate'=>'0.00','config'=>json_encode(['collection_managed'=>1,'appswitch'=>'2'])]);
$conf['collection_parent']=$parent;
foreach([1000,1001] as $uid) $DB->insert('user',['uid'=>$uid,'key'=>'synthetic-test-signing-key','money'=>'10.00','status'=>1,'pay'=>1,'msgconfig'=>serialize([])]);
$rsa=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]); openssl_pkey_export($rsa,$private); $public=openssl_pkey_get_details($rsa)['key'];
$input=['name'=>'测试支付宝','alipay_uid'=>'2088000000000001','appid'=>'2021000000000001','qr_url'=>'https://qr.alipay.com/example1','appsecret'=>$private,'appkey'=>$public];
$id=$svc->save(1000,$input,$parent); check($id>0,'create merchant subchannel');
$row=$svc->owned(1000,$id); check($row['status']==0,'new account disabled');
check(strpos($row['secret'],'PRIVATE')===false,'encrypted credentials');
check(!isset($svc->listing(1000)[0]['secret']) && !isset($svc->listing(1000)[0]['appsecret']),'listing does not expose secrets');
check(count($svc->listing(1001))===0,'account list owner scope');
rejected(fn()=>$svc->owned(1001,$id),'cross-merchant read');
rejected(fn()=>$svc->action(1001,$id,'enable'),'cross-merchant mutation');
rejected(fn()=>\lib\CollectionAccount::decrypt($row['secret'],1001),'ciphertext bound to owner');
$tampered=$row['secret'];$tampered[40]=$tampered[40]==='A'?'B':'A';rejected(fn()=>\lib\CollectionAccount::decrypt($tampered,1000),'authenticated ciphertext');
rejected(fn()=>$svc->save(1001,$input,$parent),'duplicate payee is rejected');
check((int)$DB->getColumn('SELECT COUNT(*) FROM pre_subchannel')===1,'duplicate creation rolled back');
rejected(fn()=>$svc->action(1000,$id,'enable'),'unverified account cannot enable');
$svc->verify(1000,$id);check(!empty($svc->owned(1000,$id)['verified_at']),'provider query verifies configuration');
check(\lib\CollectionAccount::health($svc->owned(1000,$id),time())==='监测离线','verification is not worker health');
$svc->action(1000,$id,'enable');
rejected(fn()=>$svc->action(1000,$id,'default'),'offline account cannot become default');
$worker=new \lib\CollectionWorker($DB); $worker->poll($svc->owned(1000,$id));
check(\lib\CollectionAccount::health($svc->owned(1000,$id),time())==='在线','successful poll online');
\Alipay\AlipayBillService::$emptyWithoutList=true;$worker->poll($svc->owned(1000,$id));
check(\lib\CollectionAccount::health($svc->owned(1000,$id),time())==='在线','Alipay empty response may omit detail_list');
\Alipay\AlipayBillService::$emptyWithoutList=false;
$svc->action(1000,$id,'default');
$route=\lib\Channel::getSubmitInfo(1,'alipay',1000,0,1);check((int)$route['subchannel']===$id && (int)$route['mode']===1,'selected account routes direct');
check(\lib\CollectionAccount::route($DB,1001,1,'alipay',1,null)===null,'other merchants unchanged');
check(\lib\CollectionAccount::route($DB,1000,2,'wxpay',1,null)===null,'other payment types unchanged');
$DB->update('collection_account',['heartbeat_at'=>date('Y-m-d H:i:s',time()-180)],['id'=>$id]);
check(\lib\CollectionAccount::route($DB,1000,1,'alipay',1,null)===false,'stale monitor fails closed');
$worker->poll($svc->owned(1000,$id));
$channel=\lib\Channel::getSub($id);check($channel['appswitch']==='2' && $channel['appmchid']===$input['alipay_uid'],'plugin config resolved from encrypted account');
function orderFor($trade,$id,$parent,$amount='1.00',$fee='0.01',$tid=3) {
    global $DB;
    $order=['trade_no'=>$trade,'out_trade_no'=>'test-'.$trade,'uid'=>1000,'tid'=>$tid,'type'=>1,'channel'=>$parent,'subchannel'=>$id,'name'=>'Synthetic test','money'=>$amount,'realmoney'=>$amount,'getmoney'=>number_format((float)$amount-(float)$fee,2,'.',''),'addtime'=>date('Y-m-d H:i:s',time()-2),'status'=>0,'notify_url'=>'https://example.invalid/notify','return_url'=>'https://example.invalid/return','profits'=>0,'version'=>0,'settle'=>0];
    $DB->insert('order',$order); return $DB->find('order','*',['trade_no'=>$trade]);
}
$o=orderFor('2026100919000000001',$id,$parent); $svc->reserve($o,$channel);$svc->reserve($o,$channel);check(true,'reservation idempotent for same order');
$collision=orderFor('2026100919000000002',$id,$parent);rejected(fn()=>$svc->reserve($collision,$channel),'same amount reservation conflict');
$bill=['direction'=>'收入','trans_amount'=>'1.00','trans_dt'=>date('Y-m-d H:i:s'),'alipay_order_no'=>'2026100923000000000000000001','trans_memo'=>''];
check($worker->receipt($svc->owned(1000,$id),$bill,time())===true,'receipt matched only against displayed reservations');
check($DB->findColumn('order','status',['trade_no'=>$o['trade_no']])==1,'order becomes paid');
check($DB->findColumn('user','money',['uid'=>1000])==='9.99','direct receipt deducts fee and never credits principal');
check($DB->getColumn('SELECT COUNT(*) FROM pre_record WHERE trade_no=:trade',[':trade'=>$o['trade_no']])==1,'one fee ledger entry');
check($DB->findColumn('order','status',['trade_no'=>$collision['trade_no']])==0,'blocked cashier order stays unpaid');
check($worker->receipt($svc->owned(1000,$id),$bill,time())===false,'receipt replay rejected');
check($DB->findColumn('user','money',['uid'=>1000])==='9.99','replay does not duplicate fee');
$bad=$bill;$bad['alipay_order_no']='2026100923000000000000000002';$bad['trans_amount']='2.00';
check(!$worker->receipt($svc->owned(1000,$id),$bad,time()),'unmatched receipt does not settle');
check($DB->findColumn('collection_receipt','state',['receipt_no'=>$bad['alipay_order_no']])==='unmatched','unmatched income is persisted');
$late=$bad;$late['alipay_order_no']='2026100923000000000000000003';$late['trans_dt']=date('Y-m-d H:i:s',time()-600);
$worker->receipt($svc->owned(1000,$id),$late,time());check($DB->findColumn('collection_receipt','state',['receipt_no'=>$late['alipay_order_no']])==='late','late income is persisted for review');
$failure=orderFor('2026100919000000003',$id,$parent,'3.00');$svc->reserve($failure,$channel);
$rollback=$bill;$rollback['trans_amount']='3.00';$rollback['alipay_order_no']='2026100923000000000000000004';
$DB->exec("CREATE TRIGGER ct_test_fail BEFORE INSERT ON pre_record FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic ledger failure'");
rejected(fn()=>$worker->receipt($svc->owned(1000,$id),$rollback,time()),'ledger failure aborts settlement');
check($DB->findColumn('order','status',['trade_no'=>$failure['trade_no']])==0,'order rollback on ledger failure');
check($DB->findColumn('user','money',['uid'=>1000])==='9.99','balance rollback on ledger failure');
check($DB->findColumn('collection_receipt','state',['receipt_no'=>$rollback['alipay_order_no']])==='unmatched','receipt remains unclaimed after rollback');
$DB->exec('DROP TRIGGER ct_test_fail');check($worker->receipt($svc->owned(1000,$id),$rollback,time())===true,'failed receipt recovers on next poll');
\Alipay\AlipayBillService::$fail=true;$worker->poll($svc->owned(1000,$id));
check(\lib\CollectionAccount::health($svc->owned(1000,$id),time())==='查询异常','provider failure visible');
check(\lib\CollectionAccount::route($DB,1000,1,'alipay',1,null)===false,'provider failure blocks new payments');
\Alipay\AlipayBillService::$fail=false;$worker->poll($svc->owned(1000,$id));
$business=orderFor('2026100919000000004',$id,$parent,'4.00','0.04',0);$svc->reserve($business,$channel);
$businessBill=$bill;$businessBill['trans_amount']='4.00';$businessBill['alipay_order_no']='2026100923000000000000000005';
check($worker->receipt($svc->owned(1000,$id),$businessBill,time()),'business order settled with deferred callback');
check($DB->findColumn('order','notify',['trade_no'=>$business['trade_no']])==1,'failed callback queued');
check($DB->getColumn('SELECT COUNT(*) FROM pre_collection_notify WHERE trade_no=:trade',[':trade'=>$business['trade_no']])==1,'callback attempt logged');
$callback=creat_callback($DB->find('order','*',['trade_no'=>$business['trade_no']]));parse_str(parse_url($callback['notify'],PHP_URL_QUERY),$q);
check($q['trade_status']==='TRADE_SUCCESS' && $q['out_trade_no']===$business['out_trade_no'] && $q['money']==='4','existing business callback contract');
$sign=$q['sign'];unset($q['sign'],$q['sign_type']);check(hash_equals($sign,\lib\Payment::makeSign($q,'synthetic-test-signing-key')),'business callback signature preserved');
$DB->update('order',['notifytime'=>date('Y-m-d H:i:s',time()-1)],['trade_no'=>$business['trade_no']]);
\lib\CollectionNotify::retry($DB,$business['trade_no']);check($DB->findColumn('order','notify',['trade_no'=>$business['trade_no']])==2,'automatic callback retry scheduled');
check($DB->getColumn('SELECT COUNT(*) FROM pre_collection_notify WHERE trade_no=:trade',[':trade'=>$business['trade_no']])==2,'retry produces separate attempt');
$log=$DB->getRow('SELECT * FROM pre_collection_notify ORDER BY id DESC LIMIT 1');check(strpos($log['target'],'?')===false && !isset($log['sign']),'callback log excludes signature query');
$meta=[];check(\lib\CollectionNotify::transport('http://127.0.0.1/',$meta)===false,'callback rejects private network');
$svc->action(1000,$id,'disable');check(\lib\CollectionAccount::route($DB,1000,1,'alipay',1,null)===false,'disable does not silently switch recipient');
rejected(fn()=>$svc->save(1000,array_merge($input,['id'=>$id,'appsecret'=>'','appkey'=>'']),$parent),'in-flight orders prevent credential edit');
$svc->action(1000,$id,'unroute');check(\lib\CollectionAccount::route($DB,1000,1,'alipay',1,null)===null,'explicit unroute restores existing selection');
check($DB->getColumn('SELECT COUNT(*) FROM pre_collection_audit')>=7,'mutations are audited');
function probe($request) {
    $p=proc_open([PHP_BINARY,ROOT.'tests/collection-api-probe.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    fwrite($pipes[0],json_encode($request));fclose($pipes[0]);
    $data=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0) throw new RuntimeException('probe failed: '.$error);
    $result=json_decode($data,true);if(!is_array($result)) throw new RuntimeException('invalid API result');return $result;
}
check(probe(['anonymous'=>true,'get'=>['act'=>'list']])['code']===-3,'API denies unauthenticated request');
check(probe(['get'=>['act'=>'enable'],'post'=>['id'=>$id]])['code']===-1,'API requires CSRF');
check(probe(['get'=>['act'=>'enable'],'post'=>['id'=>$id,'csrf'=>['synthetic-csrf']]])['code']===-1,'API rejects malformed CSRF');
check(probe(['method'=>'GET','get'=>['act'=>'enable'],'post'=>['id'=>$id,'csrf'=>'synthetic-csrf']])['code']===-1,'GET cannot mutate state');
check(probe(['uid'=>1001,'get'=>['act'=>'enable'],'post'=>['uid'=>1000,'id'=>$id,'csrf'=>'synthetic-csrf']])['code']===-1,'POST uid cannot override session owner');
check(probe(['uid'=>1001,'get'=>['act'=>'list']])['data']===[],'API list ownership');
check(probe(['uid'=>1001,'get'=>['act'=>'records','kind'=>'receipt']])['data']===[],'API receipt ownership');
check(probe(['uid'=>1001,'get'=>['act'=>'records','kind'=>'notify']])['data']===[],'API callback ownership');
check(probe(['uid'=>1001,'get'=>['act'=>'records','kind'=>'audit']])['data']===[],'API audit ownership');
check(probe(['uid'=>1001,'get'=>['act'=>'review'],'post'=>['id'=>1,'note'=>'forbidden','csrf'=>'synthetic-csrf']])['code']===-1,'API review ownership');
check(probe(['get'=>['act'=>'records','kind'=>'user']])['code']===-1,'record kind allowlist');
$apiList=probe(['get'=>['act'=>'list']]);check(strpos(json_encode($apiList),'appsecret')===false && strpos(json_encode($apiList),'secret')===false,'API never returns encrypted or plaintext keys');
$svc->action(1000,$id,'enable');
$parallel=[];
foreach(['2026100919000000005','2026100919000000006'] as $trade) {
    orderFor($trade,$id,$parent,'5.00');
    $p=proc_open([PHP_BINARY,ROOT.'tests/collection-api-probe.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    $parallel[]=[$p,$pipes,$trade];
}
foreach($parallel as [$p,$pipes,$trade]) { fwrite($pipes[0],json_encode(['reserve_trade'=>$trade]));fclose($pipes[0]); }
$successes=0;
foreach($parallel as [$p,$pipes,$trade]) { $successes+=(int)stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);proc_close($p); }
check($successes===1,'concurrent same-amount cashiers reserve exactly one order');
check(\lib\CollectionNotify::accepted(['http_code'=>200,'error'=>0],'success'),'HTTP 200 acknowledgement accepted');
check(!\lib\CollectionNotify::accepted(['http_code'=>500,'error'=>0],'success'),'HTTP error cannot acknowledge callback');
check(!\lib\CollectionNotify::accepted(['http_code'=>200,'error'=>28],'success'),'timeout cannot acknowledge callback');
check(!\lib\CollectionNotify::accepted(['http_code'=>200,'error'=>0],'pending'),'non-acknowledgement response rejected');
$DB->exec('UPDATE pre_order SET addtime=DATE_SUB(NOW(),INTERVAL 20 MINUTE) WHERE status=0');
$testCreated=probe(['get'=>['act'=>'test'],'post'=>['id'=>$id,'csrf'=>'synthetic-csrf']]);
check($testCreated['code']===0 && strpos($testCreated['url'],'/pay/qrcode/')===0,'test API accepts successful insert with a non-auto-increment order primary key');
$testReused=probe(['get'=>['act'=>'test'],'post'=>['id'=>$id,'csrf'=>'synthetic-csrf']]);
check($testReused['url']===$testCreated['url'],'repeated test click reuses pending order');
echo "Merchant collection: $checks checks passed\n";
}
