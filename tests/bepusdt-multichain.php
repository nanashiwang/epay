<?php
// Included by the isolated MySQL subscription suite, never a standalone web endpoint.
if (!defined('IN_CRONLITE') || PHP_SAPI!=='cli') exit;
$conf['bepusdt_parent']=$configured;
$conf['bepusdt_parents']=$DB->findColumn('config','v',['k'=>'bepusdt_parents']);
$parents=lib\BepusdtNetwork::parents($conf);
check(count($parents)===12,'migration creates all reviewed combinations');
$DB->update('group',['config'=>'{"bepusdt_enabled":1,"bepusdt_accounts":20}'],['gid'=>$gid]);
$available=probe(['multichain'=>true,'get'=>['act'=>'list']]);
check(count($available['networks'])===12,'API advertises configured templates');
check(!str_contains(json_encode($available),'synthetic-token'),'API never reveals credentials');
$addresses=['tron'=>'T'.str_repeat('A',33),'evm'=>'0x'.str_repeat('a',40),'solana'=>str_repeat('A',44)];
reject(fn()=>lib\BepusdtNetwork::address('usdc.solana',$addresses['tron']),'TRON Base58 is not a Solana public key');
reject(fn()=>lib\BepusdtNetwork::address('usdc.solana',str_repeat('A',32)),'short decoded Solana key rejected');
lib\BepusdtNetwork::address('usdc.solana',str_repeat('1',32));
check(true,'leading zero bytes supported for Solana address syntax');
$ids=[];
foreach (lib\BepusdtNetwork::TYPES as $name=>$network) {
    $parent=(int)$parents[$name];$type=$DB->findColumn('channel','type',['id'=>$parent]);
    $fields=array_merge($input,['name'=>$name,'trade_type'=>$name,'address'=>$addresses[$network['family']]]);
    reject(fn()=>$svc->save(1000,array_merge($fields,['address'=>'invalid-address']),$parent),'reject invalid '.$name.' address');
    $saved=probe(['multichain'=>true,'get'=>['act'=>'save'],'post'=>array_merge($fields,['csrf'=>'synthetic-csrf'])]);
    check($saved['code']===0,'save through API '.$name);$cryptoID=(int)$saved['id'];$ids[$name]=$cryptoID;
    $config=$svc->config($svc->owned(1000,$cryptoID));
    check($config['trade_type']===$name,'account binds exact '.$name);
    reject(fn()=>$svc->action(1000,$cryptoID,'enable'),'other networks do not qualify '.$name);
    $DB->update('bepusdt_account',['verified_at'=>'NOW()'],['id'=>$cryptoID]);
    $test=makeOrder($cryptoID,3);$gateway->create($test,$config);
    check($DB->findColumn('bepusdt_order','network',['trade_no'=>$test['trade_no']])===$name,'snapshot network '.$name);
    reject(fn()=>$gateway->notify($test['trade_no'],receipt($test,['trade_type'=>$name==='usdc.base'?'usdt.erc20':'usdc.base'])),'reject signed wrong network '.$name);
    check(!$svc->owned(1000,$cryptoID)['tested_at'],'wrong network never qualifies '.$name);
    $gateway->notify($test['trade_no'],receipt($test,['trade_type'=>$name]));
    check(!!$svc->owned(1000,$cryptoID)['tested_at'],'correct receipt qualifies '.$name);
    $svc->action(1000,$cryptoID,'enable');$svc->action(1000,$cryptoID,'default');
    check(lib\BepusdtAccount::route($DB,1000,$type,$name,1)['subchannel']==$cryptoID,'independent default '.$name);
    $api=probe(['multichain'=>true,'pay_api'=>'crypto-'.$name,'trade_type'=>$name]);
    check($api['code']==1,'signed business API '.$name);
    $business=$DB->find('order','*',['out_trade_no'=>'crypto-'.$name]);
    check((int)$business['subchannel']===$cryptoID && $business['realmoney']==='1.00','correct account and CNY '.$name);
    $gateway->create($business,$config);$gateway->notify($business['trade_no'],receipt($business));
    $gateway->notify($business['trade_no'],receipt($business));
    check($DB->findColumn('order','status',['trade_no'=>$business['trade_no']])==1,'standard receipt without network settles '.$name);
    check($DB->getColumn('SELECT COUNT(*) FROM pre_collection_notify WHERE trade_no=:t',[':t'=>$business['trade_no']])==1,'idempotent downstream notification '.$name);
    $svc->action(1000,$cryptoID,'disable');
    reject(fn()=>$svc->save(1000,array_merge($fields,['id'=>$cryptoID,'trade_type'=>$name==='usdc.base'?'usdt.erc20':'usdc.base']),$parents[$name==='usdc.base'?'usdt.erc20':'usdc.base']),'network immutable '.$name);
    $svc->action(1000,$cryptoID,'enable');
}
check(count($DB->getAll('SELECT * FROM pre_bepusdt_route WHERE uid=1000'))===12,'defaults coexist for all combinations');
foreach (['usdt.base','usdc.trc20','usdc.unknown'] as $unsupported) {
    $r=probe(['multichain'=>true,'get'=>['act'=>'save'],'post'=>array_merge($input,['csrf'=>'synthetic-csrf','trade_type'=>$unsupported])]);
    check($r['code']===-1,'reject unsupported pair '.$unsupported);
}
// Same EVM address must never allow a response from a different coin/network.
$parent=$parents['usdc.base'];$type=$DB->findColumn('channel','type',['id'=>$parent]);
$cryptoID=$ids['usdc.base'];$config=$svc->config($svc->owned(1000,$cryptoID));
foreach (['usdt.erc20',null] as $wrongType) {
    FakeClient::$mutate=function($d)use($wrongType){if($wrongType===null)unset($d['trade_type']);else $d['trade_type']=$wrongType;return $d;};
    reject(fn()=>$gateway->create(makeOrder($cryptoID),$config),'create must confirm exact network');
}
FakeClient::$mutate=null;
$DB->update('type',['status'=>0],['id'=>$type]);
check(count(lib\BepusdtNetwork::available($DB,$conf))===11,'disabled type hidden');
check(lib\BepusdtAccount::route($DB,1000,$type,'usdc.base',1)===false,'disabled type blocks route');
result(spawn(['setup'=>true]));
check($DB->findColumn('type','status',['id'=>$type])==0,'migration does not re-enable disabled type');
check($DB->findColumn('config','v',['k'=>'bepusdt_parents'])===$conf['bepusdt_parents'],'migration preserves all parent IDs');
$DB->update('type',['status'=>1],['id'=>$type]);
