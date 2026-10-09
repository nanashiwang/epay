<?php
namespace lib;

/** HTTPS requests to merchant-supplied gateways, pinned to a validated public IP. */
class GatewayHttp
{
    public static function endpoint($value)
    {
        if (!is_string($value) || strlen($value)>250) throw new \InvalidArgumentException('网关地址不正确');
        $p=parse_url(trim($value));
        if (!$p || ($p['scheme']??'')!=='https' || empty($p['host']) || isset($p['user'],$p['pass']) || isset($p['user']) || isset($p['query']) || isset($p['fragment'])) throw new \InvalidArgumentException('请填写不带账号、参数的 HTTPS 网关地址');
        $host=strtolower($p['host']); $port=$p['port']??443;
        if (!preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D',$host) || !str_contains($host,'.') || !in_array($port,[443,8443],true)) throw new \InvalidArgumentException('网关须使用公网域名及 HTTPS 443 或 8443 端口');
        $path=$p['path']??'/';
        if (!preg_match('#\A/(?:[a-zA-Z0-9._~-]+/)*[a-zA-Z0-9._~-]*\z#D',$path) || preg_match('~(?:^|/)\.{1,2}(?:/|$)~',$path)) throw new \InvalidArgumentException('网关路径不正确');
        return 'https://'.$host.($port===443?'':':'.$port).rtrim($path,'/').'/';
    }

    public static function publicIp($ip)
    {
        if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return false;
        if (str_contains($ip,':')) return (bool)preg_match('/\A[23][0-9a-f]{3}:/i',$ip) && !preg_match('/\A(?:2002:|2001:(?:0{1,4}:|db8:)|3fff:)/i',$ip);
        $n=(int)sprintf('%u',ip2long($ip));
        foreach ([['100.64.0.0',10],['192.0.0.0',24],['192.0.2.0',24],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',3]] as [$network,$bits]) {
            $mask=(0xffffffff << (32-$bits)) & 0xffffffff;
            if (($n & $mask)==(ip2long($network)&$mask)) return false;
        }
        return true;
    }

    public static function addresses($host)
    {
        $ips=[];
        if (filter_var($host,FILTER_VALIDATE_IP)) $ips=[$host];
        else foreach ((array)@dns_get_record($host,DNS_A|DNS_AAAA) as $record) {
            $ip=$record['ip']??$record['ipv6']??null; if ($ip) $ips[]=$ip;
        }
        if (!$ips) throw new \RuntimeException('无法解析网关域名');
        foreach ($ips as $ip) if (!self::publicIp($ip)) throw new \RuntimeException('网关地址必须解析到公网');
        return array_values(array_unique($ips));
    }

    public static function post($endpoint,$path,array $payload,$decode=true)
    {
        $endpoint=self::endpoint($endpoint); $p=parse_url($endpoint); $port=$p['port']??443;
        $ip=self::addresses($p['host'])[0]; $body='';
        $ch=curl_init($endpoint.$path);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_PROXY=>'',
            CURLOPT_RESOLVE=>[$p['host'].':'.$port.':'.(str_contains($ip,':')?'['.$ip.']':$ip)],
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk) use (&$body) {
                if (strlen($body)+strlen($chunk)>1048576) return 0; $body.=$chunk; return strlen($chunk);
            }]);
        $ok=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); $connected=curl_getinfo($ch,CURLINFO_PRIMARY_IP); curl_close($ch);
        if ($ok===false || $status!==200 || @inet_pton($connected)!==@inet_pton($ip)) throw new \RuntimeException('网关请求未确认，请核对网关订单后处理，不要重复创建');
        if (!$decode) return $body;
        try { $result=json_decode($body,true,64,JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw new \RuntimeException('网关响应格式不正确，请核对订单'); }
        if (!is_array($result)) throw new \RuntimeException('网关响应格式不正确，请核对订单');
        return $result;
    }
}
