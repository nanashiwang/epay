<?php
// Isolated PHP tests: no production bootstrap, credentials, provider requests or payments.
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__.'/../plugins/alipaycode/inc/NativeQr.php';
date_default_timezone_set('Asia/Shanghai');

if (($argv[1] ?? '') === '--render') {
    define('IN_PLUGIN', true);
    define('PAY_ROOT', __DIR__.'/../plugins/alipaycode/');
    define('TRADE_NO', '2026100918000012345');
    $state = $argv[2] ?? 'pending';
    $mode = $argv[3] ?? '2';
    $channel = ['appswitch' => $mode, 'appurl' => 'https://qr.alipay.com/example123', 'appmchid' => '2088000000000000'];
    $order = ['trade_no' => TRADE_NO, 'realmoney' => '0.57', 'name' => '<测试商品>', 'status' => $state === 'paid' ? 1 : 0,
        'addtime' => date('Y-m-d H:i:s', time() - ($state === 'expired' ? 400 : ($state === 'soon' ? 295 : 0)))];
    $conf = ['alipay_wappaylogin' => $state === 'guarded' ? 1 : 0];
    $siteurl = 'https://example.invalid/';
    $cdnpublic = 'https://cdn.staticfile.org/';
    require PAY_ROOT.'alipaycode_plugin.php';
    try {
        if (($argv[4] ?? '') === 'pay') alipaycode_plugin::pay();
        else alipaycode_plugin::qrcode();
    } catch (Exception $e) { echo $e->getMessage(); }
    exit;
}

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $checks++;
}
function throws(callable $f, $label) {
    try { $f(); } catch (InvalidArgumentException $e) { check(true, $label); return; }
    check(false, $label);
}
$created = strtotime('2026-10-09 18:00:00');
$now = $created + 60;
$order = ['trade_no' => '2026100918000012345', 'realmoney' => '0.57', 'addtime' => '2026-10-09 18:00:00', 'status' => 0, 'channel' => 3, 'subchannel' => 0];
$bill = ['direction' => '收入', 'trans_amount' => '0.57', 'trans_dt' => '2026-10-09 18:00:30', 'alipay_order_no' => '2026100923001000000000000000'];
$channel = ['id' => 3, 'appmchid' => '2088000000000000', 'appid' => '2021000000000000'];
$match = static function ($orders, $item) use ($now) { return AlipayCodeNativeQr::matchBill($orders, $item, $now); };
check(AlipayCodeNativeQr::codeUrl(' https://qr.alipay.com/example123 ') === 'https://qr.alipay.com/example123', 'native QR URL');
foreach (['http://qr.alipay.com/abc', 'https://qr.alipay.com.evil.test/abc', 'https://evil.test/abc', 'https://qr.alipay.com@evil.test/abc', 'javascript:alert(1)', "https://qr.alipay.com/abc\nxyz", 'https://qr.alipay.com/abc?amount=1', 'https://qr.alipay.com/'] as $url) {
    throws(static function () use ($url) { AlipayCodeNativeQr::codeUrl($url); }, 'reject invalid QR URL');
}
check(AlipayCodeNativeQr::cents('0.57') === 57 && AlipayCodeNativeQr::cents('1.2') === 120, 'integer cents');
foreach (['1.001', '-0.57', '1e2', '', null, [], 'not money'] as $amount) check(AlipayCodeNativeQr::cents($amount) === null, 'reject malformed amount');
check($match([$order], $bill)['trade_no'] === $order['trade_no'], 'unique amount income without memo');
foreach (['direction' => '支出', 'trans_amount' => '0.58', 'trans_dt' => '2026-10-09 17:59:59', 'alipay_order_no' => ''] as $key => $value) {
    check($match([$order], array_replace($bill, [$key => $value])) === null, 'reject mismatched '.$key);
}
check($match([$order], array_replace($bill, ['trans_dt' => '2026-02-31 18:00:00'])) === null, 'invalid date');
check($match([$order], array_replace($bill, ['trans_dt' => '2026-10-09 18:02:00'])) === null, 'future receipt');
check(AlipayCodeNativeQr::matchBill([$order], array_replace($bill, ['trans_dt' => '2026-10-09 18:05:00']), $created + 360) === null, 'expiry boundary');
check(AlipayCodeNativeQr::matchBill([$order], $bill, $created + 600) === null, 'receipt beyond query window');
check(AlipayCodeNativeQr::matchBill([$order], array_replace($bill, ['trans_dt' => '2026-10-09 18:04:59']), $created + 420) !== null, 'timely payment found during grace');
$other = array_replace($order, ['trade_no' => '2026100918000099999']);
check($match([$order, $other], $bill) === null, 'ambiguous pending amount');
check($match([$order, array_replace($other, ['status' => 1])], $bill) === null, 'paid sibling remains ambiguous');
check($match([array_replace($order, ['status' => 1])], $bill) === null, 'completed order rejected');
check($match([$order, array_replace($other, ['addtime' => '2026-10-09 18:00:31'])], $bill) !== null, 'order after receipt is not a candidate');
foreach ([$order['trade_no'], '请勿添加备注-'.$order['trade_no']] as $memo) {
    check($match([$order, $other], array_replace($bill, ['trans_memo' => $memo]))['trade_no'] === $order['trade_no'], 'explicit memo disambiguates');
}
check($match([$order], array_replace($bill, ['trans_memo' => $other['trade_no']])) === null, 'wrong memo never falls back to amount');
check($match([$order], array_replace($bill, ['trans_memo' => 'other purpose'])) === null, 'unrecognized memo requires review');
check(AlipayCodeNativeQr::remaining($order, $now) === 240, 'payment countdown');
check(AlipayCodeNativeQr::remaining($order, $created + 300) === 0, 'expired countdown');
check(AlipayCodeNativeQr::remaining(array_replace($order, ['status' => 1]), $now) === 0, 'paid countdown');
check(AlipayCodeNativeQr::remaining(array_replace($order, ['addtime' => 'invalid']), $now) === 0, 'invalid timestamp fail closed');
check(AlipayCodeNativeQr::accountChannelIds($channel, [
    ['id' => 4, 'config' => json_encode(['appmchid' => $channel['appmchid']])],
    ['id' => 5, 'config' => json_encode(['appid' => $channel['appid']])],
    ['id' => 6, 'config' => json_encode(['appmchid' => '2088111111111111'])],
    ['id' => 7, 'config' => json_encode(['appmchid' => '[uid]'])],
]) === [3,4,5,7], 'payee grouping across configured channels');

class NativeQrTestDb {
    public $orders;
    public $fresh;
    public $used = 0;
    public $locked = true;
    public $released = 0;
    public $failRead = false;
    function __construct($orders) { $this->orders = $orders; $this->fresh = $orders[0]; }
    function getColumn($sql, $params = []) {
        if (strpos($sql, 'GET_LOCK') !== false) return $this->locked ? 1 : 0;
        if (strpos($sql, 'RELEASE_LOCK') !== false) { $this->released++; return 1; }
        if (strpos($sql, 'COUNT(*)') !== false) return $this->used;
        throw new RuntimeException('unexpected query');
    }
    function getAll($sql, $params = []) {
        if ($this->failRead) return false;
        return strpos($sql, 'pre_channel') !== false ? [] : $this->orders;
    }
    function getRow($sql, $params = []) { return $this->fresh; }
}
$calls = 0;
$db = new NativeQrTestDb([$order]);
$notify = static function ($o, $receipt, $buyer) use (&$calls, $db, $bill) {
    $calls++;
    check($o['plugin'] === 'alipaycode' && $receipt === $bill['alipay_order_no'], 'central callback contract');
    $db->used = 1;
};
check(AlipayCodeNativeQr::processBill($db, $channel, $bill, $now, $notify), 'dispatch matching receipt');
check(!AlipayCodeNativeQr::processBill($db, $channel, $bill, $now, $notify) && $calls === 1, 'duplicate receipt never dispatches twice');
check($db->released === 2, 'lock released on success and duplicate');
foreach (['used', 'locked', 'fresh_status', 'fresh_amount', 'fresh_channel', 'fresh_subchannel', 'candidate_channel'] as $case) {
    $db = new NativeQrTestDb([$order]);
    if ($case === 'used') $db->used = 1;
    if ($case === 'locked') $db->locked = false;
    if ($case === 'fresh_status') $db->fresh['status'] = 1;
    if ($case === 'fresh_amount') $db->fresh['realmoney'] = '1.00';
    if ($case === 'fresh_channel') $db->fresh['channel'] = 4;
    if ($case === 'fresh_subchannel') $db->fresh['subchannel'] = 4;
    if ($case === 'candidate_channel') $db->orders[0]['channel'] = 4;
    check(!AlipayCodeNativeQr::processBill($db, $channel, $bill, $now, static function () { throw new RuntimeException('must not notify'); }), 'reject '.$case);
}
$db = new NativeQrTestDb([$order]);
try { AlipayCodeNativeQr::processBill($db, $channel, $bill, $now, static function () { throw new RuntimeException('callback failed'); }); } catch (RuntimeException $e) { check($e->getMessage() === 'callback failed', 'callback failure preserved'); }
check($db->released === 1, 'callback failure releases lock');
$db = new NativeQrTestDb([$order]); $db->failRead = true;
try { AlipayCodeNativeQr::processBill($db, $channel, $bill, $now, static function () {}); } catch (RuntimeException $e) { check(true, 'database failure stops matching'); }
check($db->released === 1, 'database failure releases lock');

$render = static function ($state, $mode = '2', $route = 'qrcode') {
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --render '.escapeshellarg($state).' '.escapeshellarg($mode).' '.escapeshellarg($route);
    exec($command, $output, $code);
    check($code === 0, 'render process exits normally');
    return implode("\n", $output);
};
foreach (['qrcode','pay'] as $route) {
    $html = $render('pending', '2', $route);
    check(strpos($html, 'qr.alipay.com') !== false && strpos($html, '20000123') === false, 'native code without UID transfer');
    check(strpos($html, '&lt;测试商品&gt;') !== false && strpos($html, '<测试商品>') === false, 'escaped order name');
    check(strpos($html, 'getshop.php') !== false, 'server-confirmed payment status');
}
foreach (['expired','paid'] as $state) {
    $html = $render($state);
    check(strpos($html, 'var codeUrl = "";') !== false, 'no QR payload for '.$state);
    check(strpos($html, 'id="payment" hidden') !== false, 'payment actions hidden for '.$state);
}
check(strpos($render('guarded'), '不支持此检查') !== false, 'payer identity check cannot silently be bypassed');
foreach (['0','1'] as $mode) {
    $html = $render('pending', $mode);
    check(strpos($html, 'example.invalid/pay/pay/') !== false, 'legacy QR route preserved '.$mode);
}
check(strpos($render('pending', '0', 'pay'), '20000123') !== false, 'legacy ordinary transfer preserved');
check(trim($render('pending', '1', 'pay')) === '', 'legacy transfer confirmation redirects');
define('TRADE_NO', '2026100918000012345');
require __DIR__.'/../plugins/alipaycode/alipaycode_plugin.php';
check(alipaycode_plugin::submit() === alipaycode_plugin::mapi(), 'web and API share cashier');
echo "PASS {$checks} checks\n";
