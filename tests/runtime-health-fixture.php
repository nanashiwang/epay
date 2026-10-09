<?php
require __DIR__.'/bepusdt-bootstrap.php';
if(getenv('EPAY_COLLECTION_KEY_FILE')!=='/var/lib/epay-keys/collection.key')exit('Docker fixture only');
foreach(['install','collection','bepusdt','merchant-channel','merchant-operations','payment-review'] as $file)foreach(explode(';',file_get_contents(ROOT.'install/'.$file.'.sql')) as $sql)if(trim($sql)!=='')$DB->exec($sql);
foreach(['merchant_channels'=>1,'bepusdt_parent'=>1,'collection_parent'=>1,'msgconfig_group'=>0] as $k=>$v)$DB->exec('REPLACE INTO pre_config VALUES (:k,:v)',[':k'=>$k,':v'=>$v]);
$CACHE->clear();
file_put_contents(lib\GatewaySecrets::path(),random_bytes(32));chmod(lib\GatewaySecrets::path(),0600);
$DB->insert('bepusdt_order',['trade_no'=>'202610090000001','uid'=>1000,'account_id'=>0,'channel_id'=>1,'money'=>10,'network'=>'usdt.trc20','state'=>'unknown','config_snapshot'=>lib\GatewaySecrets::encrypt(['appkey'=>'synthetic-only'],1000),'created_at'=>'NOW()']);
touch(ROOT.'install/install.lock');
touch('/tmp/epay-health-fixture-ready');
echo "Synthetic runtime fixture ready\n";
