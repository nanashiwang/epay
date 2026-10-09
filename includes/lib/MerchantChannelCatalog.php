<?php
namespace lib;

/** Reviewed tenant-facing configuration and execution contracts, not admin plugin APIs. */
final class MerchantChannelCatalog
{
    public static function all()
    {
        $field=static fn($name,$secret=false,$textarea=false)=>['name'=>$name,'secret'=>$secret,'type'=>$textarea?'textarea':'input'];
        return [
            'alipay'=>['name'=>'支付宝官方支付','types'=>['alipay'],'note'=>'RSA2 公钥模式。支持当面付扫码、电脑网站和手机网站支付，请选择已签约的产品。',
                'fields'=>['appid'=>$field('应用 APPID'),'appmchid'=>$field('卖家支付宝 UID（2088 开头）'),'appkey'=>$field('支付宝公钥',true,true),'appsecret'=>$field('应用私钥',true,true)],
                'modes'=>['1'=>'电脑网站支付','2'=>'手机网站支付','3'=>'当面付扫码'], 'default'=>['3'],
                'actions'=>['submit','mapi','qrcode','qrcodepc','submitwap','notify','return','ok']],
            'wxpayn'=>['name'=>'微信官方支付 V3','types'=>['wxpay'],'note'=>'微信支付公钥模式。密钥直接加密保存，无需管理员上传文件。支持 Native 扫码与 H5，AppID 须已关联商户号。',
                'fields'=>['appid'=>$field('关联的 AppID'),'appmchid'=>$field('商户号'),'appsecret'=>$field('APIv3 密钥（32 位）',true),'appkey'=>$field('商户 API 证书序列号'),'publickeyid'=>$field('微信支付公钥 ID'),'merchant_private_key'=>$field('商户 API 私钥 PEM',true,true),'platform_public_key'=>$field('微信支付公钥 PEM',true,true)],
                'modes'=>['1'=>'Native 扫码','3'=>'H5 支付'],'default'=>['1'],'actions'=>['submit','mapi','qrcode','h5','notify','return','ok']],
            'wxpay'=>['name'=>'微信官方支付 V2','types'=>['wxpay'],'note'=>'适用于已开通 APIv2 的商户。支持 Native 扫码与 H5；新接入可选择 V3。',
                'fields'=>['appid'=>$field('关联的 AppID'),'appmchid'=>$field('商户号'),'appkey'=>$field('APIv2 密钥（32 位）',true)],
                'modes'=>['1'=>'Native 扫码','3'=>'H5 支付'],'default'=>['1'],'actions'=>['submit','mapi','qrcode','h5','notify','return','ok']],
            'qqpay'=>['name'=>'QQ 钱包官方支付','types'=>['qqpay'],'note'=>'扫码及 H5 收款。不需要填写资金下发操作员账号或上传退款证书。',
                'fields'=>['appid'=>$field('QQ 钱包商户号'),'appkey'=>$field('API 密钥（32 位）',true)],
                'modes'=>['1'=>'扫码 / H5'],'default'=>['1'],'actions'=>['submit','mapi','qrcode','notify','return','ok']],
            'epay'=>['name'=>'易支付兼容网关','types'=>['alipay','wxpay','qqpay','bank','jdpay'],'note'=>'填写你自己的 HTTPS 网关、商户 ID 和密钥，跳转至该网关收银台付款。每种支付方式分别配置默认账号。',
                'fields'=>['appurl'=>$field('HTTPS 网关地址'),'appid'=>$field('商户 ID'),'appkey'=>$field('商户密钥',true)],
                'modes'=>[],'default'=>[],'actions'=>['submit','mapi','notify','return']],
        ];
    }
    public static function get($plugin)
    {
        $all=self::all();
        if (!is_string($plugin) || !isset($all[$plugin])) throw new \InvalidArgumentException('此插件尚未开放商户自助配置');
        return $all[$plugin];
    }
    public static function rsa($value,$private)
    {
        $raw=preg_replace('/-----[^-]+-----|\s+/','',$value);
        $kind=$private?'PRIVATE KEY':'PUBLIC KEY';
        $pem="-----BEGIN $kind-----\n".chunk_split($raw,64,"\n")."-----END $kind-----\n";
        $key=$private?openssl_pkey_get_private($pem):openssl_pkey_get_public($pem);
        if (!$key && $private) {
            $pem="-----BEGIN RSA PRIVATE KEY-----\n".chunk_split($raw,64,"\n")."-----END RSA PRIVATE KEY-----\n";
            $key=openssl_pkey_get_private($pem);
        }
        $d=$key?openssl_pkey_get_details($key):false;
        if (!$d || $d['type']!==OPENSSL_KEYTYPE_RSA || $d['bits']<2048) throw new \InvalidArgumentException('请填写有效的 RSA 密钥（至少 2048 位）');
        return $pem;
    }
    public static function validate($plugin,array $input,array $old=[])
    {
        $spec=self::get($plugin); $config=[];
        foreach ($spec['fields'] as $key=>$field) {
            if (isset($input[$key]) && !is_string($input[$key])) throw new \InvalidArgumentException('配置字段格式不正确');
            $value=trim($input[$key]??'');
            if ($value==='' && $field['secret']) $value=$old[$key]??'';
            if ($value==='' || strlen($value)>8192) throw new \InvalidArgumentException('请填写'.$field['name']);
            $config[$key]=$value;
        }
        if ($plugin==='alipay') {
            if (!preg_match('/\A20[0-9]{14}\z/D',$config['appid']) || !preg_match('/\A2088[0-9]{12}\z/D',$config['appmchid'])) throw new \InvalidArgumentException('请检查支付宝 APPID 和卖家 UID');
            foreach (['appkey','appsecret'] as $key) $config[$key]=preg_replace('/-----[^-]+-----|\s+/','',self::rsa($config[$key],$key==='appsecret'));
        } elseif (in_array($plugin,['wxpay','wxpayn'],true)) {
            if (!preg_match('/\Awx[a-zA-Z0-9]{16}\z/D',$config['appid']) || !preg_match('/\A[0-9]{8,16}\z/D',$config['appmchid'])) throw new \InvalidArgumentException('请检查微信 AppID 和商户号');
            $key=$plugin==='wxpayn'?'appsecret':'appkey';
            if (!preg_match('/\A[\x21-\x7e]{32}\z/D',$config[$key])) throw new \InvalidArgumentException('微信 API 密钥须为 32 位');
            if ($plugin==='wxpayn') {
                if (!preg_match('/\A[0-9A-Fa-f]{16,64}\z/D',$config['appkey']) || !preg_match('/\APUB_KEY_ID_[0-9A-Za-z_]{8,80}\z/D',$config['publickeyid'])) throw new \InvalidArgumentException('请检查证书序列号与微信支付公钥 ID');
                $config['merchant_private_key']=self::rsa($config['merchant_private_key'],true);
                $config['platform_public_key']=self::rsa($config['platform_public_key'],false);
            }
        } elseif ($plugin==='qqpay') {
            if (!preg_match('/\A[0-9]{5,20}\z/D',$config['appid']) || !preg_match('/\A[\x21-\x7e]{32}\z/D',$config['appkey'])) throw new \InvalidArgumentException('请检查 QQ 商户号及 32 位 API 密钥');
        } elseif ($plugin==='epay') {
            $config['appurl']=GatewayHttp::endpoint($config['appurl']);
            GatewayHttp::addresses(parse_url($config['appurl'],PHP_URL_HOST));
            if (!preg_match('/\A[0-9]{1,20}\z/D',$config['appid']) || !preg_match('/\A[\x21-\x7e]{8,256}\z/D',$config['appkey'])) throw new \InvalidArgumentException('请检查易支付商户 ID 和密钥');
        }
        $modes=$input['apptype']??[];
        if (!is_array($modes) || array_filter($modes,static fn($v)=>!is_string($v) || !array_key_exists($v,$spec['modes'])) || ($spec['modes'] && !$modes)) throw new \InvalidArgumentException('请选择支持的收款产品');
        $config['apptype']=implode(',',array_unique($modes));
        return $config;
    }
    public static function guard(array $channel,$action)
    {
        $spec=self::get($channel['plugin']);
        if (!in_array($action,$spec['actions'],true)) throw new \InvalidArgumentException('此操作未对商户自助通道开放');
        if ($action==='mapi' && !in_array($GLOBALS['method']??'',['','web'],true)) throw new \InvalidArgumentException('此通道支持网页 / 扫码收款，请使用 web 方式');
        $required=['h5'=>'3','submitwap'=>'2','qrcodepc'=>'1'];
        $modes=is_array($channel['apptype'])?$channel['apptype']:explode(',',$channel['apptype']);
        if (isset($required[$action]) && !in_array($required[$action],$modes,true)) throw new \InvalidArgumentException('此收款产品尚未启用');
    }
}
