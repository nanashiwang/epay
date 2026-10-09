<?php
require __DIR__.'/merchant-operations.php';
use lib\SubscriptionDashboard as Dashboard;
$start=$checks;
$DB->exec('DELETE FROM pre_subscription_event');$DB->exec('DELETE FROM pre_subscription_purchase');
function sale($amount,$provider,$when,$status=1,$buyer=1000){global $DB,$gid;$t=order(['tid'=>4,'money'=>$amount,'api_trade_no'=>$provider,'endtime'=>$when,'status'=>$status]);$DB->insert('subscription_purchase',['trade_no'=>$t,'uid'=>$buyer,'gid'=>$gid,'request_key'=>hash('sha256',$t),'created_at'=>'NOW()']);$DB->insert('subscription_event',['trade_no'=>$t,'uid'=>$buyer,'gid'=>$gid,'months'=>1,'money'=>$amount,'state'=>'applied','created_at'=>$when]);return $t;}
sale('20.00','balance','2026-10-01 00:00:00');sale('30.00','provider-1','2026-10-02 12:00:00');sale('99.00','refund','2026-10-03 12:00:00',2);sale('70.00','future','2026-11-01 00:00:00');
$r=Dashboard::report($DB,'2026-10-01','2026-10-31');
check($r['summary']['purchases']==2 && (float)$r['summary']['amount']===50.0,'only current paid subscription orders within payment dates');
check((float)$r['summary']['balance_amount']===20.0 && (float)$r['summary']['external_amount']===30.0,'balance and external amount separated');
check($r['renewals']===1,'same merchant subsequent purchase counted as renewal');
check(count($r['daily'])===2 && count($r['plans'])===1,'daily and plan aggregation');
check(Dashboard::report($DB,'2026-10-02','2026-10-02')['renewals']===1,'renewal looks outside selected window');
reject(fn()=>Dashboard::report($DB,'2026-02-30','2026-10-31'),'invalid date');reject(fn()=>Dashboard::report($DB,[],'2026-10-31'),'malformed date');reject(fn()=>Dashboard::report($DB,'2025-01-01','2026-10-31'),'bounded range');
check(result(spawn(['page'=>'overview']))==='','merchant cannot read dashboard');
$html=result(spawn(['page'=>'overview','admin'=>1,'get'=>['from'=>'2026-10-01','to'=>'2026-10-31']]));check(str_contains($html,'¥50.00') && str_contains($html,'续购订单'),'admin dashboard renders exact totals');
for($i=1100;$i<1125;$i++)$DB->insert('user',['uid'=>$i,'gid'=>$gid,'status'=>1,'endtime'=>date('Y-m-d H:i:s',time()+86400)]);
$r=Dashboard::report($DB,'2026-10-01','2026-10-31');check(count($r['expiring'])===20 && $r['more'],'expiration list bounded');
$r=Dashboard::report($DB,'2026-10-01','2026-10-31',2);check(count($r['expiring'])>0 && !$r['more'],'expiration pagination');
check((float)Dashboard::report($DB,'2020-01-01','2020-01-01')['summary']['amount']===0.0,'empty dates zero totals');
echo 'Subscription dashboard: '.($checks-$start)." checks passed\n";
