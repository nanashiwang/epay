<?php
namespace lib;

final class CollectionNotify
{
    public static function accepted(array $meta,$body)
    {
        return empty($meta['error']) && ($meta['http_code']??0)>=200 && $meta['http_code']<300 && (strpos((string)$body,'success')!==false || strpos((string)$body,'SUCCESS')!==false || strpos((string)$body,'Success')!==false);
    }

    public static function transport($url,&$meta)
    {
        $meta=['http_code'=>0,'duration_ms'=>0,'error'=>1];
        $p=parse_url($url);
        if (!$p || !in_array($p['scheme']??'',['http','https'],true) || isset($p['user']) || isset($p['pass'])) return false;
        $host=$p['host']??''; $port=$p['port']??($p['scheme']==='https'?443:80);
        $ips=filter_var($host,FILTER_VALIDATE_IP)?[$host]:array_column(dns_get_record($host,DNS_A)?:[],'ip');
        if (!$ips) return false;
        foreach($ips as $ip) if (!GatewayHttp::publicIp($ip)) return false;
        $body=''; $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ips[0]],CURLOPT_PROXY=>'',CURLOPT_WRITEFUNCTION=>function($ch,$chunk) use(&$body) { if (strlen($body)+strlen($chunk)>65536) return 0; $body.=$chunk; return strlen($chunk); }]);
        $ok=curl_exec($curl);
        $meta=['http_code'=>(int)curl_getinfo($curl,CURLINFO_HTTP_CODE),'duration_ms'=>(int)round(curl_getinfo($curl,CURLINFO_TOTAL_TIME)*1000),'error'=>curl_errno($curl)];
        curl_close($curl);
        return $ok!==false?$body:false;
    }

    public static function scope()
    {
        $clauses=['EXISTS (SELECT 1 FROM pre_collection_account C WHERE C.id=O.subchannel AND C.uid=O.uid)'];
        if (!empty($GLOBALS['conf']['bepusdt_parent'])) $clauses[]='EXISTS (SELECT 1 FROM pre_bepusdt_order B WHERE B.trade_no=O.trade_no AND B.uid=O.uid AND B.account_id=O.subchannel AND B.account_id>0 AND B.state=\'paid\')';
        if (MerchantChannel::installed()) $clauses[]='EXISTS (SELECT 1 FROM pre_merchant_channel_order M WHERE M.trade_no=O.trade_no AND M.uid=O.uid AND M.account_id=O.subchannel AND M.paid_at IS NOT NULL)';
        return '('.implode(' OR ',$clauses).')';
    }

    public static function retry($db,$trade,$manual=false)
    {
        $lock='collection-notify:'.$trade;
        if ((int)$db->getColumn('SELECT GET_LOCK(:name,0)',[':name'=>$lock])!==1) return false;
        try {
            $o=$db->getRow('SELECT O.* FROM pre_order O WHERE '.self::scope().' AND O.trade_no=:trade AND O.status=1 AND O.tid=0',[':trade'=>$trade]);
            if (!$o) return false;
            if (!$manual && ((int)$o['notify']<=0 || strtotime($o['notifytime'])>time())) return false;
            $attempt=(int)$o['notify'];
            if (!$manual && $attempt>5) { $db->update('order',['notify'=>-1,'notifytime'=>null],['trade_no'=>$trade]); return false; }
            $wait=[1=>120,2=>960,3=>2160,4=>3600,5=>3600][$attempt]??60;
            $db->update('order',['notify'=>$attempt>=5?-1:max(1,$attempt+1),'notifytime'=>date('Y-m-d H:i:s',time()+$wait)],['trade_no'=>$trade]);
            $urls=creat_callback($o); $ok=do_notify($urls['notify']);
            if ($ok) $db->update('order',['notify'=>0,'notifytime'=>null],['trade_no'=>$trade]);
            return $ok;
        } finally { $db->getColumn('SELECT RELEASE_LOCK(:name)',[':name'=>$lock]); }
    }

    public static function order($db,$url)
    {
        parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
        if (empty($query['trade_no']) || !is_string($query['trade_no']) || !preg_match('/\A[0-9]{10,32}\z/D',$query['trade_no'])) return null;
        return $db->getRow('SELECT O.trade_no,O.uid,O.notify_url FROM pre_order O WHERE '.self::scope().' AND O.trade_no=:trade',[':trade'=>$query['trade_no']]);
    }

    public static function record($db,array $order,array $meta,$body,$success)
    {
        $parts=parse_url($order['notify_url']);
        $target=($parts['scheme']??'https').'://'.($parts['host']??'').(isset($parts['port'])?':'.$parts['port']:'').($parts['path']??'/');
        // Do not store callback signatures, query parameters, raw echoed keys or customer data.
        $summary=$success?'success':(($meta['error']??0)?'网络连接失败或超时':'未返回 success；响应摘要 '.substr(hash('sha256',(string)$body),0,16));
        $db->insert('collection_notify',['uid'=>$order['uid'],'trade_no'=>$order['trade_no'],'target'=>mb_substr($target,0,300),'http_code'=>$meta['http_code']??0,'duration_ms'=>$meta['duration_ms']??0,'success'=>$success?1:0,'response_summary'=>$summary,'created_at'=>'NOW()']);
    }
}
