<?php
require '../includes/common.php';
if ($islogin!=1) { header('Location: login.php'); exit; }
$_SESSION['subscription_admin_csrf']=$_SESSION['subscription_admin_csrf']??bin2hex(random_bytes(24));
$title='套餐异常处理'; include './head.php';
?>
<link rel="stylesheet" href="/user/assets/css/merchant-workspace.css">
<main class="container merchant-workspace admin-subscriptions" id="subscription-admin" data-csrf="<?=htmlspecialchars($_SESSION['subscription_admin_csrf'],ENT_QUOTES,'UTF-8')?>">
<header class="mw-heading"><div><p class="mw-eyebrow">商户订阅</p><h1>套餐异常处理</h1><p>核对已付款但尚未开通的套餐，并保留每次处置依据。</p></div><a href="/index.php?doc=help&amp;topic=admin_plans">查看套餐说明 →</a></header>
<p><a href="subscription_overview.php">查看订阅运营看板 →</a></p>
<p class="mw-notice">开通不同套餐会替换当前权益，剩余价值不自动折算；请先与商户确认。登记线下处理只保存结果，不会执行退款。</p>
<div class="mw-toolbar"><label>记录范围 <select id="sa-state"><option value="review">待处理</option><option value="resolved">已处理</option></select></label><button type="button" id="sa-refresh" class="btn btn-default">刷新</button></div>
<p id="sa-message" role="status" aria-live="polite"></p><div id="sa-records">正在读取…</div>
<nav class="mw-toolbar" aria-label="分页"><button id="sa-prev" class="btn btn-default" disabled>上一页</button><span id="sa-page"></span><button id="sa-next" class="btn btn-default" disabled>下一页</button></nav>
<dialog id="sa-dialog"><form id="sa-form"><h2>处理套餐付款</h2><p id="sa-summary"></p><p class="mw-notice" id="sa-effect"></p><input name="trade_no" type="hidden"><input name="version" type="hidden"><label>处理方式<select name="action"><option value="apply">开通所购套餐</option><option value="close">登记已完成的线下处理</option></select></label><label>处理原因<textarea name="reason" required minlength="5" maxlength="500" rows="3" placeholder="记录与商户确认的处理依据，不填写任何密钥"></textarea></label><label>线下处理凭证编号<input name="reference" maxlength="200" placeholder="登记线下处理时必填"></label><label class="mw-check"><input type="checkbox" name="confirmed" value="1" required>我已核对订单及当前权益，并确认上述处理影响</label><p id="sa-error" role="alert"></p><div class="mw-toolbar"><button type="button" id="sa-cancel" class="btn btn-default">取消</button><button type="submit" class="btn btn-primary">确认处理</button></div></form></dialog>
</main><script src="/assets/js/subscriptions-admin.js"></script></body></html>
