<?php
if (PHP_SAPI!=='cli') exit;
$nosession=true;
$_SERVER['HTTP_HOST']=getenv('HTTP_HOST')?:'localhost';
require dirname(__DIR__).'/includes/common.php';
if (!in_array('--apply',$argv,true)) exit("用法：php scripts/merchant-operations-setup.php --apply\n请先备份数据库；仅新增套餐处理审计和提醒记录。\n");
if (!\lib\MerchantOperations::table($DB,'subscription_event')) throw new RuntimeException('请先完成订阅基础迁移');
$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach (explode(';',file_get_contents(ROOT.'install/merchant-operations.sql')) as $sql) if (trim($sql)!=='') $DB->exec($sql);
echo "套餐运营迁移完成；未修改价格、商户权益或支付状态。\n";
