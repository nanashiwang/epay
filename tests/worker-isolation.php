<?php
require __DIR__.'/merchant-operations.php';
$start=$checks;
$DB->exec("REPLACE INTO pre_config VALUES ('msgconfig_group','1')");$CACHE->clear();
$DB->exec("DELETE FROM pre_cache WHERE k LIKE 'worker_%'");
$env=getenv();$env['EPAY_TEST_BLOCK_MAIL']='1';
$p=proc_open([PHP_BINARY,ROOT.'tests/worker-probe.php','subscription'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);
stream_set_timeout($pipes[1],10);
try {
    check(trim(fgets($pipes[1])?:'')==='MAIL','slow mail entered');
    check(lib\WorkerRuntime::read($DB,'subscription')===null,'blocked mail has no completion heartbeat');
    $c=proc_open([PHP_BINARY,ROOT.'tests/worker-probe.php','collection'],[['pipe','r'],['pipe','w'],['pipe','w']],$cp);
    fclose($cp[0]);stream_set_timeout($cp[1],10);stream_get_contents($cp[1]);
    $meta=stream_get_meta_data($cp[1]);
    if ($meta['timed_out']) proc_terminate($c);
    check(!$meta['timed_out'],'collection completes while mail is blocked');
    $error=stream_get_contents($cp[2]);fclose($cp[1]);fclose($cp[2]);check(proc_close($c)===0,'collection worker succeeds: '.$error);
    check(lib\WorkerRuntime::read($DB,'collection')['state']==='ok','independent collection heartbeat');
    fwrite($pipes[0],"release\n");fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);check(proc_close($p)===0,'mail worker succeeds: '.$error);$p=null;
    check(lib\WorkerRuntime::read($DB,'subscription')['state']==='ok','mail completion heartbeat');
    $DB->exec("REPLACE INTO pre_config VALUES ('msgconfig_group','0')");$CACHE->clear();
    $c=proc_open([PHP_BINARY,ROOT.'tests/worker-probe.php','subscription'],[['pipe','r'],['pipe','w'],['pipe','w']],$cp);
    fclose($cp[0]);$out=stream_get_contents($cp[1]);fclose($cp[1]);fclose($cp[2]);check(proc_close($c)===0 && $out==='','disabled mail sends nothing');
    check(lib\WorkerRuntime::read($DB,'subscription')['state']==='disabled','worker reads current notification switch');
} finally { if (is_resource($p)) proc_terminate($p); }
echo 'Worker isolation: '.($checks-$start)." checks passed\n";
