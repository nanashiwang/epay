<?php
if (!defined('IN_CRONLITE') || !isset($reviewAdmin)) exit;
use lib\HelpCenter as H;
header('Cache-Control: no-store');
$title='支付异常核对';$reviewUid=$reviewAdmin?null:$uid;
$tokenKey=$reviewAdmin?'payment_review_admin_csrf':'payment_review_csrf';
$_SESSION[$tokenKey]=$_SESSION[$tokenKey]??bin2hex(random_bytes(24));
$message='';$error='';$list=null;
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!is_string($_POST['csrf']??null) || !hash_equals($_SESSION[$tokenKey],$_POST['csrf'])) throw new InvalidArgumentException('页面验证已过期，请刷新重试');
        $message=(new \lib\PaymentReview())->act($DB,$reviewUid,$reviewAdmin?'admin:'.(string)$conf['admin_user']:'merchant:'.$uid,$_POST);
    }
    $list=\lib\PaymentReview::listing($DB,$reviewUid,$_GET['page']??1,$_GET['trade']??'');
} catch (InvalidArgumentException $e) {$error=$e->getMessage();}
catch (Throwable $e) {$error='核对未完成，请刷新查看审计记录；请联系管理员检查数据迁移。';}
include $reviewAdmin?ROOT.'admin/head.php':ROOT.'user/head.php';
?>
<link rel="stylesheet" href="/user/assets/css/merchant-workspace.css">
<?php if (!$reviewAdmin) { ?><div id="content" class="app-content" role="main"><div class="app-content-body"><?php } ?>
<main class="<?= $reviewAdmin?'container admin-subscriptions ':''?>merchant-workspace">
<header class="mw-heading"><div><p class="mw-eyebrow"><?= $reviewAdmin?'全站商户收款':'我的收款'?></p><h1>支付异常核对</h1><p>核对未确认付款和业务通知，保留每次处理的依据。</p></div><a href="payment_review.php">刷新异常列表</a></header>
<p class="mw-notice">未确认不代表未付款，请先到收款机构或网关核对。查单和备注不修改付款状态、不重新创建订单、不执行退款。已确认付款仍由原通道签名回调处理。</p>
<form class="mw-toolbar" method="get"><label>平台订单号 <input name="trade" maxlength="32" pattern="[0-9]{10,32}" placeholder="留空查看异常队列" value="<?=H::escape($list['trade']??'')?>"></label><button class="btn btn-primary">查询订单</button></form>
<?php if ($message) { ?><p class="mw-notice" role="status"><?=H::escape($message)?></p><?php } if ($error) { ?><p class="mw-notice" role="alert"><?=H::escape($error)?></p><a href="payment_review.php">重新加载</a><?php } if($list) { ?>
<p>另有 <?=$list['unmatched']?> 条原生码收款流水尚未匹配。<?php if(!$reviewAdmin) { ?><a href="collection.php">在支付宝收款码的收款记录中核对</a><?php } else { ?>请结合商户的支付宝收款码记录核对。<?php } ?></p>
<?php if (!$list['rows']) { ?><section class="mw-card"><h2>暂无符合条件的订单</h2><p>异常队列显示创建结果未知、创建超过 2 分钟、未确认超过 30 分钟以及已付款但通知未完成的订单。也可按完整订单号查看收款记录及最近核对历史。</p></section><?php } foreach($list['rows'] as $r) { ?>
<article class="mw-card"><h2><?=H::escape($r['label'])?></h2><p>订单 <?=H::escape($r['trade_no'])?> · 商户 #<?=$r['uid']?> · ¥<?=H::escape($r['money'])?> · <?=$r['tid']==3?'测试订单':'业务订单'?></p><p>创建于 <?=H::escape($r['addtime'])?><?php if($r['provider_id']) { ?> · 网关单号 <?=H::escape($r['provider_id'])?><?php } ?></p>
<?php if($r['notification']) { ?><p>最近通知：<?=H::escape($r['notification']['created_at'])?> · HTTP <?=H::escape($r['notification']['http_code'])?> · <?=H::escape($r['notification']['response_summary'])?></p><?php } ?>
<details><summary>登记核对依据或继续处理</summary><form method="post">
<input type="hidden" name="trade_no" value="<?=H::escape($r['trade_no'])?>"><input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(16))?>"><input type="hidden" name="csrf" value="<?=H::escape($_SESSION[$tokenKey])?>">
<label>操作 <select name="action"><option value="note">登记核对依据</option><?php if($r['provider_id']) { ?><option value="inspect">查询原 BEpusdt 网关</option><?php } if($r['status']==1 && $r['tid']==0 && $r['notify']!=0) { ?><option value="retry">重试业务通知</option><?php } ?></select></label>
<label>核对原因 <textarea name="reason" required minlength="5" maxlength="500" rows="2" placeholder="记录核对结果，不填写密钥、Token 或完整回调地址"></textarea></label><label>凭证编号 <input name="reference" maxlength="200" placeholder="选填：工单号、支付机构凭证号"></label><button class="btn btn-primary">提交处理</button></form></details>
<?php if($r['history']) { ?><h3>最近 5 次核对</h3><?php } foreach($r['history'] as $h) { ?><p><?=H::escape($h['created_at'].' · '.$h['actor'].' · '.(['note'=>'登记依据','inspect'=>'查询网关','retry'=>'重试通知'][$h['action']]??$h['action']))?><br><?=H::escape($h['reason'])?><?php if($h['reference']) { ?> · 凭证 <?=H::escape($h['reference'])?><?php } ?><br><?=H::escape($h['result']?:'处理中；如长时间未完成，请人工核对后再操作')?></p><?php } ?></article>
<?php } ?><nav class="mw-toolbar" aria-label="异常订单分页"><?php if($list['page']>1) { ?><a href="?<?=H::escape(http_build_query(['page'=>$list['page']-1,'trade'=>$list['trade']]))?>">上一页</a><?php } ?>第 <?=$list['page']?> 页<?php if($list['more']) { ?><a href="?<?=H::escape(http_build_query(['page'=>$list['page']+1,'trade'=>$list['trade']]))?>">下一页</a><?php } ?></nav><?php } ?></main>
<?php if (!$reviewAdmin) { ?></div></div><?php include ROOT.'user/foot.php'; } else { ?></body></html><?php } ?>
