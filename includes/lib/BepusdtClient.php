<?php
namespace lib;

class BepusdtClient
{
    private $endpoint;
    private $token;
    public function __construct($endpoint,$token) { $this->endpoint=GatewayHttp::endpoint($endpoint); $this->token=$token; }

    public static function decimal($value,$places=18)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw new \InvalidArgumentException('金额格式不正确');
        $s=(string)$value;
        if (!preg_match('/\A[0-9]{1,18}(?:\.[0-9]{1,'.$places.'})?\z/D',$s)) throw new \InvalidArgumentException('金额精度或范围不正确');
        [$whole,$fraction]=array_pad(explode('.',$s,2),2,'');
        $whole=ltrim($whole,'0'); $fraction=rtrim($fraction,'0');
        return ($whole===''?'0':$whole).($fraction===''?'':'.'.$fraction);
    }

    // Go's fmt %v uses shortest float64 digits and scientific notation at exponent >= 6 / < -4.
    // JSON numbers are decoded as float64 by BEpusdt, while JSON strings retain their exact text.
    private static function number($value)
    {
        $json=json_encode((float)$value,JSON_THROW_ON_ERROR);
        preg_match('/\A(-?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?\z/',$json,$m);
        $raw=$m[2].($m[3]??''); $digits=ltrim($raw,'0');
        if ($digits==='') return $m[1].'0';
        $exponent=strlen($m[2])+(int)($m[4]??0)-(strlen($raw)-strlen($digits))-1;
        $digits=rtrim($digits,'0');
        if ($exponent>=6 || $exponent< -4) return $m[1].$digits[0].(strlen($digits)>1?'.'.substr($digits,1):'').'e'.($exponent<0?'-':'+').str_pad((string)abs($exponent),2,'0',STR_PAD_LEFT);
        if ($exponent<0) return $m[1].'0.'.str_repeat('0',-$exponent-1).$digits;
        $digits=str_pad($digits,$exponent+1,'0');
        return $m[1].substr($digits,0,$exponent+1).(strlen($digits)>$exponent+1?'.'.substr($digits,$exponent+1):'');
    }

    public static function sign(array $values,$token)
    {
        ksort($values); $parts=[];
        foreach ($values as $key=>$value) {
            if ($key==='signature' || $value===null || $value==='') continue;
            if (!is_scalar($value) || (is_float($value) && !is_finite($value))) throw new \InvalidArgumentException('签名字段格式不正确');
            $parts[]=$key.'='.(is_bool($value)?($value?'true':'false'):((is_int($value)||is_float($value))?self::number($value):(string)$value));
        }
        return md5(implode('&',$parts).$token);
    }

    public static function verify(array $data,$token)
    {
        $signature=$data['signature']??null;
        if (!is_string($signature) || !preg_match('/\A[0-9a-f]{32}\z/D',$signature)) return false;
        try { return hash_equals(self::sign($data,$token),$signature); } catch (\Throwable $e) { return false; }
    }

    protected function post($path,array $params,$decode=true) { return GatewayHttp::post($this->endpoint,$path,$params,$decode); }

    public function create(array $params)
    {
        $amount=self::decimal($params['amount'],2);
        if ($amount==='0' || (float)$amount>99999999.99) throw new \InvalidArgumentException('支付金额必须大于零且不超过 99999999.99');
        $params['amount']=(float)$amount; $params['fiat']='CNY';
        $params['signature']=self::sign($params,$this->token);
        $response=$this->post('api/v1/order/create-transaction',$params);
        if (($response['status_code']??null)!==200 || !is_array($response['data']??null)) throw new \RuntimeException('网关未确认创建结果，请登录网关核对订单');
        $d=$response['data'];
        if (($d['order_id']??null)!==$params['order_id'] || ($d['fiat']??null)!=='CNY' || !is_string($d['trade_id']??null) || !preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/D',$d['trade_id']) || self::decimal($d['amount']??null,2)!==$amount || ($d['status']??null)!==1) throw new \RuntimeException('网关订单与请求不一致，请核对订单');
        if (isset($d['trade_type']) && $d['trade_type']!==$params['trade_type']) throw new \RuntimeException('网关支付网络不一致');
        if (!is_string($d['token']??null) || !preg_match('/\A[a-zA-Z0-9:_-]{20,128}\z/D',$d['token']) || (!empty($params['address']) && $params['address']!==$d['token'])) throw new \RuntimeException('网关收款地址不一致');
        $d['actual_amount']=self::decimal($d['actual_amount']??null);
        if ($d['actual_amount']==='0') throw new \RuntimeException('网关币额不正确');
        $seconds=$d['expiration_time']??null;
        if (!is_int($seconds) || $seconds<1 || $seconds>3600) throw new \RuntimeException('网关有效期不正确');
        $p=is_string($d['payment_url']??null)?parse_url($d['payment_url']):false; $origin=parse_url($this->endpoint);
        if (!$p || ($p['scheme']??'')!=='https' || strtolower($p['host']??'')!==$origin['host'] || ($p['port']??443)!==($origin['port']??443) || isset($p['user']) || isset($p['fragment']) || preg_match('/[\x00-\x20<>"\x27\\\\]/',$d['payment_url'])) throw new \RuntimeException('网关收银台地址不正确');
        return $d;
    }

    /** Checkout evidence is informational; never synthesize a signed payment callback. */
    public function inspect(array $snapshot)
    {
        if (empty($snapshot['provider_id'])) throw new \InvalidArgumentException('缺少网关单号，请在网关后台按本地订单号核对');
        $response=$this->post('api/v1/pay/info',['trade_id'=>$snapshot['provider_id']]);
        $d=$response['data']??null;
        if (($response['status_code']??null)!==200 || !is_array($d) || ($d['trade_id']??null)!==$snapshot['provider_id'] || ($d['order_id']??null)!==$snapshot['trade_no'] || ($d['fiat']??null)!=='CNY' || ($d['trade_type']??null)!==$snapshot['network'] || ($d['token']??null)!==$snapshot['address'] || self::decimal($d['money']??null,2)!==self::decimal($snapshot['money'],2) || self::decimal($d['actual_amount']??null)!==self::decimal($snapshot['coin_amount']) || !in_array($d['status']??null,[1,2,3],true)) throw new \RuntimeException('网关查询证据不完整或与订单不一致');
        return [1=>'网关报告待付款，请核对付款窗口',2=>'网关报告已付款，请在网关重发原始签名回调',3=>'网关报告已过期，请核对是否存在迟到付款'][$d['status']];
    }

    /** Signed echo verifies credentials without creating a charge or changing an order. */
    public function probe()
    {
        $params=['order_id'=>'epay-check-'.bin2hex(random_bytes(12)),'status'=>1];
        $params['signature']=self::sign($params,$this->token);
        if (trim($this->post('api/v1/pay/notify',$params,false))!=='ok') throw new \RuntimeException('网关签名校验未通过，请检查 Token');
        return true;
    }
}
