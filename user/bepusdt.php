<?php
require '../includes/common.php';
if ($islogin2!=1) { header('Location: ./login.php'); exit; }
header('Cache-Control: no-store');
if (empty($_SESSION['bepusdt_csrf'])) $_SESSION['bepusdt_csrf']=bin2hex(random_bytes(24));
$title='USDT / USDC 收款账号'; include './head.php';
?>
<link rel="stylesheet" href="assets/css/collection.css">
<div id="content" class="app-content" role="main"><div class="app-content-body"><main class="collection" id="bepusdt" data-csrf="<?=htmlspecialchars($_SESSION['bepusdt_csrf'],ENT_QUOTES,'UTF-8')?>">
  <header class="collection-header"><div><div class="collection-eyebrow">BEpusdt · USDT / USDC · 多网络</div><h1>稳定币收款账号</h1><p>客户付款直达你的钱包。每个币种与网络单独测试、启用和设为默认，占用一个自助账号名额。</p></div><button class="btn btn-primary" id="bep-add" disabled>＋ 添加网关</button></header>
  <nav class="collection-tabs" aria-label="收款方式"><?php if(!empty($conf['merchant_channels'])){?><a class="btn btn-default" href="channels.php">支付通道</a><?php } ?><a class="btn btn-default" href="collection.php">支付宝收款</a><a class="btn btn-primary" href="bepusdt.php" aria-current="page">USDT / USDC 收款</a><a class="btn btn-default" href="groupbuy.php">我的套餐</a></nav>
  <p class="collection-help"><a href="/index.php?doc=help&amp;topic=bepusdt">查看网关配置、测试与故障排查 →</a></p>
  <div id="bep-plan" class="collection-notice">正在读取套餐…</div>
  <div class="collection-notice">保存配置 → 校验接口 → 测试到账 → 启用并设为默认。收款使用你自己的 BEpusdt；请按收银台显示的网络、地址和精确币额转账。</div>
  <div id="bep-message" role="status" aria-live="polite" hidden></div>
  <nav class="collection-tabs" aria-label="网关管理"><button data-bep-tab="accounts" class="selected">网关账号</button><button data-bep-tab="orders">支付订单</button><button data-bep-tab="notify">通知记录</button><button data-bep-tab="audit">操作记录</button></nav>
  <section id="bep-accounts" class="collection-grid"><p>正在加载网关账号…</p></section>
  <section id="bep-record-view" hidden><div id="bep-records" class="collection-table"></div><div class="collection-pagination"><button id="bep-prev" class="btn btn-default">上一页</button><span id="bep-page"></span><button id="bep-next" class="btn btn-default">下一页</button></div></section>
  <dialog id="bep-dialog" class="collection-dialog"><form id="bep-form" autocomplete="off"><div class="collection-dialog-title"><h2 id="bep-title">添加网关</h2><button type="button" id="bep-close" class="collection-close" aria-label="关闭">×</button></div><input name="id" type="hidden" value="0">
    <label>账号名称<input name="name" required maxlength="30" placeholder="例如：我的 USDT 网关"></label>
    <label>币种与网络<select name="trade_type" required aria-describedby="bep-network-help"></select></label>
    <p id="bep-network-help" class="collection-help">保存后不能修改组合；需要其他币种或网络时新增账号。同一个 BEpusdt 实例可以添加多个组合。</p>
    <label>BEpusdt 网关地址<input name="endpoint" required type="url" placeholder="https://pay.example.com/"></label>
    <label>API Token<input name="token" type="password" autocomplete="new-password" maxlength="256" placeholder="保存后不回显，编辑时留空保留"></label>
    <label>所选网络收款地址（可选）<input name="address" maxlength="128" placeholder="留空使用该网络的网关钱包池"></label>
    <label>付款窗口（秒）<input name="timeout" type="number" min="120" max="3600" value="1200" required></label>
    <p class="collection-help">订单按人民币计价，由网关换算所选稳定币。核对收银台的网络、代币合约、地址及精确数量；同名代币的跨链版本不一定通用。这里只需要网关 Token，不需要钱包私钥或助记词。更换配置后需要重新校验和测试。</p>
    <div id="bep-form-error" role="alert"></div><div class="collection-actions"><button type="button" id="bep-cancel" class="btn btn-default">取消</button><button type="submit" class="btn btn-primary">保存配置</button></div>
  </form></dialog>
</main></div></div>
<?php include './foot.php'; ?>
<script src="assets/js/bepusdt.js"></script>
