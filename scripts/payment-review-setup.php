<?php
if (PHP_SAPI!=='cli') exit;
$nosession=true;$_SERVER['HTTP_HOST']=getenv('HTTP_HOST')?:'localhost';
require dirname(__DIR__).'/includes/common.php';
if (!in_array('--apply',$argv,true)) exit("先备份数据库，再执行：php scripts/payment-review-setup.php --apply\n仅新增核对审计，不改变支付状态。\n");
$DB->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(explode(';',file_get_contents(ROOT.'install/payment-review.sql')) as $sql) if(trim($sql)!=='')$DB->exec($sql);
echo "支付核对审计迁移完成，未修改订单状态。\n";
