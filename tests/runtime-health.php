<?php
require __DIR__.'/merchant-operations.php';
use lib\RuntimeHealth as Health;
use lib\WorkerRuntime as Worker;
$start=$checks;
foreach(explode(';',file_get_contents(ROOT.'install/payment-review.sql')) as $sql)if(trim($sql)!=='')$DB->exec($sql);
$conf['version']=2053;$conf['msgconfig_group']=0;
$key=tempnam(sys_get_temp_dir(),'epay-health-key-');file_put_contents($key,random_bytes(32));chmod($key,0600);putenv('EPAY_COLLECTION_KEY_FILE='.$key);
function healthRow($report,$name){foreach($report['checks'] as $r)if($r['name']===$name)return $r;return null;}
try {
    foreach([['collection_account','secret','collection','id'],['bepusdt_order','config_snapshot','bepusdt','trade_no'],['merchant_channel_order','config_snapshot','merchant-order','trade_no']] as [$table,$field,$purpose,$pk])foreach($DB->getAll('SELECT * FROM pre_'.$table) as $r)$DB->update($table,[$field=>lib\GatewaySecrets::encrypt(['synthetic'=>true],$r['uid'],$purpose)],[$pk=>$r[$pk]]);
    Worker::beat($DB,'collection');Worker::beat($DB,'subscription','disabled');
    check(Health::check($DB,$conf)['ok'],'healthy migrated runtime with readable historical ciphertext');
    $DB->exec('RENAME TABLE pre_payment_review TO pre_payment_review_hidden');
    check(!healthRow(Health::check($DB,$conf),'收款扩展迁移')['ok'],'missing migration fails');$DB->exec('RENAME TABLE pre_payment_review_hidden TO pre_payment_review');
    $DB->exec('ALTER TABLE pre_payment_review DROP COLUMN result');check(!healthRow(Health::check($DB,$conf),'收款扩展迁移')['ok'],'missing column fails');$DB->exec("ALTER TABLE pre_payment_review ADD result varchar(300) NOT NULL DEFAULT ''");
    $old=file_get_contents($key);file_put_contents($key,random_bytes(32));check(!healthRow(Health::check($DB,$conf),'收款主密钥')['ok'],'wrong 32 byte key cannot pass old ciphertext check');file_put_contents($key,$old);
    check(Health::check($DB,$conf,time()+700)['exit_code']===2,'stale worker is retryable startup failure');
    Worker::beat($DB,'subscription','error');check(!Health::check($DB,$conf)['ok'],'failed reminder heartbeat fails even when mail disabled');Worker::beat($DB,'subscription','disabled');
    check(!Health::check($DB,array_replace($conf,['msgconfig_group'=>1]))['ok'],'enabled mail requires active task completion');
    check(Health::check($DB,array_replace($conf,['version'=>1]))['exit_code']===1,'old schema fails permanently');
    $DB->update('order',['subchannel'=>91,'notify'=>-1,'endtime'=>date('Y-m-d H:i:s',time()-7200)],['trade_no'=>$managed]);$DB->update('merchant_channel_order',['paid_at'=>'NOW()'],['trade_no'=>$managed]);
    $r=Health::check($DB,$conf);check($r['ok'] && count($r['warnings'])===1,'notification backlog warns without sending');
    check($DB->findColumn('order','notify',['trade_no'=>$managed])==-1,'health checks never send retries');
    $badConfig=$dbconfig;$badConfig['dbname']='epay_bepusdt_missing_test';reject(fn()=>new lib\PdoHelper($badConfig,true),'health DB bootstrap throws instead of exit zero');
    check(!str_contains(json_encode($r),'synthetic') && !str_contains(json_encode($r),$old),'health output excludes secrets');
} finally {unlink($key);}
echo 'Runtime health: '.($checks-$start)." checks passed\n";
