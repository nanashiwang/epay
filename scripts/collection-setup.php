<?php
// Additive, explicit CLI migration. Never run through the web server.
if (PHP_SAPI!=='cli') exit;
$nosession=true;
$_SERVER['HTTP_HOST']=getenv('HTTP_HOST')?:'localhost';
require dirname(__DIR__).'/includes/common.php';
if (!in_array('--apply',$argv,true)) exit("用法：php scripts/collection-setup.php --apply\n先备份数据库；密钥文件必须由运行 PHP 的用户可读，并独立备份。\n");
$keyFile=\lib\CollectionAccount::keyPath();
if (!file_exists($keyFile)) {
    $old=umask(0077); $handle=fopen($keyFile,'x');
    if (!$handle || fwrite($handle,random_bytes(32))!==32) throw new RuntimeException('无法创建加密密钥文件');
    fclose($handle); umask($old);
}
foreach(explode(';',file_get_contents(ROOT.'install/collection.sql')) as $sql) {
    if (trim($sql)!=='' && $DB->exec($sql)===false) throw new RuntimeException('迁移失败');
}
$type=$DB->getColumn("SELECT id FROM pre_type WHERE name='alipay'");
if (!$type) throw new RuntimeException('支付宝支付类型不存在');
$parent=$DB->getColumn("SELECT v FROM pre_config WHERE k='collection_parent'");
if (!$parent) {
    $parent=$DB->insert('channel',['mode'=>1,'type'=>$type,'plugin'=>'alipaycode','name'=>'商户自助原生码模板','rate'=>'100.00','costrate'=>'0.00','status'=>0,'config'=>json_encode(['collection_managed'=>1,'appswitch'=>'2'])]);
    if (!$parent) throw new RuntimeException('模板创建失败');
    $DB->exec("INSERT INTO pre_config (k,v) VALUES ('collection_parent',:id)",[':id'=>$parent]);
    $CACHE->clear();
}
echo "迁移完成；模板保持关闭，不加入平台通道池。配置加密密钥权限并启动 collection-worker.php 后即可使用。\n";
