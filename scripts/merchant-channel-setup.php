<?php
if (PHP_SAPI!=='cli') exit;
$nosession=true;
$_SERVER['HTTP_HOST']=getenv('HTTP_HOST')?:'localhost';
require dirname(__DIR__).'/includes/common.php';
if (!in_array('--apply',$argv,true)) exit("用法：php scripts/merchant-channel-setup.php --apply\n先备份数据库和站点外加密密钥，并完成 collection-setup 与 bepusdt-setup。\n");
if (empty($conf['collection_parent']) || empty($conf['bepusdt_parent'])) throw new RuntimeException('请先执行 collection-setup.php --apply 和 bepusdt-setup.php --apply');
$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
\lib\GatewaySecrets::encrypt(['check'=>1],0,'merchant-channel');
if ((int)$DB->getColumn("SELECT GET_LOCK('merchant-channel-setup',10)")!==1) throw new RuntimeException('迁移正在执行，请稍后重试');
try {
    foreach (explode(';',file_get_contents(ROOT.'install/merchant-channel.sql')) as $sql) if (trim($sql)!=='') $DB->exec($sql);
    \lib\DbTransaction::run($DB,function()use($DB) {
        $names=['alipay'=>'支付宝','wxpay'=>'微信支付','qqpay'=>'QQ 钱包','bank'=>'银行卡','jdpay'=>'京东支付'];
        foreach (\lib\MerchantChannelCatalog::all() as $plugin=>$spec) foreach ($spec['types'] as $name) {
            $types=$DB->getAll('SELECT id FROM pre_type WHERE name=:name',[':name'=>$name]);
            if (!$types) $types=[['id'=>$DB->insert('type',['name'=>$name,'showname'=>$names[$name],'status'=>1])]];
            foreach ($types as $t) {
                if ($DB->find('merchant_channel_template','channel',['plugin'=>$plugin,'type'=>$t['id']])) continue;
                $id=$DB->insert('channel',['mode'=>1,'type'=>$t['id'],'plugin'=>$plugin,'name'=>'商户自配 · '.$spec['name'],'rate'=>'100.00','costrate'=>'0.00','status'=>0,'config'=>'{"merchant_managed":1}']);
                $DB->insert('merchant_channel_template',['plugin'=>$plugin,'type'=>$t['id'],'channel'=>$id]);
            }
        }
        $DB->exec("INSERT INTO pre_config (k,v) VALUES ('merchant_channels','1') ON DUPLICATE KEY UPDATE v='1'");
    });
    $CACHE->clear();
} finally { $DB->getColumn("SELECT RELEASE_LOCK('merchant-channel-setup')"); }
echo "商户支付通道迁移完成。模板保持停用且不包含密钥；在套餐中开启商户自配权益，由商户添加、测试和启用自己的账号。旧套餐、平台月费通道与商户资金不变。\n";
