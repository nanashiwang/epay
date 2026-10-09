<?php

class bepusdt_plugin
{
    public static $info = [
        'name'     => 'bepusdt',
        'showname' => 'BEpusdt USDT/USDC 个人收款',
        'author'   => 'V03413',
        'link'     => 'https://github.com/v03413/BEpusdt',
        'types'    => [
            // 此列表可能存在变动，以此为准 https://github.com/v03413/BEpusdt/blob/main/docs/trade-type.md
            'tron.trx',
            'usdt.trc20',
            'usdc.trc20',
            'usdt.polygon',
            'usdc.polygon',
            'usdt.arbitrum',
            'usdc.arbitrum',
            'usdt.erc20',
            'usdc.erc20',
            'usdt.bep20',
            'usdc.bep20',
            'usdt.xlayer',
            'usdc.xlayer',
            'usdc.base',
            'usdt.solana',
            'usdc.solana',
            'usdt.aptos',
            'usdc.aptos',
        ],
        'inputs'   => [
            'appurl'  => [
                'name' => '接口地址',
                'type' => 'input',
                'note' => '使用公网 HTTPS 域名，端口 443 或 8443，以 / 结尾',
            ],
            'appkey'  => [
                'name' => '认证Token',
                'type' => 'input',
                'note' => '搭建BEpusdt时填写的 auth_token 参数',
            ],
            'address' => [
                'name' => '收款地址',
                'type' => 'input',
                'note' => '可以留空 留空则由BEpusdt自动分配，切勿乱填 注意空格',
            ],
            'timeout' => [
                'name' => '订单超时',
                'type' => 'input',
                'note' => '可以留空 填写整数(单位秒)、推荐 1200',
            ],
            'rate'    => [
                'name' => '订单汇率',
                'type' => 'input',
                'note' => '可以留空 例如：7.4 ~1.02 ~0.98（不明白切勿乱填）',
            ],
        ],
        'select'   => null,
        'note'     => '', //支付密钥填写说明
    ];

    public static function submit(): array
    {
        global $DB,$order,$channel,$conf;
        if (empty($conf['bepusdt_parent'])) return ['type'=>'error','msg'=>'BEpusdt 接入尚未完成升级，请联系管理员执行接入迁移'];
        try { return ['type'=>'jump','url'=>(new \lib\BepusdtGateway($DB))->create($order,$channel)]; }
        catch (\Throwable $e) { return ['type'=>'error','msg'=>'无法创建支付：'.($e instanceof \PDOException?'订单保存失败，请联系管理员':$e->getMessage())]; }
    }

    public static function mapi(): array { return self::submit(); }

    public static function notify()
    {
        global $DB,$order,$channel,$conf;
        if (ob_get_length()) ob_clean();
        header('Content-Type: text/plain; charset=utf-8');
        try {
            $raw=file_get_contents('php://input',false,null,0,65537);
            if (strlen($raw)>65536) throw new \InvalidArgumentException('payload too large');
            $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new \InvalidArgumentException('invalid payload');
            $snapshot=!empty($conf['bepusdt_parent'])?$DB->find('bepusdt_order','trade_no',['trade_no'=>TRADE_NO]):null;
            if ($snapshot) (new \lib\BepusdtGateway($DB))->notify(TRADE_NO,$data);
            else {
                // Only administrator-owned orders created before migration may use the old contract.
                if (!empty($channel['bepusdt_managed']) || (!empty($conf['bepusdt_parent']) && (empty($conf['bepusdt_cutover']) || $order['addtime']>=$conf['bepusdt_cutover']))) throw new \InvalidArgumentException('missing snapshot');
                if (!\lib\BepusdtClient::verify($data,$channel['appkey']) || ($data['order_id']??null)!==TRADE_NO || ($data['status']??null)!==2 || empty($data['trade_id']) || !is_string($data['trade_id']) || \lib\BepusdtClient::decimal($data['amount']??null,2)!==\lib\BepusdtClient::decimal($order['realmoney'],2)) throw new \InvalidArgumentException('invalid legacy receipt');
                if (!empty($channel['address']) && trim($channel['address'])!==($data['token']??null)) throw new \InvalidArgumentException('legacy address mismatch');
                processNotify($order,$data['trade_id']);
            }
            exit('ok');
        } catch (\Throwable $e) { http_response_code(400); exit('fail'); }
    }

    public static function return(): array { return ['type'=>'page','page'=>'return']; }
}