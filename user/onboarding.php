<?php
require '../includes/common.php';
if ($islogin2!=1) { header('Location: ./login.php'); exit; }
header('Cache-Control: no-store');
$title='开通收款指南';include './head.php';
use lib\HelpCenter as H;
$guideError=false;
try {$guide=\lib\MerchantOnboarding::progress($DB,$uid);} catch (Throwable $e) {$guideError=true;}
?>
<link rel="stylesheet" href="/user/assets/css/merchant-workspace.css">
<div id="content" class="app-content" role="main"><div class="app-content-body"><main class="merchant-workspace">
<header class="mw-heading"><div><p class="mw-eyebrow">开通收款指南</p><h1>用自己的支付账户开始收款</h1><p>按月购买软件服务，自行管理支付宝、微信或 USDT 等通道。</p></div><a href="index.php">返回工作台</a></header>
<?php if ($guideError) { ?><p class="mw-notice" role="alert">暂时无法读取开通进度，请稍后刷新；持续失败请联系管理员检查数据迁移。</p><a href="onboarding.php">重新加载</a><?php } else { ?>
<?php if (empty($conf['group_buy'])) { ?><p class="mw-notice">套餐购买暂未开放。已有权益仍按原到期时间生效，请联系管理员了解开放时间。</p><?php } ?>
<?php if ((int)$guide['u']['status']!==1 || (int)$guide['u']['pay']!==1 || (!empty($conf['cert_force']) && !$guide['u']['cert'])) { ?><p class="mw-notice">收款需要完成账号审核及站点要求的实名认证。<a href="certificate.php">检查实名认证</a> · <a href="userinfo.php">查看账户资料</a></p><?php } ?>
<section class="mw-card"><h2>你的开通进度</h2><ol class="mw-steps">
<li><b>1. 选择包月套餐</b><span><?=$guide['policy']['active']?'已开通 · '.H::escape($guide['g']['name']).' · '.H::escape($guide['u']['endtime']).' 到期':'尚未开通或已到期'?></span><a href="groupbuy.php">查看套餐与价格</a></li>
<li><b>2. 配置自己的账号</b><span>已添加 <?=$guide['added']?> 个账号；密钥由你保管和配置</span><a href="<?=!empty($conf['merchant_channels'])?'channels.php':'bepusdt.php'?>">配置收款通道</a></li>
<li><b>3. 测试、启用与默认路由</b><span><?=$guide['tested']?> 个账号已通过测试；<?=count($guide['types'])?> 种支付方式当前可用。修改密钥后需重新测试</span><a href="/index.php?doc=help&amp;topic=testing">查看测试步骤</a></li>
<li><b>4. 接入你的业务网站</b><span><?=$guide['integrated']?'已记录业务订单到账与通知成功':'尚未记录业务订单通知成功；请核对金额、订单号和网站发货结果'?></span><a href="userinfo.php?mod=api">获取 API 接入信息</a></li>
</ol></section>
<div class="mw-grid"><section class="mw-card"><h2>开通前准备</h2><p>准备支付机构的商户号、应用参数或自建 BEpusdt 网关地址。套餐决定可用通道和账号数量，支付机构另有签约要求与费率。</p><a href="/index.php?doc=help&amp;topic=choose">选择适合的接入方式 →</a></section><section class="mw-card"><h2>怎么确认已经可用</h2><p>保存成功只表示配置已保存。请完成通道测试，启用并选择默认账号，再通过业务网站验收订单和通知。测试付款由你操作。</p><a href="order.php">查看订单记录 →</a> · <a href="onboarding.php">刷新开通进度</a></section></div>
<?php } ?></main></div></div>
<?php include './foot.php'; ?>
