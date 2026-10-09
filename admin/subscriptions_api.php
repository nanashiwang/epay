<?php
require '../includes/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function subscription_reply($code,$msg,$data=[]) { echo json_encode(['code'=>$code,'msg'=>$msg]+$data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if ($islogin!=1) { http_response_code(401); subscription_reply(-3,'请先登录管理员账号'); }
try {
    if (!\lib\MerchantOperations::table($DB,'subscription_resolution')) throw new InvalidArgumentException('请先执行套餐运营迁移');
    $act=$_GET['act']??'list';
    if ($act==='list') subscription_reply(0,'',\lib\MerchantOperations::reviewList($DB,$_GET['page']??1,$_GET['state']??'review'));
    if ($act!=='resolve' || $_SERVER['REQUEST_METHOD']!=='POST' || !is_string($_POST['csrf']??null) || empty($_SESSION['subscription_admin_csrf']) || !hash_equals($_SESSION['subscription_admin_csrf'],$_POST['csrf'])) { http_response_code(403); throw new InvalidArgumentException('页面验证已过期，请刷新重试'); }
    $msg=\lib\MerchantOperations::resolve($DB,$_POST['trade_no']??'',$_POST['action']??'',$_POST['reason']??'',$_POST['reference']??'',$_POST['version']??'',(string)$conf['admin_user'],($_POST['confirmed']??'')==='1');
    subscription_reply(0,$msg);
} catch (InvalidArgumentException $e) { subscription_reply(-1,$e->getMessage()); }
catch (Throwable $e) { subscription_reply(-1,'处理未完成，请刷新核对；权益和审计必须同时保存'); }
