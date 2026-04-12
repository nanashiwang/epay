<?php
if(!defined('IN_CRONLITE'))exit();
require INDEX_ROOT.'head.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-glow"></div>
    <div class="hero-inner">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="badge-dot"></span>
                <span>安全稳定的聚合支付平台</span>
            </div>
            <h1 class="hero-title">
                欢迎使用<br><span class="gradient-text"><?php echo $conf['sitename']?></span>
            </h1>
            <p class="hero-subtitle">提供免签约支付宝、微信支付、QQ钱包等多渠道聚合支付解决方案，费率低、到账快、接入简单。</p>
            <div class="hero-buttons">
                <a href="/user/" class="btn-glow btn-glow-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M13.8 12H3"/></svg>
                    登录商户
                </a>
                <a href="/user/reg.php" class="btn-glow btn-glow-outline">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    注册商户
                </a>
            </div>
        </div>
        <div class="hero-image">
            <img src="<?php echo STATIC_ROOT?>images/banner4.png" alt="<?php echo $conf['sitename']?>">
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="stats-grid stagger-children fade-up">
        <div class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="stat-number" data-count="1000000">1,000,000+</div>
            <div class="stat-label">累计交易笔数</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <div class="stat-number" data-count="5000">5,000+</div>
            <div class="stat-label">注册商户数</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-number">99.9%</div>
            <div class="stat-label">支付成功率</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            </div>
            <div class="stat-number">10+</div>
            <div class="stat-label">支持支付方式</div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="section-header fade-up">
        <div class="section-tag">Core Features</div>
        <h2><?php echo $conf['sitename']?> 免签约支付产品</h2>
        <p>为您提供安全、高效、便捷的聚合支付解决方案</p>
    </div>
    <div class="features-grid stagger-children fade-up">
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <h3>多种支付方式</h3>
            <p>支持支付宝、微信支付、QQ钱包、财付通等主流支付渠道，一站式接入，满足多场景需求。</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <h3>对接费率超低</h3>
            <p>每笔交易手续费低至 2%，远低于行业平均水平，帮助商户降低运营成本，提升利润空间。</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
            <h3>无需自主提现</h3>
            <p>满足一定金额即可自动结算到您的账户，省去手动提现的繁琐，资金到账快速安全。</p>
        </div>
    </div>
</section>

<!-- Steps Section -->
<section class="steps-section">
    <div class="section-header fade-up">
        <div class="section-tag">How It Works</div>
        <h2>四步快速接入</h2>
        <p>简单几步即可完成支付接口对接，快速上线收款</p>
    </div>
    <div class="steps-container stagger-children fade-up">
        <div class="step-item">
            <div class="step-number">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            </div>
            <div class="step-num-label">STEP 01</div>
            <h4>注册账号</h4>
            <p>注册并登录商户中心</p>
        </div>
        <div class="step-item">
            <div class="step-number">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </div>
            <div class="step-num-label">STEP 02</div>
            <h4>配置接口</h4>
            <p>获取商户ID和密钥</p>
        </div>
        <div class="step-item">
            <div class="step-number">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="step-num-label">STEP 03</div>
            <h4>接入测试</h4>
            <p>对接API并完成调试</p>
        </div>
        <div class="step-item">
            <div class="step-number">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="step-num-label">STEP 04</div>
            <h4>上线运营</h4>
            <p>正式开启收款之旅</p>
        </div>
    </div>
</section>

<!-- Partners Section -->
<section class="partners-section">
    <div class="section-header fade-up">
        <div class="section-tag">Partners</div>
        <h2>平台合作伙伴</h2>
        <p>携手行业领先支付平台，为商户提供稳定可靠的支付服务</p>
    </div>
    <div class="partners-grid fade-up">
        <div class="partner-item">
            <img src="<?php echo STATIC_ROOT?>images/alipay.png" alt="支付宝">
        </div>
        <div class="partner-item">
            <img src="<?php echo STATIC_ROOT?>images/wxpay.png" alt="微信支付">
        </div>
        <div class="partner-item">
            <img src="<?php echo STATIC_ROOT?>images/qqpay.png" alt="QQ钱包">
        </div>
        <div class="partner-item">
            <img src="<?php echo STATIC_ROOT?>images/tenpay.png" alt="财付通">
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="section-header fade-up">
        <div class="section-tag">FAQ</div>
        <h2>常见问题</h2>
        <p>关于接入和使用的常见疑问</p>
    </div>
    <div class="faq-container fade-up">
        <div class="faq-item">
            <div class="faq-question">
                <h4>如何注册成为商户？</h4>
                <span class="faq-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-inner">点击首页"注册商户"按钮，填写相关信息完成注册。注册成功后即可登录商户中心，获取商户ID和密钥，开始接入支付接口。</div>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question">
                <h4>支持哪些支付方式？</h4>
                <span class="faq-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-inner">目前支持支付宝、微信支付、QQ钱包、财付通等多种主流支付方式，覆盖绝大多数用户的支付需求。具体可用支付方式以商户中心显示为准。</div>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question">
                <h4>接入需要多长时间？</h4>
                <span class="faq-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-inner">我们提供完善的开发文档和 SDK，有开发经验的技术人员通常只需 30 分钟即可完成对接。如遇问题，可通过在线客服获取技术支持。</div>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question">
                <h4>交易手续费是多少？</h4>
                <span class="faq-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-inner">手续费低至 2%，具体费率根据商户类型和交易量可能有所不同。注册商户后可在商户中心查看详细的费率信息。高交易量商户可联系客服协商更优费率。</div>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question">
                <h4>资金结算周期是多久？</h4>
                <span class="faq-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
            <div class="faq-answer">
                <div class="faq-answer-inner">满足一定金额后系统会自动结算到您绑定的账户，无需手动申请提现。具体结算规则请参阅商户中心的结算设置。</div>
            </div>
        </div>
    </div>
</section>

<script>
// Scroll animations
(function() {
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('.fade-up').forEach(function(el) {
        observer.observe(el);
    });
})();

// FAQ toggle
$(document).on('click', '.faq-question', function() {
    var item = $(this).closest('.faq-item');
    item.toggleClass('active');
    item.siblings('.faq-item').removeClass('active');
});

// Navbar scroll effect
$(window).on('scroll', function() {
    if ($(this).scrollTop() > 50) {
        $('.modern-nav').addClass('scrolled');
    } else {
        $('.modern-nav').removeClass('scrolled');
    }
});

// Mobile nav toggle
$(document).on('click', '.nav-toggle', function() {
    $('.nav-links').toggleClass('active');
});
</script>

<?php require INDEX_ROOT.'foot.php';?>
