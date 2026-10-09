<?php
if (PHP_SAPI !== 'cli' || !isset($aop, $DB, $channel)) exit;
require_once __DIR__.'/NativeQr.php';
AlipayCodeNativeQr::codeUrl($channel['appurl'] ?? '');
if (!preg_match('/\A2088[0-9]{12}\z/D', (string)($channel['appmchid'] ?? ''))) {
    throw new RuntimeException('原生收款码模式需要配置收款账号 UID');
}

while (true) {
    $started = time();
    try {
        $pending = $DB->getColumn("SELECT COUNT(*) FROM pre_order WHERE channel=:channel AND subchannel=:subchannel AND status=0 AND addtime>=:since", [
            ':channel' => $channel['id'], ':subchannel' => $channel['subid'] ?? 0,
            ':since' => date('Y-m-d H:i:s', $started - AlipayCodeNativeQr::QUERY_SECONDS),
        ]);
        if ($pending === false || $pending === null) throw new RuntimeException('读取待确认订单失败');
        if ((int)$pending === 0) {
            echo '暂无待确认原生码订单'.PHP_EOL;
        } else {
            $page = 1;
            do {
                $result = $aop->accountlogQuery(date('Y-m-d H:i:s', $started - AlipayCodeNativeQr::QUERY_SECONDS), date('Y-m-d H:i:s', $started), $page, 2000);
                $items = $result['detail_list'] ?? [];
                foreach ($items as $item) {
                    AlipayCodeNativeQr::processBill($DB, $channel, $item, time(), function ($order, $receipt, $buyer) {
                        processNotify($order, $receipt, $buyer);
                        echo '原生码订单'.$order['trade_no'].'已提交到账确认'.PHP_EOL;
                    });
                }
                $more = !empty($items) && $page * 2000 < (int)($result['total_size'] ?? 0);
                $page++;
            } while ($more);
        }
    } catch (Throwable $e) {
        // Do not copy provider errors (which may contain signed request data) into persistent logs.
        echo '原生码账单查询或确认失败，请检查接口权限与数据库连接'.PHP_EOL;
    }
    sleep(max(1, 3 - (time() - $started)));
}
