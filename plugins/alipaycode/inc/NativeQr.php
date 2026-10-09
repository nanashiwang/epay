<?php

final class AlipayCodeNativeQr
{
    const PAY_SECONDS = 300;
    const QUERY_SECONDS = 480;

    public static function codeUrl($value)
    {
        $value = trim((string)$value);
        if (!preg_match('~\Ahttps://qr\.alipay\.com/[A-Za-z0-9]+\z~D', $value)) {
            throw new InvalidArgumentException('请配置支付宝原生收款码地址（https://qr.alipay.com/…）');
        }
        return $value;
    }

    public static function cents($value)
    {
        if (!is_scalar($value) || !preg_match('/\A([0-9]{1,10})(?:\.([0-9]{1,2}))?\z/D', (string)$value, $m)) {
            return null;
        }
        return (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
    }

    public static function timestamp($value)
    {
        if (!is_string($value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Asia/Shanghai'));
        return $date && $date->format('Y-m-d H:i:s') === $value ? $date->getTimestamp() : null;
    }

    public static function remaining(array $order, $now)
    {
        $created = self::timestamp($order['addtime'] ?? null);
        if ($created === null || (int)($order['status'] ?? -1) !== 0) return 0;
        return max(0, min(self::PAY_SECONDS, $created + self::PAY_SECONDS - $now));
    }

    // Include completed orders in $orders: a shared amount must never pick an arbitrary unpaid order.
    public static function matchBill(array $orders, array $bill, $now)
    {
        $money = self::cents($bill['trans_amount'] ?? null);
        $paidAt = self::timestamp($bill['trans_dt'] ?? null);
        if (($bill['direction'] ?? '') !== '收入' || $money === null || $money <= 0
            || $paidAt === null || $paidAt > $now || $paidAt < $now - self::QUERY_SECONDS
            || !preg_match('/\A[0-9]{10,64}\z/D', (string)($bill['alipay_order_no'] ?? ''))) {
            return null;
        }
        $memo = trim((string)($bill['trans_memo'] ?? ''));
        $tradeNo = null;
        if ($memo !== '') {
            if (!preg_match('/\A(?:请勿添加备注-)?([0-9]{10,32})\z/uD', $memo, $m)) return null;
            $tradeNo = $m[1];
        }
        $matches = [];
        foreach ($orders as $order) {
            $created = self::timestamp($order['addtime'] ?? null);
            if ($created === null || $paidAt < $created || $paidAt >= $created + self::PAY_SECONDS
                || self::cents($order['realmoney'] ?? null) !== $money
                || ($tradeNo !== null && (string)$order['trade_no'] !== $tradeNo)) continue;
            $matches[] = $order;
        }
        if (count($matches) !== 1 || (int)$matches[0]['status'] !== 0) return null;
        return $matches[0];
    }

    public static function accountChannelIds(array $channel, array $rows)
    {
        $ids = [(int)$channel['id']];
        foreach ($rows as $row) {
            $config = json_decode($row['config'] ?? '', true);
            if (!is_array($config)) continue;
            // Parameterized parent channels are included conservatively because they can share a payee.
            $uid = (string)($config['appmchid'] ?? '');
            if ($uid === (string)$channel['appmchid'] || substr($uid, 0, 1) === '['
                || (!empty($config['appid']) && $config['appid'] === $channel['appid'])) {
                $ids[] = (int)$row['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    public static function processBill($db, array $channel, array $bill, $now, callable $notify)
    {
        $lock = 'alipaycode:' . substr(hash('sha256', (string)$channel['appmchid']), 0, 48);
        if ((int)$db->getColumn('SELECT GET_LOCK(:lock_name, 0)', [':lock_name' => $lock]) !== 1) return false;
        try {
            $channels = $db->getAll("SELECT id,config FROM pre_channel WHERE plugin='alipaycode'");
            if (!is_array($channels)) throw new RuntimeException('读取收款通道失败');
            $ids = implode(',', self::accountChannelIds($channel, $channels));
            $orders = $db->getAll("SELECT trade_no,realmoney,addtime,status,channel,subchannel FROM pre_order WHERE channel IN ({$ids}) AND addtime>=:since", [
                ':since' => date('Y-m-d H:i:s', $now - self::QUERY_SECONDS - self::PAY_SECONDS),
            ]);
            if (!is_array($orders)) throw new RuntimeException('读取候选订单失败');
            $match = self::matchBill($orders, $bill, $now);
            if (!$match || (int)$match['channel'] !== (int)$channel['id']
                || (int)$match['subchannel'] !== (int)($channel['subid'] ?? 0)) return false;
            $used = $db->getColumn('SELECT COUNT(*) FROM pre_order WHERE api_trade_no=:receipt', [':receipt' => $bill['alipay_order_no']]);
            if ($used === false || $used === null || (int)$used !== 0) return false;
            $order = $db->getRow('SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A LEFT JOIN pre_type B ON A.type=B.id WHERE A.trade_no=:trade_no LIMIT 1', [':trade_no' => $match['trade_no']]);
            if (!$order || (int)$order['status'] !== 0 || !empty($order['api_trade_no'])
                || (int)$order['channel'] !== (int)$channel['id']
                || (int)$order['subchannel'] !== (int)($channel['subid'] ?? 0)
                || self::cents($order['realmoney']) !== self::cents($bill['trans_amount'])) return false;
            $order['plugin'] = 'alipaycode';
            $buyer = empty($order['buyer']) ? ($bill['other_account'] ?? null) : null;
            $notify($order, $bill['alipay_order_no'], $buyer);
            return true;
        } finally {
            $db->getColumn('SELECT RELEASE_LOCK(:lock_name)', [':lock_name' => $lock]);
        }
    }
}
