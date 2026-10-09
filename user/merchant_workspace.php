<?php
if (!defined('IN_CRONLITE')) exit;
use lib\HelpCenter as H;
$view=\lib\MerchantWorkspace::overview($DB,$uid);
?>
<link rel="stylesheet" href="/user/assets/css/merchant-workspace.css">
<div id="content" class="app-content" role="main"><div class="app-content-body"><main class="merchant-workspace">
<header class="mw-heading"><div><p class="mw-eyebrow">商户工作台</p><h1>今天的收款与服务状态</h1><p>收款进入你自己的支付账户，平台余额用于购买服务。</p></div><a class="btn btn-primary" href="groupbuy.php">查看套餐与续期</a></header>
<?php if ($view['notice']) { ?><p class="mw-notice" role="status"><?=H::escape($view['notice']['text'])?> <a href="groupbuy.php">查看套餐 →</a></p><?php } ?>
<?php if ((int)$userrow['pay']!==1 || (!empty($conf['cert_force']) && !$userrow['cert'])) { ?><p class="mw-notice">商户审核或实名认证尚未完成，请在个人资料中核对。<a href="certificate.php">查看认证状态</a></p><?php } ?>
<section class="mw-stats" aria-label="收款概览">
<article class="mw-card"><span>今日业务收款</span><strong>¥<?=H::escape(number_format((float)$view['stats']['amount'],2))?></strong><small><?=H::escape($view['stats']['paid_orders'])?> 笔已付款业务订单，已排除测试</small></article>
<article class="mw-card"><span>可用支付方式</span><strong><?=count($view['types'])?> 种</strong><small>按当前权益、默认账号及通道状态计算</small></article>
<article class="mw-card"><span>待完成业务通知</span><strong><?=$view['pending']?> 笔</strong><small>包含等待重试及已停止重试的通知</small></article>
<article class="mw-card"><span>当前套餐</span><strong><?=H::escape($view['group']['name']??'默认套餐')?></strong><small><?=H::escape($view['user']['endtime']??'未开通')?> 到期 · <?=$view['used']?> / <?=$view['policy']['limit']?> 个账号</small></article>
</section>
<section class="mw-card"><h2>完成收款配置</h2><a href="onboarding.php">查看完整开通指南与验收进度 →</a><ol class="mw-steps">
<li><b>1. 开通套餐</b><span><?=$view['policy']['active']?'已开通':'未开通或已到期'?></span><a href="groupbuy.php">我的套餐</a></li>
<li><b>2. 添加并测试账号</b><span><?=$view['used']?'已添加 '.$view['used'].' 个账号':'尚未添加'?></span><a href="<?=!empty($conf['merchant_channels'])?'channels.php':'bepusdt.php'?>">配置支付通道</a></li>
<li><b>3. 启用并选择默认</b><span><?=$view['types']?'已可使用：'.H::escape(implode('、',array_column($view['types'],'showname'))):'尚无可用支付方式'?></span><a href="/index.php?doc=help&amp;topic=choose">查看配置步骤</a></li>
<li><b>4. 接入业务网站</b><span>通道测试后，还需验证业务订单和通知</span><a href="/index.php?doc=help&amp;topic=testing">核对完整流程</a></li>
</ol></section>
<div class="mw-grid"><section class="mw-card"><h2>查订单与通知</h2><p>已到账但业务网站未发货时，先查看对应通道的通知记录。</p><div class="mw-toolbar"><a href="order.php">订单记录</a><?php if (!empty($conf['merchant_channels'])) { ?><a href="channels.php">支付通道</a><?php } if (!empty($conf['collection_parent'])) { ?><a href="collection.php">支付宝收款码</a><?php } if (!empty($conf['bepusdt_parent'])) { ?><a href="bepusdt.php">USDT 收款</a><?php } ?></div></section>
<section class="mw-card"><h2>平台账户余额</h2><p>¥<?=H::escape($userrow['money'])?>，与支付机构收到的款项分别记录。</p><div class="mw-toolbar"><a href="record.php">资金明细</a><a href="userinfo.php?mod=api">API 接入信息</a><a href="/index.php?doc=help">帮助中心</a></div></section></div>
</main></div></div>
