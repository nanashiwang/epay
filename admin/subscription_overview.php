<?php
require '../includes/common.php';
if ($islogin!=1) { header('Location: login.php'); exit; }
header('Cache-Control: no-store');
use lib\HelpCenter as H;
$title='订阅运营看板';include './head.php';
$error='';$report=null;
try {$report=\lib\SubscriptionDashboard::report($DB,$_GET['from']??date('Y-m-01'),$_GET['to']??date('Y-m-d'),$_GET['page']??1);}
catch (InvalidArgumentException $e) {$error=$e->getMessage();}
catch (Throwable $e) {$error='暂时无法读取统计，请稍后重试并检查数据库迁移。';}
?>
<link rel="stylesheet" href="/user/assets/css/merchant-workspace.css">
<main class="container merchant-workspace admin-subscriptions">
<header class="mw-heading"><div><p class="mw-eyebrow">商户订阅</p><h1>订阅运营看板</h1><p>关注套餐成交、续购与当前服务状态。</p></div><a href="subscriptions.php">处理套餐异常 →</a></header>
<form class="mw-toolbar" method="get"><label>开始日期 <input type="date" name="from" value="<?=H::escape($report['from']??date('Y-m-01'))?>" required></label><label>结束日期 <input type="date" name="to" value="<?=H::escape($report['to']??date('Y-m-d'))?>" required></label><button class="btn btn-primary">查询</button><a href="subscription_overview.php">本月</a></form>
<?php if ($error) { ?><p class="mw-notice" role="alert"><?=H::escape($error)?></p><?php } else { $s=$report['summary']; ?>
<section class="mw-stats" aria-label="所选时间段的套餐成交">
<?php foreach(['套餐成交额'=>'¥'.number_format($s['amount'],2),'外部支付确认额'=>'¥'.number_format($s['external_amount'],2),'余额支付金额'=>'¥'.number_format($s['balance_amount'],2),'续购订单'=>$report['renewals'].' 笔'] as $label=>$value) { ?><article class="mw-card"><span><?=H::escape($label)?></span><strong><?=H::escape($value)?></strong><small>所选日期 · <?=$s['purchases']?> 笔已付款套餐订单</small></article><?php } ?>
</section><p class="mw-notice">按套餐付款时间统计当前状态为已付款的订单，排除测试、商户业务流水及已标记退款的订单。续购指同一商户已有更早的已付款套餐订单。外部支付确认额依据系统付款记录，不代表支付机构已结算；线下退款需另行核对。</p>
<section class="mw-stats" aria-label="当前权益状态">
<?php foreach(['权益有效商户'=>$report['members']['active'],'7 天内到期'=>$report['members']['expiring'],'已到期商户'=>$report['members']['expired'],'待处理套餐付款'=>$report['review']] as $label=>$value) { ?><article class="mw-card"><span><?=H::escape($label)?></span><strong><?=H::escape($value)?></strong><small>当前状态 · 不受日期筛选影响</small></article><?php } ?>
</section><div class="mw-grid"><section class="mw-card"><h2>每日成交</h2><?php if (!$report['daily']) { ?><p>所选日期暂无套餐成交。</p><?php } foreach($report['daily'] as $r) { ?><p><?=H::escape($r['day'])?> · <?=$r['purchases']?> 笔 · ¥<?=H::escape(number_format($r['amount'],2))?></p><?php } ?></section><section class="mw-card"><h2>按套餐汇总</h2><?php if (!$report['plans']) { ?><p>暂无记录。</p><?php } foreach($report['plans'] as $r) { ?><p><?=H::escape($r['name'])?>（#<?=$r['gid']?>） · <?=$r['purchases']?> 笔 · ¥<?=H::escape(number_format($r['amount'],2))?></p><?php } ?></section></div>
<section class="mw-card"><h2>7 天内到期商户</h2><p>统计正常账号的自助收款套餐有效期；实际收款仍需审核、认证及通道可用。</p><?php if (!$report['expiring']) { ?><p>本页暂无即将到期商户。</p><?php } foreach($report['expiring'] as $r) { ?><p>商户 #<?=$r['uid']?> · 套餐 #<?=$r['gid']?> · <?=H::escape($r['endtime'])?> 到期</p><?php } ?>
<nav class="mw-toolbar" aria-label="到期商户分页"><?php $query=['from'=>$report['from'],'to'=>$report['to']]; if($report['page']>1) { ?><a href="?<?=H::escape(http_build_query($query+['page'=>$report['page']-1]))?>">上一页</a><?php } ?>第 <?=$report['page']?> 页<?php if($report['more']) { ?><a href="?<?=H::escape(http_build_query($query+['page'=>$report['page']+1]))?>">下一页</a><?php } ?></nav></section>
<?php } ?></main></body></html>
