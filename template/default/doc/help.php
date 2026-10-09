<?php
if (!defined('IN_CRONLITE')) exit;
use lib\HelpCenter as Help;
$catalog=Help::catalog();
$topic=$_GET['topic']??'';$query=$_GET['q']??'';
$badQuery=!is_string($query) || !mb_check_encoding($query,'UTF-8') || mb_strlen($query)>120;
if ($badQuery) { http_response_code(400);$query=''; }
$article=$topic===''?null:Help::article($topic);
$notFound=$topic!=='' && !$article;
if ($notFound) http_response_code(404);
$results=$query!==''?Help::search($query):$catalog['articles'];
$title=$notFound?'文章不存在':($article?$article['title']:($query!==''?'搜索帮助':'帮助中心'));
$rendered=$article?Help::render($article['body']):null;
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark"><title><?=Help::escape($title)?> · <?=Help::escape($conf['sitename'])?></title><link rel="stylesheet" href="/assets/help/help.css"></head><body>
<a class="help-skip" href="#help-content">跳到正文</a>
<header class="help-header"><a class="help-brand" href="<?=Help::escape(Help::url())?>"><?=Help::escape($conf['sitename'])?><span>帮助中心</span></a><nav aria-label="主导航"><a href="/doc/index.html">API 开发文档</a><a href="/user/">商户中心</a><a href="/">首页</a></nav></header>
<div class="help-shell"><aside><form class="help-search" action="/index.php" method="get" role="search"><input type="hidden" name="doc" value="help"><label for="help-query">搜索操作步骤与问题</label><div><input type="search" id="help-query" name="q" maxlength="120" value="<?=Help::escape($query)?>" placeholder="例如：微信 公钥、回调、套餐"><button type="submit">搜索</button></div></form>
<details class="help-nav" id="help-navigation"><summary>文章目录</summary><nav aria-label="帮助文章"><a class="help-overview" href="<?=Help::escape(Help::url())?>">全部帮助</a><?php foreach ($catalog['categories'] as $category) { ?><p class="help-category"><?=Help::escape($category)?></p><?php foreach ($catalog['articles'] as $item) { if ($item['category']!==$category) continue; ?><a href="<?=Help::escape(Help::url($item['id']))?>" <?=$topic===$item['id']?'aria-current="page"':''?>><?=Help::escape($item['title'])?></a><?php } } ?></nav></details></aside>
<main id="help-content" tabindex="-1">
<?php if ($badQuery) { ?><div class="help-notice" role="alert">搜索词格式不正确，请输入不超过 120 个字的关键词。</div><?php } ?>
<?php if ($notFound) { ?><div class="help-empty"><p class="help-eyebrow">404</p><h1>这篇帮助文章不存在</h1><p>链接可能不完整，请从目录选择文章或重新搜索。</p><a class="help-button" href="<?=Help::escape(Help::url())?>">返回帮助中心</a></div>
<?php } elseif ($article) { ?>
<p class="help-eyebrow"><?=Help::escape($article['category'])?></p><h1><?=Help::escape($article['title'])?></h1><p class="help-lead"><?=Help::escape($article['summary'])?></p><p class="help-meta">核对日期：<?=Help::escape($catalog['reviewed_at'])?> · 适用于商户包月自配模式</p>
<?php if ($rendered['toc']) { ?><details class="help-toc" open><summary>本页内容</summary><ul><?php foreach ($rendered['toc'] as $section) { ?><li><a href="#<?=$section['id']?>"><?=Help::escape($section['title'])?></a></li><?php } ?></ul></details><?php } ?>
<article class="help-article"><?=$rendered['html']?></article>
<nav class="help-adjacent" aria-label="相邻文章"><?php $ids=array_column($catalog['articles'],'id');$current=array_search($topic,$ids,true);foreach ([-1=>'上一篇',1=>'下一篇'] as $delta=>$label) { if (!isset($catalog['articles'][$current+$delta])) continue;$adj=$catalog['articles'][$current+$delta]; ?><a href="<?=Help::escape(Help::url($adj['id']))?>"><span><?=$label?></span><?=Help::escape($adj['title'])?></a><?php } ?></nav>
<?php } else { ?>
<p class="help-eyebrow">从配置到到账</p><h1><?=$query!==''?'搜索帮助':'让收款配置有章可循'?></h1><p class="help-lead"><?=$query!==''?'搜索“'.Help::escape($query).'”，找到 '.count($results).' 篇文章。':'选择套餐、配置自己的支付账户，完成到账与业务通知验收。'?></p>
<?php if ($query==='') { ?><div class="help-start"><a href="<?=Help::escape(Help::url('start'))?>"><b>01 开始使用</b><span>准备账户与套餐</span></a><a href="<?=Help::escape(Help::url('choose'))?>"><b>02 配置支付</b><span>按自己的产品填写</span></a><a href="<?=Help::escape(Help::url('testing'))?>"><b>03 验收到账</b><span>核对订单与通知</span></a></div><p class="help-notice">本地手册按本站功能编写。支付FM的 97 篇参考资料见<a href="<?=Help::escape(Help::url('sources'))?>">来源索引</a>，专属收费、接口和软件请勿直接套用。</p><?php } ?>
<?php if (!$results) { ?><div class="help-empty"><h2>没有找到匹配文章</h2><p>试试“支付宝”“公钥”“到期”或“通知”，多个关键词用空格分隔。</p><a href="<?=Help::escape(Help::url())?>">查看全部文章</a></div><?php } ?>
<?php foreach ($catalog['categories'] as $category) { $group=array_filter($results,static fn($a)=>$a['category']===$category);if (!$group) continue; ?><section class="help-section"><h2><?=Help::escape($category)?></h2><div class="help-cards"><?php foreach ($group as $item) { ?><a class="help-card" href="<?=Help::escape(Help::url($item['id']))?>"><h3><?=Help::escape($item['title'])?></h3><p><?=Help::escape($item['summary'])?></p></a><?php } ?></div></section><?php } ?>
<?php } ?>
<footer class="help-footer">操作以本站实际开通的套餐和支付机构当前要求为准。<a href="<?=Help::escape(Help::url('limitations'))?>">查看适用范围</a> · <a href="<?=Help::escape(Help::url('sources'))?>">来源与核对记录</a></footer>
</main></div><script src="/assets/help/help.js"></script></body></html>
