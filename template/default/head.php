<?php
if(!defined('IN_CRONLITE'))exit();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"/>
<title><?php echo $conf['title']?></title>
<meta name="keywords" content="<?php echo $conf['keywords']?>">
<meta name="description" content="<?php echo $conf['description']?>" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5"/>
<meta name="renderer" content="webkit">
<link rel="stylesheet" href="<?php echo STATIC_ROOT?>css/modern.css">
<script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
</head>
<body class="modern-theme">

<!-- Navigation -->
<nav class="modern-nav">
    <div class="nav-inner">
        <a href="/" class="nav-logo">
            <img src="/assets/img/logo.png" alt="<?php echo $conf['sitename']?>">
        </a>
        <button class="nav-toggle" aria-label="Toggle menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <ul class="nav-links">
            <li><a href="/">首页</a></li>
            <li><a href="doc.html">开发文档</a></li>
            <?php if($conf['test_open']){?>
            <li><a href="/user/test.php">支付测试</a></li>
            <?php }?>
            <li><a href="/user/">用户中心</a></li>
            <li><a href="/user/reg.php" class="nav-cta">注册商户</a></li>
        </ul>
    </div>
</nav>
