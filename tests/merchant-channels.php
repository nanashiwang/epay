<?php
require __DIR__.'/bepusdt-bootstrap.php';
require_once SYSTEM_ROOT.'vendor/autoload.php';
use lib\MerchantChannel as Channels;
use lib\MerchantChannelCatalog as Catalog;
use lib\Channel;
$conf['merchant_channels']=1;
foreach (['install','collection','bepusdt','merchant-channel'] as $file) foreach(explode(';',file_get_contents(ROOT.'install/'.$file.'.sql')) as $sql) if(trim($sql)!=='') $DB->exec($sql);
foreach (['merchant_channel_template','merchant_channel_account','merchant_channel_route','merchant_channel_order','merchant_channel_audit','bepusdt_account','bepusdt_route','bepusdt_order','bepusdt_audit','subscription_purchase','subscription_event','collection_account','collection_route','collection_notify'] as $table) $DB->exec('DELETE FROM pre_'.$table);
$key=tempnam(sys_get_temp_dir(),'channel-key-');file_put_contents($key,random_bytes(32));putenv('EPAY_COLLECTION_KEY_FILE='.$key);register_shutdown_function(fn()=>unlink($key));
$checks=0;
function check($value,$label){global $checks;if(!$value)throw new RuntimeException('FAIL: '.$label);$checks++;}
function reject(callable $fn,$label){try{$fn();}catch(Throwable $e){check(true,$label);return;}check(false,$label);}
function spawn(array $r){$p=proc_open([PHP_BINARY,ROOT.'tests/merchant-channel-probe.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fwrite($pipes[0],json_encode($r));fclose($pipes[0]);return [$p,$pipes];}
function result($process){[$p,$pipes]=$process;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('probe: '.$err);return $out;}
function probe($r){$raw=result(spawn($r));$r=json_decode($raw,true);if($r===null)throw new RuntimeException('Invalid probe response: '.$raw);return $r;}
result(spawn(['setup'=>1]));$count=$DB->getColumn('SELECT COUNT(*) FROM pre_channel');result(spawn(['setup'=>1]));check($count==$DB->getColumn('SELECT COUNT(*) FROM pre_channel'),'setup idempotent');check($DB->getColumn('SELECT COUNT(*) FROM pre_channel WHERE status<>0 AND id IN (SELECT channel FROM pre_merchant_channel_template)')==0,'templates never in public pool');
$gid=$DB->insert('group',['name'=>'商户自助月费','isbuy'=>1,'price'=>'20.00','expire'=>1,'config'=>'{"merchant_channels_enabled":1,"merchant_channels_accounts":4}','info'=>'{}']);
$expiry=date('Y-m-d H:i:s',time()+864000);
foreach([1000,1001,1002] as $uid)$DB->insert('user',['uid'=>$uid,'gid'=>$uid===1002?0:$gid,'endtime'=>$uid===1002?null:$expiry,'key'=>'synthetic-key','money'=>'100.00','status'=>1,'pay'=>1,'msgconfig'=>serialize([])]);
$platform=$DB->insert('channel',['type'=>1,'plugin'=>'alipay','name'=>'platform','status'=>1,'rate'=>'95.00','mode'=>0,'config'=>'{}']);
check(Channel::getTypes(1000,$gid)===[],'unconfigured SaaS user sees no platform types');check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)===false,'no platform fallback');
$GLOBALS['platform_payment']=true;check(isset(Channel::getTypes(1000,$gid)[1]),'platform purchase uses platform channels');unset($GLOBALS['platform_payment']);
$svc=new Channels($DB);$input=['name'=>'我的易支付','plugin'=>'epay','type'=>1,'config'=>['appurl'=>'https://8.8.8.8/','appid'=>'1234','appkey'=>'synthetic-merchant-key']];
$id=$svc->save(1000,$input);$r=$svc->owned(1000,$id);check($r['status']==0,'saved disabled');check(!str_contains($r['secret'],'synthetic-merchant-key'),'encrypted account');
foreach ([['setChannel',[]],['delChannel',[]],['saveChannel',['action'=>'copy']],['saveChannel',['action'=>'edit']],['saveChannelInfo',[]]] as [$act,$post]) {
    $reply=probe(['admin'=>1,'get'=>['act'=>$act,'id'=>$r['channel'],'status'=>1],'post'=>array_merge($post,['id'=>$r['channel']])]);
    check($reply['code']===-1 && str_contains($reply['msg'],'模板'),'admin template guard '.$act.($post['action']??''));
}
check(Channel::getSub($id)['owner_uid']===1000,'tenant channel config loaded');check(Channel::getSub($id)['mode']===1,'always direct');
reject(fn()=>$svc->owned(1001,$id),'cross owner access');reject(fn()=>lib\GatewaySecrets::decrypt($r['secret'],1001,'merchant-channel'),'cipher UID bound');reject(fn()=>lib\GatewaySecrets::decrypt($r['secret'],1000),'cipher purpose bound');
reject(fn()=>$svc->action(1000,$id,'enable'),'untested cannot enable');
reject(fn()=>$svc->save(1000,array_replace_recursive($input,['plugin'=>'alipayd'])),'unreviewed plugin cannot be selected');
reject(fn()=>$svc->save(1000,array_replace_recursive($input,['config'=>['appurl'=>'https://127.0.0.1/']])),'private gateway URL rejected');
foreach(['transfer','refund','close','getHttpResponse','alipay'] as $action) reject(fn()=>Catalog::guard(Channel::getSub($id),$action),'arbitrary plugin method '.$action);
check(probe(['anonymous'=>1])['code']===-3,'API login required');
check(probe(['get'=>['act'=>'disable'],'post'=>['id'=>$id]])['code']===-1,'API CSRF');
check(probe(['method'=>'GET','get'=>['act'=>'disable'],'post'=>['id'=>$id,'csrf'=>'synthetic-csrf']])['code']===-1,'API mutation method');
check(probe(['uid'=>1001,'get'=>['act'=>'disable'],'post'=>['id'=>$id,'csrf'=>'synthetic-csrf']])['code']===-1,'API mutation owner');
$list=probe([]);check($list['code']===0 && !str_contains(json_encode($list),'synthetic-merchant-key') && !isset($list['data'][0]['secret']),'API credentials never echoed');
foreach(['orders','notify','audit'] as $kind) check(probe(['uid'=>1001,'get'=>['act'=>'records','kind'=>$kind]])['data']===[],'records owner '.$kind);
function makeOrder($id,$tid=0,$param='') {global $DB,$svc;static $seq=0;$r=$svc->owned(1000,$id);$trade='2026100988'.str_pad((string)++$seq,9,'0',STR_PAD_LEFT);$DB->insert('order',['trade_no'=>$trade,'out_trade_no'=>'merchant-'.$trade,'uid'=>1000,'type'=>$r['type'],'tid'=>$tid,'channel'=>$r['channel'],'subchannel'=>$id,'name'=>'合成支付','money'=>'1.00','realmoney'=>'1.00','getmoney'=>'1.00','param'=>$param,'addtime'=>'NOW()','status'=>0,'notify_url'=>'https://example.invalid/notify','return_url'=>'https://example.invalid/return']);return $DB->getRow('SELECT O.*,T.name typename FROM pre_order O JOIN pre_type T ON T.id=O.type WHERE O.trade_no=:t',[':t'=>$trade]);}
function epayReceipt($o,$key='synthetic-merchant-key',$override=[]) {$p=array_merge(['pid'=>'1234','type'=>'alipay','out_trade_no'=>$o['trade_no'],'trade_no'=>'provider-'.$o['trade_no'],'money'=>'1.00','trade_status'=>'TRADE_SUCCESS'],$override);$p['sign']=lib\Payment::makeSign($p,$key);$p['sign_type']='MD5';return $p;}
function dispatch($o,$data,$action='notify') {return probe(['dispatch'=>$action.'/'.$o['trade_no'].'/','get'=>$data]);}
$test=probe(['get'=>['act'=>'test'],'post'=>['id'=>$id,'csrf'=>'synthetic-csrf','amount'=>'1.00']]);check($test['code']===0,'test API creates order');preg_match('~/([0-9]+)/~',$test['url'],$m);$o=$DB->getRow('SELECT O.*,T.name typename FROM pre_order O JOIN pre_type T ON T.id=O.type WHERE O.trade_no=:t',[':t'=>$m[1]]);
check($DB->find('merchant_channel_order','trade_no',['trade_no'=>$o['trade_no']])!==false,'test snapshot before provider');
check(dispatch($o,epayReceipt($o),'return')['type']==='page','browser return waits for notify');check($DB->findColumn('order','status',['trade_no'=>$o['trade_no']])==0,'return cannot settle');
check(dispatch($o,epayReceipt($o,'bad-key'))['data']==='fail','SDK signature rejects forged callback');
foreach([['pid'=>'99'],['type'=>'wxpay'],['money'=>'1.01'],['out_trade_no'=>'wrong'],['trade_status'=>'WAIT_BUYER_PAY']] as $change)check(isset(dispatch($o,epayReceipt($o,'synthetic-merchant-key',$change))['error']),'signed callback contract mismatch');
check(dispatch($o,epayReceipt($o))['data']==='success','signed test callback');check((bool)$svc->owned(1000,$id)['tested_at'],'callback enables test status');
$svc->action(1000,$id,'enable');$svc->action(1000,$id,'default');$route=Channel::getSubmitInfo(1,'alipay',1000,$gid,1);check($route['subchannel']==$id && $route['rate']===100,'own route zero fee');check(isset(Channel::getTypes(1000,$gid)[1]),'own type available');
check(probe(['pay_api'=>'direct-fee'])['code']===1,'signed payment API');$apiOrder=$DB->find('order','*',['out_trade_no'=>'direct-fee']);check($apiOrder['money']===$apiOrder['realmoney'] && $apiOrder['money']===$apiOrder['getmoney'],'API minimum and random fees skipped');
check(probe(['pay_api'=>'web-cashier','pay_method'=>'web'])['code']===1,'web API executes managed plugin');
$webOrder=$DB->find('order','*',['out_trade_no'=>'web-cashier']);check((bool)$DB->find('merchant_channel_order','trade_no',['trade_no'=>$webOrder['trade_no']]),'partial API order creates canonical snapshot');
$web=makeOrder($id);$DB->update('order',['type'=>0,'channel'=>0,'subchannel'=>0,'realmoney'=>null,'getmoney'=>null],['trade_no'=>$web['trade_no']]);
$html=result(spawn(['submit2'=>$web['trade_no']]));check(str_contains($html,'https://8.8.8.8/submit.php'),'cashier dispatches own gateway form');
$web=$DB->find('order','*',['trade_no'=>$web['trade_no']]);check($web['realmoney']==='1.00' && $web['getmoney']==='1.00','cashier minimum and random fee bypass');
check((bool)$DB->find('merchant_channel_order','trade_no',['trade_no'=>$web['trade_no']]),'cashier creates canonical snapshot');
$DB->update('user',['mode'=>1],['uid'=>1000]);probe(['pay_api'=>'direct-payer-fee']);$apiOrder=$DB->find('order','*',['out_trade_no'=>'direct-payer-fee']);check($apiOrder['realmoney']==='1.00' && $apiOrder['getmoney']==='1.00','payer fee mode zero');
$o=makeOrder($id);$channel=Channels::prepare($DB,$o,Channel::getSub($id),'submit');
$money=$DB->findColumn('user','money',['uid'=>1000]);$p1=spawn(['dispatch'=>'notify/'.$o['trade_no'].'/','get'=>epayReceipt($o)]);$p2=spawn(['dispatch'=>'notify/'.$o['trade_no'].'/','get'=>epayReceipt($o)]);check(json_decode(result($p1),true)['data']==='success' && json_decode(result($p2),true)['data']==='success','concurrent callback idempotent');
check($DB->findColumn('user','money',['uid'=>1000])===$money,'no platform balance effect');check($DB->getColumn('SELECT COUNT(*) FROM pre_record WHERE trade_no=:t',[':t'=>$o['trade_no']])==0,'no fee or referral ledger');check($DB->getColumn('SELECT COUNT(*) FROM pre_collection_notify WHERE trade_no=:t',[':t'=>$o['trade_no']])==1,'one initial business notification');
check(isset(dispatch($o,epayReceipt($o,'synthetic-merchant-key',['trade_no'=>'other']))['error']),'conflicting replay rejected');
$collision=makeOrder($id);Channels::prepare($DB,$collision,Channel::getSub($id),'submit');check(isset(dispatch($collision,epayReceipt($collision,'synthetic-merchant-key',['trade_no'=>epayReceipt($o)['trade_no']]))['error']),'receipt reuse rejected');check($DB->findColumn('order','status',['trade_no'=>$collision['trade_no']])==0,'receipt collision rolls back');
$old=makeOrder($id);Channels::prepare($DB,$old,Channel::getSub($id),'submit');$DB->update('user',['endtime'=>'2020-01-01 00:00:00'],['uid'=>1000]);check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)===false,'expired route fails closed');check(Channel::getTypes(1000,$gid)===[],'expired types hidden');reject(fn()=>Channels::prepare($DB,makeOrder($id),Channel::getSub($id),'submit'),'expired new order denied');check(dispatch($old,epayReceipt($old))['data']==='success','old callback survives expiry');$DB->update('user',['endtime'=>$expiry],['uid'=>1000]);
$oldTest=makeOrder($id,3,'{"merchant_channel_test":1}');Channels::prepare($DB,$oldTest,Channel::getSub($id),'submit');$svc->action(1000,$id,'disable');$svc->save(1000,array_replace_recursive($input,['id'=>$id,'config'=>['appkey'=>'new-synthetic-key']]));check(dispatch($oldTest,epayReceipt($oldTest))['data']==='success','old credential snapshot verifies');check(!$svc->owned(1000,$id)['tested_at'],'old test cannot approve new revision');
check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)===false,'disabled explicit route fails closed');$svc->action(1000,$id,'unroute');check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)===false,'unroute never falls back for SaaS');
// Real Alipay SDK signature path and isolated config; generated keys never leave the test process tree.
$k=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);openssl_pkey_export($k,$private);$public=openssl_pkey_get_details($k)['key'];
$aliInput=['name'=>'支付宝官方','plugin'=>'alipay','type'=>1,'config'=>['appid'=>'2026100900000001','appmchid'=>'2088000000000001','appsecret'=>$private,'appkey'=>$public,'apptype'=>['3']]];
$ali=$svc->save(1000,$aliInput);$ao=makeOrder($ali,3,'{"merchant_channel_test":1}');Channels::prepare($DB,$ao,Channel::getSub($ali),'submit');
function aliReceipt($o,$private,$changes=[]) {$p=array_merge(['app_id'=>'2026100900000001','seller_id'=>'2088000000000001','out_trade_no'=>$o['trade_no'],'trade_no'=>'ali-'.$o['trade_no'],'total_amount'=>'1.00','trade_status'=>'TRADE_SUCCESS','buyer_id'=>'2088000000000002'],$changes);ksort($p);$data=urldecode(http_build_query($p));openssl_sign($data,$sign,$private,OPENSSL_ALGO_SHA256);$p['sign']=base64_encode($sign);$p['sign_type']='RSA2';return $p;}
$bad=probe(['dispatch'=>'notify/'.$ao['trade_no'].'/','post'=>aliReceipt($ao,$private,['seller_id'=>'2088000000000099'])]);check(isset($bad['error']),'Alipay signed wrong seller rejected');
$valid=probe(['dispatch'=>'notify/'.$ao['trade_no'].'/','post'=>aliReceipt($ao,$private)]);check(($valid['data']??'')==='success','Alipay RSA2 SDK callback: '.json_encode($valid));
$svc->action(1000,$ali,'enable');$svc->action(1000,$ali,'default');check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)['subchannel']==$ali,'replace default same type');
$wx=$svc->save(1000,['name'=>'微信 V3','plugin'=>'wxpayn','type'=>2,'config'=>['appid'=>'wx1234567890abcdef','appmchid'=>'1234567890','appsecret'=>str_repeat('K',32),'appkey'=>str_repeat('A',32),'publickeyid'=>'PUB_KEY_ID_1234567890123456','merchant_private_key'=>$private,'platform_public_key'=>$public,'apptype'=>['1','3']]]);
$channel=Channel::getSub($wx);$config=require ROOT.'plugins/wxpayn/inc/config.php';$client=new WeChatPay\V3\PaymentService($config);check($config['merchantPrivateKeyFilePath']==='' && $client instanceof WeChatPay\V3\PaymentService,'V3 keys constructed without shared files');
$wo=makeOrder($wx,3,'{"merchant_channel_test":1}');Channels::prepare($DB,$wo,$channel,'submit');$receipt=['appid'=>$channel['appid'],'mchid'=>$channel['appmchid'],'out_trade_no'=>$wo['trade_no'],'trade_state'=>'SUCCESS','amount'=>['total'=>100,'currency'=>'CNY']];Channels::receipt($receipt,$channel,$wo);check(true,'V3 identity amount contract');foreach([['amount'=>['total'=>99,'currency'=>'CNY']],['amount'=>['total'=>100,'currency'=>'USD']],['mchid'=>'other'],['combine_out_trade_no'=>'wrong']] as $change)reject(fn()=>Channels::receipt(array_replace($receipt,$change),$channel,$wo),'V3 wrong receipt');
Channels::settle($DB,$wo,'wx-'.$wo['trade_no']);$svc->action(1000,$wx,'enable');$svc->action(1000,$wx,'default');check(Channel::getSubmitInfo(2,'wxpay',1000,$gid,1)['subchannel']==$wx && Channel::getSubmitInfo(1,'alipay',1000,$gid,1)['subchannel']==$ali,'independent type defaults');
$qq=$svc->save(1000,['name'=>'QQ 钱包','plugin'=>'qqpay','type'=>3,'config'=>['appid'=>'12345678','appkey'=>str_repeat('Q',32),'apptype'=>['1']]]);reject(fn()=>$svc->save(1000,$input),'combined save quota');
$DB->update('group',['config'=>'{"merchant_channels_enabled":1,"merchant_channels_accounts":1}'],['gid'=>$gid]);check(Channel::getTypes(1000,$gid)===[],'over enabled quota fails closed');$svc->action(1000,$wx,'disable');check(isset(Channel::getTypes(1000,$gid)[1]),'disable excess restores selected route');$DB->update('group',['config'=>'{"merchant_channels_enabled":1,"merchant_channels_accounts":4}'],['gid'=>$gid]);
$DB->update('group',['info'=>'{"1":{"channel":0}}'],['gid'=>$gid]);check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)===false,'group closure wins');$DB->update('group',['info'=>'{}'],['gid'=>$gid]);$DB->update('type',['status'=>0],['id'=>1]);check(Channels::route($DB,1000,1,'alipay',1)===false,'type closure wins');$DB->update('type',['status'=>1],['id'=>1]);
foreach([1,2,4,5] as $tid)reject(fn()=>Channels::prepare($DB,makeOrder($ali,$tid),Channel::getSub($ali),'submit'),'platform tid denied '.$tid);
$forged=makeOrder($ali,4,'{"subscription_v2":1}');reject(fn()=>lib\Payment::processOrder(true,$forged,'forged'),'direct callback cannot buy subscription');
$arch=makeOrder($ali);Channels::prepare($DB,$arch,Channel::getSub($ali),'submit');$svc->action(1000,$ali,'disable');$svc->action(1000,$ali,'unroute');$svc->action(1000,$ali,'archive');check(probe(['dispatch'=>'notify/'.$arch['trade_no'].'/','post'=>aliReceipt($arch,$private)])['data']==='success','archived callback survives');
$subscription=lib\MerchantSubscription::purchase($DB,1001,$gid,1,0,'new-general-plan');check($subscription['code']===1,'general plan monthly purchase');check($DB->findColumn('user','money',['uid'=>1001])==='80.00','general plan charged only monthly price');
// Existing native QR and BE accounts participate in the same SaaS quota and fee contract.
$nativeParent=$DB->insert('channel',['type'=>1,'plugin'=>'alipaycode','name'=>'native template','mode'=>1,'status'=>0,'rate'=>'97.00','config'=>'{"collection_managed":1}']);
$nativeSvc=new lib\CollectionAccount($DB);
$native=$nativeSvc->save(1000,['name'=>'原生码','alipay_uid'=>'2088000000000001','appid'=>'2026100900000001','qr_url'=>'https://qr.alipay.com/testsynthetic','appsecret'=>$private,'appkey'=>$public],$nativeParent);
$DB->update('collection_account',['verified_at'=>'NOW()','last_ok'=>'NOW()','heartbeat_at'=>'NOW()'],['id'=>$native]);$nativeSvc->action(1000,$native,'enable');$nativeSvc->action(1000,$native,'default');
check(Channels::count($DB,1000)===4,'native account in total quota');
$nativeRoute=Channel::getSubmitInfo(1,'alipay',1000,$gid,1);check($nativeRoute['subchannel']==$native && $nativeRoute['rate']===100 && $nativeRoute['subscription_direct']===1,'native QR zero fee for general plan');
check(probe(['pay_api'=>'native-saas-fee'])['code']===1,'native SaaS API selection');$no=$DB->find('order','*',['out_trade_no'=>'native-saas-fee']);check($no['realmoney']==='1.00' && $no['getmoney']==='1.00','native minimum and random fee bypass');
reject(fn()=>$svc->save(1000,$input),'native consumes generic quota');
$beType=$DB->insert('type',['name'=>'usdt.trc20','showname'=>'USDT','status'=>1]);$beParent=$DB->insert('channel',['type'=>$beType,'plugin'=>'bepusdt','name'=>'BE template','mode'=>1,'status'=>0,'config'=>'{"bepusdt_managed":1}']);
$beInput=['name'=>'BE quota','endpoint'=>'https://8.8.8.8/','token'=>'synthetic-be-token','timeout'=>'1200'];
reject(fn()=>(new lib\BepusdtAccount($DB))->save(1000,$beInput,$beParent),'BE consumes total quota');
$svc->action(1000,$qq,'archive');$be=(new lib\BepusdtAccount($DB))->save(1000,$beInput,$beParent);check(Channels::count($DB,1000)===4,'BE and native share restored slot');
$DB->update('user',['endtime'=>'2020-01-01 00:00:00'],['uid'=>1000]);check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)===false,'expired native route stops');reject(fn()=>$nativeSvc->save(1000,['name'=>'x'],$nativeParent),'expired native creation rejected');$DB->update('user',['endtime'=>$expiry],['uid'=>1000]);
$rollback=makeOrder($wx,3,'{"merchant_channel_test":1}');Channels::prepare($DB,$rollback,Channel::getSub($wx),'submit');$DB->exec("CREATE TRIGGER bt_channel_fail BEFORE INSERT ON pre_merchant_channel_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
reject(fn()=>Channels::settle($DB,$rollback,'rollback-provider'),'receipt audit failure aborts transaction');$DB->exec('DROP TRIGGER bt_channel_fail');check($DB->findColumn('order','status',['trade_no'=>$rollback['trade_no']])==0 && !$DB->findColumn('merchant_channel_order','paid_at',['trade_no'=>$rollback['trade_no']]),'settlement and receipt roll back together');
$conf['merchant_channels']=0;check(Channels::isOrder($DB,$rollback),'historical channel identity survives disabled entry');check(Channel::getSubmitInfo(1,'alipay',1000,$gid,1)===false,'disabled service never falls back to platform');$conf['merchant_channels']=1;
echo "Merchant channels: $checks checks passed\n";
