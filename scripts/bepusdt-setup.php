<?php
if (PHP_SAPI!=='cli') exit;
$nosession=true;
$_SERVER['HTTP_HOST']=getenv('HTTP_HOST')?:'localhost';
require dirname(__DIR__).'/includes/common.php';
if (!in_array('--apply',$argv,true)) exit("用法：php scripts/bepusdt-setup.php --apply\n先备份数据库及网站外的收款加密密钥；管理员随后配置套餐售价与权益。\n");
if (empty($conf['collection_parent'])) throw new RuntimeException('请先执行 collection-setup.php --apply，复用收款账号基础设施');
$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
\lib\GatewaySecrets::encrypt(['check'=>1],0);
foreach (explode(';',file_get_contents(ROOT.'install/bepusdt.sql')) as $sql) if (trim($sql)!=='') $DB->exec($sql);
\lib\DbTransaction::run($DB,function()use($DB) {
    $type=$DB->getColumn("SELECT id FROM pre_type WHERE name='usdt.trc20'");
    if (!$type) $type=$DB->insert('type',['name'=>'usdt.trc20','showname'=>'USDT / TRC20','status'=>1]);
    $parent=$DB->getColumn("SELECT v FROM pre_config WHERE k='bepusdt_parent' FOR UPDATE");
    if (!$parent) {
        $parent=$DB->insert('channel',['mode'=>1,'type'=>$type,'plugin'=>'bepusdt','name'=>'商户 BEpusdt 直收模板','rate'=>'100.00','costrate'=>'0.00','status'=>0,'config'=>json_encode(['bepusdt_managed'=>1])]);
        $DB->insert('config',['k'=>'bepusdt_parent','v'=>$parent]);
        $DB->insert('config',['k'=>'bepusdt_cutover','v'=>date('Y-m-d H:i:s')]);
    }
});
$CACHE->clear();
echo "BEpusdt 迁移完成。模板保持关闭；请配置包月套餐，并由商户完成接口校验与测试付款后启用。\n";
