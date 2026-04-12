<?php
if(!defined('IN_CRONLITE'))exit();
?>

<!-- Footer -->
<footer class="modern-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-brand">
                <img src="/assets/img/logo.png" alt="<?php echo $conf['sitename']?>" style="height:36px;filter:brightness(0) invert(1);opacity:0.8;">
                <p><?php echo $conf['sitename']?>是<?php echo $conf['orgname']?>旗下的免签约支付产品，为商户提供安全、便捷的聚合支付服务。</p>
            </div>
            <div class="footer-col">
                <h4>产品</h4>
                <ul>
                    <li><a href="doc.html">开发文档</a></li>
                    <li><a href="agreement.html">服务条款</a></li>
                    <?php if($conf['test_open']){?>
                    <li><a href="/user/test.php">支付测试</a></li>
                    <?php }?>
                </ul>
            </div>
            <div class="footer-col">
                <h4>联系我们</h4>
                <ul>
                    <?php if($conf['kfqq']){?>
                    <li><a href="https://wpa.qq.com/msgrd?v=3&uin=<?php echo $conf['kfqq']?>&Site=pay&Menu=yes" target="_blank">QQ: <?php echo $conf['kfqq']?></a></li>
                    <?php }?>
                    <?php if($conf['email']){?>
                    <li><a href="mailto:<?php echo $conf['email']?>">Email: <?php echo $conf['email']?></a></li>
                    <?php }?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p><?php echo $conf['sitename']?>&nbsp;&copy;&nbsp;<?php echo date("Y")?>&nbsp;All Rights Reserved.&nbsp;&nbsp;<?php echo $conf['footer']?></p>
        </div>
    </div>
</footer>

</body>
</html>
