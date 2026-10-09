<?php
require '../includes/common.php';
if ($islogin2!=1) { header('Location: ./login.php'); exit; }
header('Cache-Control: no-store');
if (empty($_SESSION['collection_csrf'])) $_SESSION['collection_csrf']=bin2hex(random_bytes(24));
$title='收款账号';
include './head.php';
?>
<link rel="stylesheet" href="assets/css/collection.css">
<div id="content" class="app-content" role="main"><div class="app-content-body"><main class="collection" id="collection" data-csrf="<?=htmlspecialchars($_SESSION['collection_csrf'],ENT_QUOTES,'UTF-8')?>">
  <header class="collection-header"><div><div class="collection-eyebrow">支付宝 · 商家账单</div><h1>收款账号</h1><p>资金直接进入你的支付宝，到账与订单在这里核对。</p></div><button class="btn btn-primary" id="add-account">＋ 添加收款账号</button></header>
  <?php if(!empty($_GET['paid'])) { $paid=$DB->getRow('SELECT status FROM pre_order WHERE trade_no=:trade AND uid=:uid AND tid=3',[':trade'=>(string)$_GET['paid'],':uid'=>$uid]); if($paid && (int)$paid['status']===1) { ?><div class="collection-notice good">测试订单已到账。业务站的异步通知仍需使用真实业务订单验证。</div><?php }} ?>
  <div class="collection-notice">先添加账号并校验接口 → 启用监测 → 测试收款 → 设为默认。未设置默认时，订单沿用平台通道。直收本金不计入平台可提现余额。</div>
  <div id="collection-message" role="status" aria-live="polite" hidden></div>
  <nav class="collection-tabs" aria-label="收款管理"><button class="selected" data-tab="accounts">收款账号</button><button data-tab="receipt">到账流水</button><button data-tab="notify">通知记录</button><button data-tab="audit">操作记录</button></nav>
  <?php if (!empty($conf['bepusdt_parent'])) { ?><nav class="collection-tabs"><a class="btn btn-default" href="collection.php">支付宝收款</a><a class="btn btn-default" href="bepusdt.php">USDT 收款</a><a class="btn btn-default" href="groupbuy.php">我的套餐</a></nav><?php } ?>
<section id="account-view"><div class="collection-stats" id="account-stats"></div><div id="accounts" class="collection-grid" aria-live="polite"><p>正在加载收款账号…</p></div></section>
  <section id="record-view" hidden><form id="record-filter" class="collection-filter"><label>账号<select id="filter-account"><option value="">全部账号</option></select></label><label>系统订单号<input id="filter-trade" placeholder="输入完整系统订单号"></label><label id="filter-state-wrap">匹配状态<select id="filter-state"><option value="">全部状态</option><option value="unmatched">未匹配</option><option value="ambiguous">金额歧义</option><option value="late">超出自动确认窗口</option><option value="matched">已匹配</option></select></label><button class="btn btn-default">查询</button></form><p class="collection-help" id="record-help"></p><div class="collection-table" id="records"></div><div class="collection-pagination"><button class="btn btn-default" id="prev-page">上一页</button><span id="page-label"></span><button class="btn btn-default" id="next-page">下一页</button></div></section>
  <dialog id="account-dialog" class="collection-dialog"><form id="account-form" autocomplete="off"><div class="collection-dialog-title"><h2 id="form-title">添加收款账号</h2><button type="button" class="collection-close" id="close-dialog" aria-label="关闭">×</button></div><p>使用已上线应用的账单查询权限。密钥保存后不回显，编辑时留空保留原值。</p><input type="hidden" name="id" value="0">
    <label>账号名称<input name="name" required maxlength="30" placeholder="例如：企业支付宝"></label>
    <label>支付宝 UID<input name="alipay_uid" required pattern="2088[0-9]{12}" placeholder="2088 开头的 16 位账号 UID"></label>
    <label>原生收款码地址<input name="qr_url" required type="url" placeholder="https://qr.alipay.com/…"></label>
    <div class="collection-upload"><label class="btn btn-default">解析二维码图片<input type="file" id="qr-file" accept="image/png,image/jpeg,image/webp" hidden></label><span id="qr-hint">图片仅用于解析，不保存到相册或公开目录</span></div>
    <label>应用 APPID<input name="appid" required pattern="20[0-9]{14}" placeholder="20 开头的 16 位应用 APPID"></label>
    <label>应用私钥<textarea name="appsecret" rows="3" spellcheck="false" placeholder="粘贴 RSA2 应用私钥；已保存时留空保留"></textarea></label>
    <label>支付宝公钥<textarea name="appkey" rows="3" spellcheck="false" placeholder="粘贴开放平台返回的支付宝公钥"></textarea></label>
    <div class="collection-notice">扫码后请核对收款方，并按收银台金额付款。无备注收入只在金额和付款时间唯一匹配时确认；同一码在其他网站同时使用会影响识别。</div>
    <div id="form-error" role="alert"></div><div class="collection-actions"><button type="button" class="btn btn-default" id="cancel-dialog">取消</button><button type="submit" class="btn btn-primary">保存配置</button></div>
  </form></dialog>
</main></div></div>
<?php include './foot.php'; ?>
<script src="assets/js/collection.js"></script>
