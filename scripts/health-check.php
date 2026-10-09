<?php
if(PHP_SAPI!=='cli')exit;
error_reporting(0);date_default_timezone_set('Asia/Shanghai');
// Avoid common.php: it may exit with HTTP installation text and a successful CLI status.
define('ROOT',dirname(__DIR__).'/');define('SYSTEM_ROOT',ROOT.'includes/');
require SYSTEM_ROOT.'autoloader.php';Autoloader::register();
$report=['ok'=>false,'exit_code'=>1,'checks'=>[],'warnings'=>[]];
ob_start();
try {
    if(!is_file(ROOT.'install/install.lock')) throw new RuntimeException('installation incomplete');
    require ROOT.'config.php';
    $DB=new \lib\PdoHelper($dbconfig,true);
    $DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $conf=[];foreach($DB->getAll('SELECT k,v FROM pre_config') as $r)$conf[$r['k']]=$r['v'];
    $report=\lib\RuntimeHealth::check($DB,$conf);
} catch(Throwable $e) {
    $report['checks'][]=['name'=>'运行环境','ok'=>false,'detail'=>'无法完成检查；请核对安装锁、数据库连接、配置和迁移，不要公开凭据'];
}
ob_end_clean();
if(in_array('--json',$argv,true)) echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
else {
    foreach($report['checks'] as $r)echo ($r['ok']?'[通过] ':'[失败] ').$r['name'].'：'.$r['detail']."\n";
    foreach($report['warnings'] as $w)echo '[提示] '.$w."\n";
}
exit($report['exit_code']);
