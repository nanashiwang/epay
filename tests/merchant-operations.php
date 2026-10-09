<?php
require __DIR__.'/bepusdt-bootstrap.php';
use lib\MerchantOperations as Ops;
use lib\MerchantSubscription as Sub;
$conf['merchant_channels']=1;
foreach (['install','collection','bepusdt','merchant-channel','merchant-operations'] as $file) foreach(explode(';',file_get_contents(ROOT.'install/'.$file.'.sql')) as $sql) if(trim($sql)!=='') $DB->exec($sql);
foreach (['subscription_resolution','subscription_reminder','subscription_purchase','subscription_event','merchant_channel_template','merchant_channel_audit','merchant_channel_order','merchant_channel_account','merchant_channel_route','bepusdt_order','bepusdt_account','bepusdt_route','collection_account','collection_route','collection_notify'] as $table) $DB->exec('DELETE FROM pre_'.$table);
$checks=0;
function check($v,$label){global $checks;if(!$v) throw new RuntimeException('FAIL: '.$label);$checks++;}
function reject(callable $fn,$label){try{$fn();}catch(Throwable $e){check(true,$label);return;}check(false,$label);}
function spawn($r){$p=proc_open([PHP_BINARY,ROOT.'tests/merchant-operations-probe.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fwrite($pipes[0],json_encode($r));fclose($pipes[0]);return [$p,$pipes];}
function result($proc){[$p,$pipes]=$proc;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('probe: '.$err);return $out;}
function probe($r){$s=result(spawn($r));$v=json_decode($s,true);if(!is_array($v))throw new RuntimeException('Invalid probe: '.$s);return $v;}
$gid=$DB->insert('group',['name'=>'商户月付套餐','isbuy'=>1,'price'=>'20.00','expire'=>1,'config'=>'{"merchant_channels_enabled":1,"merchant_channels_accounts":4}','info'=>'{}']);
$other=$DB->insert('group',['name'=>'另一套餐','isbuy'=>1,'price'=>'30.00','expire'=>1,'config'=>'{"merchant_channels_enabled":1,"merchant_channels_accounts":6}','info'=>'{}']);
$expiry=date('Y-m-d H:i:s',time()+5*86400);
foreach([1000,1001,1002] as $uid)$DB->insert('user',['uid'=>$uid,'gid'=>$uid===1002?0:$gid,'endtime'=>$uid===1002?null:$expiry,'key'=>'synthetic-key','money'=>'100.00','status'=>1,'pay'=>1,'email'=>'merchant'.$uid.'@example.invalid','msgconfig'=>serialize([])]);
function order($fields=[]){global $DB;static $seq=0;$trade='2026100977'.str_pad((string)++$seq,9,'0',STR_PAD_LEFT);$DB->insert('order',array_replace(['trade_no'=>$trade,'out_trade_no'=>$trade,'uid'=>1000,'type'=>1,'tid'=>0,'channel'=>1,'subchannel'=>0,'name'=>'合成订单','money'=>'10.00','realmoney'=>'10.00','getmoney'=>'9.00','addtime'=>'NOW()','date'=>'CURDATE()','status'=>1],$fields));return $trade;}
$legacy=order();$test=order(['tid'=>3]);$managed=order();
$DB->insert('merchant_channel_order',['trade_no'=>$managed,'uid'=>1000,'account_id'=>91,'channel_id'=>1,'type'=>1,'plugin'=>'epay','revision'=>1,'config_snapshot'=>'synthetic','money'=>10,'created_at'=>'NOW()']);
$be=order();$platformBe=order();
foreach ([$be=>92,$platformBe=>0] as $trade=>$account)$DB->insert('bepusdt_order',['trade_no'=>$trade,'uid'=>1000,'account_id'=>$account,'channel_id'=>1,'config_snapshot'=>'synthetic','money'=>10,'network'=>'TRC20','state'=>'paid','created_at'=>'NOW()']);
$DB->insert('collection_account',['id'=>93,'uid'=>1000,'alipay_uid'=>'2088000000000001','appid'=>'2026100900000001','qr_url'=>'https://qr.alipay.com/synthetic','secret'=>'synthetic','deleted_at'=>'NOW()','created_at'=>'NOW()']);
$native=order(['subchannel'=>93,'getmoney'=>'10.00']);$legacyNative=order(['subchannel'=>93]);
$sub=order(['tid'=>4]);$DB->insert('subscription_purchase',['trade_no'=>$sub,'uid'=>1000,'gid'=>$gid,'request_key'=>hash('sha256',$sub),'created_at'=>'NOW()']);
function eligible(){global $DB;return array_column($DB->getAll('SELECT O.trade_no FROM pre_order O WHERE O.status=1 AND '.Ops::referralWhere($DB).' ORDER BY O.trade_no'),'trade_no');}
check(eligible()===[$legacy,$platformBe,$legacyNative],'daily cashback keeps legacy receipts, excludes direct, tests and subscription');
$DB->update('user',['gid'=>0],['uid'=>1000]);check(eligible()===[$legacy,$platformBe,$legacyNative],'classification survives plan change and archived native account');$DB->update('user',['gid'=>$gid],['uid'=>1000]);
check(Ops::table($DB,'subscription_resolution'),'table lookup respects test prefix');
check(probe([])['code']===-3,'ordinary merchant cannot read admin queue');
check(probe(['anonymous'=>1])['code']===-3,'anonymous cannot read admin queue');
function review($target){global $DB,$gid;$trade=order(['uid'=>1002,'tid'=>4,'money'=>'20.00','realmoney'=>'20.00','getmoney'=>'20.00','param'=>json_encode(['subscription_v2'=>1,'uid'=>1000,'gid'=>$target,'months'=>1,'price'=>'20.00'])]);$DB->insert('subscription_purchase',['trade_no'=>$trade,'uid'=>1000,'gid'=>$target,'request_key'=>hash('sha256',$trade),'created_at'=>'NOW()']);$DB->insert('subscription_event',['trade_no'=>$trade,'uid'=>1000,'gid'=>$target,'months'=>1,'money'=>'20.00','state'=>'review','old_endtime'=>$DB->findColumn('user','endtime',['uid'=>1000]),'created_at'=>'NOW()']);return $trade;}
function version(){global $DB;return Ops::entitlementVersion($DB->find('user','*',['uid'=>1000]));}
function resolveRequest($trade,$action='apply'){return ['admin'=>1,'get'=>['act'=>'resolve'],'post'=>['trade_no'=>$trade,'action'=>$action,'reason'=>'已与商户确认处理依据','reference'=>$action==='close'?'external-ref-001':'','version'=>version(),'confirmed'=>'1','csrf'=>'synthetic-csrf']];}
$trade=review($gid);$request=resolveRequest($trade);
check(probe(array_replace_recursive($request,['post'=>['csrf'=>'bad']]))['code']===-1,'CSRF required');
check(probe(array_replace($request,['method'=>'GET']))['code']===-1,'POST required');
check(probe(array_replace_recursive($request,['post'=>['confirmed'=>'0']]))['code']===-1,'impact confirmation required');
check(probe(array_replace_recursive($request,['post'=>['reason'=>[]]]))['code']===-1,'malformed input safely rejected');
check(probe(array_replace_recursive($request,['post'=>['version'=>'stale']]))['code']===-1,'stale entitlement rejected');
$p1=spawn($request);$p2=spawn($request);$codes=[json_decode(result($p1),true)['code'],json_decode(result($p2),true)['code']];sort($codes);check($codes===[-1,0],'concurrent admins apply once');
check($DB->findColumn('user','endtime',['uid'=>1000])===Sub::addMonths($expiry,1),'same plan extends current period');
$audit=$DB->find('subscription_resolution','*',['trade_no'=>$trade]);check($audit['actor']==='synthetic-admin' && $audit['old_endtime']===$expiry,'immutable audit contains actor and previous entitlement');
check(probe($request)['code']===-1,'repeat handling rejected');
$trade=review($other);check(probe(resolveRequest($trade))['code']===0,'different plan explicit replacement');
check($DB->findColumn('user','gid',['uid'=>1000])==$other,'new plan assigned');
check(abs(strtotime($DB->findColumn('user','endtime',['uid'=>1000]))-strtotime(Sub::addMonths(date('Y-m-d H:i:s'),1)))<10,'replacement starts at handling time');
$trade=review($gid);$before=$DB->find('user','gid,endtime,money',['uid'=>1000]);
check(probe(array_replace_recursive(resolveRequest($trade,'close'),['post'=>['reference'=>'']]))['code']===-1,'offline resolution requires reference');
check(probe(resolveRequest($trade,'close'))['code']===0,'external handling recorded');check($DB->find('user','gid,endtime,money',['uid'=>1000])===$before,'external handling moves no funds or entitlement');
$list=probe(['admin'=>1,'get'=>['state'=>'resolved']]);check(count($list['rows'])===3,'resolved queue retains all audit records');
$trade=review($gid);$DB->exec("CREATE TRIGGER operations_audit_failure BEFORE INSERT ON pre_subscription_resolution FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
check(probe(resolveRequest($trade))['code']===-1,'audit write failure rejected');check($DB->find('user','gid,endtime,money',['uid'=>1000])===$before && $DB->findColumn('subscription_event','state',['trade_no'=>$trade])==='review','audit failure rolls back both entitlement and event');$DB->exec('DROP TRIGGER operations_audit_failure');
$html=result(spawn(['page'=>'plan','conf'=>['group_buy'=>0]]));check(str_contains($html,'套餐购买暂未开放') && str_contains($html,'external-ref-001') && str_contains($html,'已登记线下处理'),'closed purchases retain merchant history and resolution');
$html=result(spawn(['page'=>'plan','uid'=>1001,'conf'=>['group_buy'=>0]]));check(!str_contains($html,'external-ref-001'),'merchant history is owner scoped');
$html=result(spawn(['page'=>'plan','conf'=>['reg_pay_uid'=>9999]]));check(str_contains($html,'平台收款配置暂不可用') && str_contains($html,'external-ref-001'),'missing platform account retains history');
$html=result(spawn(['page'=>'home','conf'=>['completeinfo'=>1]]));check(str_contains($html,'今天的收款与服务状态') && !str_contains($html,"window.location.href='./completeinfo.php'"),'SaaS dashboard does not require legacy settlement account');
$stats=probe(['page'=>'stats','get'=>['act'=>'getcount']]);check((int)$stats['orders_today']===6 && (float)$stats['order_today_all']===60.0,'business statistics exclude test and subscription');
$view=lib\MerchantWorkspace::overview($DB,1000);check((int)$view['stats']['paid_orders']===6 && (float)$view['stats']['amount']===60.0,'workspace and stats use same definition');
$now=time();$DB->update('user',['endtime'=>date('Y-m-d H:i:s',$now+5*86400)],['uid'=>1000]);$DB->update('user',['email'=>''],['uid'=>1001]);
$deliveries=[];$sender=function($u,$g,$n)use(&$deliveries){$deliveries[]=$n['stage'];return true;};
check(Ops::sendReminders($DB,$sender,$now)===1,'7 day reminder');check(Ops::sendReminders($DB,$sender,$now)===0,'reminder deduplicated');
check(Ops::sendReminders($DB,$sender,$now+3*86400)===1,'3 day reminder');check(Ops::sendReminders($DB,$sender,$now+4*86400)===1,'1 day reminder');check(Ops::sendReminders($DB,$sender,$now+5*86400)===1,'expiry reminder');check($deliveries===['7','3','1','expired'],'reminder stages');
$renewed=date('Y-m-d H:i:s',$now+6*86400);$DB->update('user',['endtime'=>$renewed],['uid'=>1000]);
check(Ops::sendReminders($DB,fn()=>false,$now)===0,'failed delivery not marked sent');
check(Ops::sendReminders($DB,$sender,$now+60)===0,'failed delivery backs off');check(Ops::sendReminders($DB,$sender,$now+3601)===1,'renewed period and failed retry delivered');
$otherDb=new lib\PdoHelper($dbconfig);$otherDb->getColumn("SELECT GET_LOCK('epay:subscription-reminders',0)");check(Ops::sendReminders($DB,$sender,$now)===0,'concurrent reminder worker excluded');$otherDb->getColumn("SELECT RELEASE_LOCK('epay:subscription-reminders')");
check(Ops::reminder(['endtime'=>null],$now)===null && Ops::reminder(['endtime'=>date('Y-m-d H:i:s',$now+8*86400)],$now)===null,'no reminders for unlimited or distant expiry');
$DB->exec('DELETE FROM pre_subscription_reminder');$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT);$called=false;
$DB->exec("CREATE TRIGGER operations_reminder_failure BEFORE INSERT ON pre_subscription_reminder FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
reject(fn()=>Ops::sendReminders($DB,function()use(&$called){$called=true;return true;},$now),'failed reminder persistence stops delivery');check(!$called && $DB->db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_SILENT,'never send without ledger and restore PDO mode');$DB->exec('DROP TRIGGER operations_reminder_failure');$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
echo "Merchant operations: $checks checks passed\n";
