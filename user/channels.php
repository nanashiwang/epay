<?php
require '../includes/common.php';
if ($islogin2!=1) { header('Location: ./login.php'); exit; }
header('Cache-Control: no-store');
if (empty($_SESSION['channels_csrf'])) $_SESSION['channels_csrf']=bin2hex(random_bytes(24));
$title='支付通道'; include './head.php';
?>
<link rel="stylesheet" href="assets/css/collection.css">
<link rel="stylesheet" href="/user/assets/css/merchant-workspace.css">
<div id="content" class="app-content" role="main"><div class="app-content-body"><main class="collection" id="merchant-channels" data-csrf="<?=htmlspecialchars($_SESSION['channels_csrf'],ENT_QUOTES,'UTF-8')?>">
<header class="collection-header"><div><div class="collection-eyebrow">我的收款配置</div><h1>支付通道</h1><p>使用自己的支付账号收款，由你管理密钥和默认通道。</p></div><button class="btn btn-primary" id="mc-add" disabled>＋ 添加支付通道</button></header>
<nav class="collection-tabs" aria-label="收款方式"><a class="btn btn-primary" href="channels.php" aria-current="page">支付通道</a><?php if(!empty($conf['collection_parent'])){?><a class="btn btn-default" href="collection.php">支付宝收款码</a><?php } if(!empty($conf['bepusdt_parent'])){?><a class="btn btn-default" href="bepusdt.php">USDT / BEpusdt</a><?php } ?><a class="btn btn-default" href="groupbuy.php">我的套餐</a></nav>
<p class="collection-help"><a href="/index.php?doc=help&amp;topic=choose">查看配置教程与常见问题 →</a></p>
<div id="mc-plan" class="collection-notice">正在读取套餐…</div>
<div class="collection-notice">包月套餐收取软件使用费，不收按笔平台服务费；支付机构自己的费率仍按你的签约执行。保存配置 → 测试到账 → 启用 → 设为默认。每种支付方式可选择一个默认账号，未配置的方式不会使用平台收款账户。</div>
<div id="mc-message" role="status" aria-live="polite" hidden></div>
<nav class="collection-tabs" aria-label="支付管理"><button data-mc-tab="accounts" class="selected">我的通道</button><button data-mc-tab="orders">支付订单</button><button data-mc-tab="notify">通知记录</button><button data-mc-tab="audit">操作记录</button><button id="mc-refresh">刷新状态</button></nav>
<section id="mc-accounts" class="collection-grid"><p>正在加载支付通道…</p></section>
<section id="mc-record-view" hidden><div id="mc-records" class="collection-table"></div><div class="collection-pagination"><button id="mc-prev" class="btn btn-default">上一页</button><span id="mc-page"></span><button id="mc-next" class="btn btn-default">下一页</button></div></section>
<dialog id="mc-dialog" class="collection-dialog"><form id="mc-form" autocomplete="off"><div class="collection-dialog-title"><h2 id="mc-title">添加支付通道</h2><button type="button" id="mc-close" class="collection-close" aria-label="关闭">×</button></div>
<input name="id" type="hidden" value="0"><label>通道名称<input name="name" required maxlength="30" placeholder="例如：我的支付宝"></label>
<label>接入方式<select name="plugin" id="mc-plugin" required></select></label><p id="mc-help" class="collection-help"></p>
<label>支付方式<select name="type" id="mc-type" required></select></label><div id="mc-fields"></div><fieldset id="mc-modes"><legend>已签约的收款产品</legend></fieldset>
<p class="collection-help">密钥加密保存，保存后不回显，编辑时留空保留。修改配置需要重新测试。退款请在自己的支付机构后台处理。</p><div id="mc-form-error" role="alert"></div><div class="collection-actions"><button type="button" id="mc-cancel" class="btn btn-default">取消</button><button type="submit" class="btn btn-primary">保存配置</button></div>
</form></dialog>
<dialog id="mc-test-dialog" class="collection-dialog"><form id="mc-test-form"><div class="collection-dialog-title"><h2>创建收款测试</h2><button type="button" id="mc-test-close" class="collection-close" aria-label="关闭">×</button></div><input name="id" type="hidden"><label>测试金额（元）<input name="amount" type="number" min="0.01" max="100" step="0.01" value="0.01" required></label><p>创建后由你打开收银台并付款；收到支付机构回调后才能启用此配置。</p><div id="mc-test-error" role="alert"></div><button type="submit" class="btn btn-primary">创建测试订单</button></form></dialog>
</main></div></div>
<?php include './foot.php'; ?>
<script src="assets/js/channels.js"></script>
