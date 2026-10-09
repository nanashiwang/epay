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
    $parent=$DB->getColumn("SELECT v FROM pre_config WHERE k='bepusdt_parent' FOR UPDATE");
    $map=$DB->getColumn("SELECT v FROM pre_config WHERE k='bepusdt_parents' FOR UPDATE");
    $parents=\lib\BepusdtNetwork::parents(['bepusdt_parent'=>$parent,'bepusdt_parents'=>$map?:'{}']);
    foreach (\lib\BepusdtNetwork::TYPES as $name=>$network) {
        $type=$DB->getColumn('SELECT id FROM pre_type WHERE name=:name',[':name'=>$name]);
        if (!$type) $type=$DB->insert('type',['name'=>$name,'showname'=>\lib\BepusdtNetwork::label($name),'status'=>1]);
        if (empty($parents[$name])) {
            $parents[$name]=$DB->insert('channel',['mode'=>1,'type'=>$type,'plugin'=>'bepusdt','name'=>'商户 '.\lib\BepusdtNetwork::label($name).' 模板','rate'=>'100.00','costrate'=>'0.00','status'=>0,'config'=>json_encode(['bepusdt_managed'=>1])]);
        } else {
            $p=$DB->find('channel','*',['id'=>(int)$parents[$name]]);
            if (!$p || (int)$p['type']!==(int)$type || $p['plugin']!=='bepusdt' || (int)$p['mode']!==1 || (int)$p['status']!==0 || (json_decode($p['config'],true)['bepusdt_managed']??null)!==1) throw new RuntimeException('现有 BEpusdt 模板配置异常，请核对 '.$name.'，迁移未修改已有模板');
        }
    }
    if (!$parent) {
        $DB->insert('config',['k'=>'bepusdt_parent','v'=>$parents['usdt.trc20']]);
        $DB->insert('config',['k'=>'bepusdt_cutover','v'=>date('Y-m-d H:i:s')]);
    }
    $DB->exec('INSERT INTO pre_config (k,v) VALUES (:key,:value) ON DUPLICATE KEY UPDATE v=VALUES(v)',[':key'=>'bepusdt_parents',':value'=>json_encode($parents)]);
});
$CACHE->clear();
echo "BEpusdt 迁移完成。模板保持关闭；请配置包月套餐，并由商户完成接口校验与测试付款后启用。\n";
