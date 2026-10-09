<?php
if (!defined('IN_PLUGIN')) exit;
$escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>支付宝收款</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#1e293b;font:15px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        main{max-width:440px;margin:32px auto;padding:28px 24px;background:#fff;border:1px solid #e2e8f0;border-radius:20px;text-align:center}
        h1{font-size:21px;margin:0 0 18px}.label,.order{color:#64748b;font-size:13px}.amount{font-size:42px;font-weight:700;line-height:1.4;margin:2px 0 8px}
        .copy{border:0;background:none;color:#1677ff;cursor:pointer;font:inherit}.instructions{text-align:left;background:#f0f6ff;border-radius:12px;padding:14px 18px;margin:20px 0}
        .instructions p{margin:4px 0}#qrcode{width:240px;max-width:100%;min-height:240px;margin:20px auto}#qrcode canvas,#qrcode img{max-width:100%;height:auto}
        .button{display:block;width:100%;padding:12px;margin:12px 0;border:0;border-radius:10px;text-decoration:none;background:#1677ff;color:white;font:inherit;cursor:pointer}
        .secondary{background:#edf2f7;color:#334155}#countdown{color:#b45309}#status{min-height:48px;margin:14px 0;color:#475569}.order{overflow-wrap:anywhere;border-top:1px solid #e2e8f0;padding-top:16px}
        [hidden]{display:none!important}.notice{font-size:13px;color:#64748b}@media(max-width:480px){main{margin:12px;border-radius:16px;padding:24px 18px}}
        @media(prefers-color-scheme:dark){body{background:#0f172a;color:#e2e8f0}main{background:#172033;border-color:#334155}.instructions{background:#1e3150}.secondary{background:#334155;color:#e2e8f0}.label,.order,.notice,#status{color:#bac7d8}#countdown{color:#fbbf24}.order{border-color:#334155}}
    </style>
</head>
<body>
<main>
    <h1>支付宝收款</h1>
    <div class="label">请准确支付以下金额</div>
    <div class="amount">¥<?= $escape($order['realmoney']) ?></div>
    <button class="copy" id="copyAmount" type="button">复制金额</button>
    <div id="payment"<?= $paytime <= 0 ? ' hidden' : '' ?>>
        <div class="instructions">
            <p>1. 使用支付宝扫描下方收款码。</p>
            <p>2. 核对收款方，输入上方金额后付款。</p>
            <p>3. 完成后回到此页，系统会自动确认到账。</p>
        </div>
        <div id="qrcode" aria-label="支付宝收款二维码"></div>
        <a id="openAlipay" class="button" hidden>打开支付宝付款</a>
        <div id="countdown" aria-live="off"></div>
    </div>
    <div id="status" role="status" aria-live="polite"><?= $paytime > 0 ? '等待付款' : '订单已结束或已超时，请勿继续付款。' ?></div>
    <button id="checkPayment" class="button secondary" type="button">我已付款，检查结果</button>
    <p class="notice">请勿重复付款。金额不符或付款后未更新，请保留支付宝账单并联系商家核对。</p>
    <div class="order"><?= $escape($order['name']) ?><br>订单号：<?= $escape($order['trade_no']) ?></div>
</main>
<script src="<?= $escape($cdnpublic) ?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?= $escape($cdnpublic) ?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script>
(function () {
    'use strict';
    var codeUrl = <?= json_encode($paytime > 0 ? $code_url : '', $jsonFlags) ?>;
    var tradeNo = <?= json_encode((string)$order['trade_no'], $jsonFlags) ?>;
    var amount = <?= json_encode((string)$order['realmoney'], $jsonFlags) ?>;
    var remaining = <?= (int)$paytime ?>;
    var deadline = Date.now() + remaining * 1000;
    var pollUntil = deadline + 180000;
    var ended = remaining <= 0, paid = false, checking = false, timer;
    var status = document.getElementById('status');
    var payment = document.getElementById('payment');
    var open = document.getElementById('openAlipay');
    var ua = navigator.userAgent;

    function expire() {
        if (ended) return;
        ended = true;
        payment.hidden = true;
        document.getElementById('qrcode').textContent = '';
        open.removeAttribute('href');
        status.textContent = '订单已超时，请勿继续付款。已付款请检查结果或联系商家。';
    }
    function countdown() {
        if (paid) return;
        var seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        if (!seconds) { expire(); return; }
        document.getElementById('countdown').textContent = '请在 ' + Math.floor(seconds / 60) + ' 分 ' + seconds % 60 + ' 秒内付款';
    }
    if (!ended) {
        try {
            jQuery('#qrcode').qrcode({text: codeUrl, width: 240, height: 240, foreground: '#000000', background: '#ffffff'});
        } catch (e) {
            status.textContent = '收款码加载失败，请刷新重试。';
        }
        if (/Android|iPhone|iPad|Mobile/i.test(ua)) {
            open.hidden = false;
            open.href = codeUrl;
            if (/MicroMessenger/i.test(ua)) {
                open.removeAttribute('href');
                open.textContent = '请在浏览器打开后使用支付宝付款';
            }
        }
        countdown();
        setInterval(countdown, 1000);
    }
    open.addEventListener('click', function (event) {
        if (Date.now() >= deadline || paid) { event.preventDefault(); expire(); }
    });
    document.getElementById('copyAmount').addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(amount).then(function () { document.getElementById('copyAmount').textContent = '金额已复制'; }, function () { status.textContent = '请手动输入金额：' + amount; });
        } else { status.textContent = '请手动输入金额：' + amount; }
    });
    function check(manual) {
        if (checking || paid) return;
        clearTimeout(timer);
        checking = true;
        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, 10000);
        fetch('/getshop.php?type=alipay&trade_no=' + encodeURIComponent(tradeNo), {credentials:'same-origin', cache:'no-store', signal:controller.signal})
            .then(function (response) { if (!response.ok) throw new Error('network'); return response.json(); })
            .then(function (data) {
                if (data.code === 1) {
                    paid = true;
                    payment.hidden = true;
                    document.getElementById('qrcode').textContent = '';
                    open.removeAttribute('href');
                    document.getElementById('checkPayment').disabled = true;
                    status.textContent = '订单结果已确认，正在返回…';
                    if (typeof data.backurl === 'string' && /^(https?:\/\/|\/(?!\/))/.test(data.backurl)) {
                        setTimeout(function () { window.location.assign(data.backurl); }, 1000);
                    }
                } else if (manual) {
                    status.textContent = '暂未确认到账。已付款请勿重复支付，稍后检查或联系商家。';
                }
            })
            .catch(function () { status.textContent = '到账查询暂时失败，正在重试。已付款请勿重复支付。'; })
            .finally(function () {
                clearTimeout(timeout);
                checking = false;
                if (!paid && Date.now() < pollUntil) timer = setTimeout(function () { check(false); }, 2000);
            });
    }
    document.getElementById('checkPayment').addEventListener('click', function () { check(true); });
    window.addEventListener('pageshow', function () { if (!paid) { countdown(); check(false); } });
    document.addEventListener('visibilitychange', function () { if (!document.hidden && !paid) { countdown(); check(false); } });
    check(false);
}());
</script>
</body>
</html>
