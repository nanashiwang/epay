<?php
require __DIR__.'/merchant-operations.php';
$start=$checks;
$DB->insert('user',['uid'=>1003,'gid'=>0,'status'=>1,'pay'=>1,'key'=>'synthetic','money'=>0]);
$u=$DB->find('user','*',['uid'=>1003]);
check(lib\MerchantOnboarding::firstVisit($DB,$u,$conf),'new merchant receives guide');
check(!lib\MerchantOnboarding::firstVisit($DB,$u,[]),'legacy installation unchanged');
check(!lib\MerchantOnboarding::firstVisit($DB,array_replace($u,['account'=>'existing']),$conf),'configured settlement account unchanged');
$html=result(spawn(['page'=>'home','uid'=>1003]));check(str_contains($html,'你的开通进度') && !str_contains($html,"window.location.href='./completeinfo.php'"),'new merchant bypasses old settlement form');
order(['uid'=>1003]);check(!lib\MerchantOnboarding::firstVisit($DB,$u,$conf),'historical business merchant not auto migrated');
$html=result(spawn(['page'=>'onboarding','conf'=>['group_buy'=>0,'cert_force'=>1]]));check(str_contains($html,'套餐购买暂未开放') && str_contains($html,'检查实名认证'),'paused purchase and required certification explained');
$a=lib\MerchantOnboarding::progress($DB,1000);check(!$a['integrated'],'payment alone does not prove integration');
$DB->insert('collection_notify',['uid'=>1000,'trade_no'=>$managed,'target'=>'https://example.invalid','success'=>1,'created_at'=>'NOW()']);
check(lib\MerchantOnboarding::progress($DB,1000)['integrated'],'business success proves integration');
check(!lib\MerchantOnboarding::progress($DB,1001)['integrated'],'progress owner isolation');
$DB->update('user',['endtime'=>date('Y-m-d H:i:s',time()-3600)],['uid'=>1000]);
check(!lib\MerchantOnboarding::progress($DB,1000)['policy']['active'],'expired entitlement stays inactive');
echo 'Merchant onboarding: '.($checks-$start)." checks passed\n";
