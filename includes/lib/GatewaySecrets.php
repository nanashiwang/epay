<?php
namespace lib;

final class GatewaySecrets
{
    public static function path() { return getenv('EPAY_COLLECTION_KEY_FILE')?:dirname(ROOT).'/epay-collection.key'; }
    private static function key()
    {
        $path=realpath(self::path()); $root=realpath(ROOT);
        if (!$path || !$root || str_starts_with($path,$root.DIRECTORY_SEPARATOR)) throw new \RuntimeException('收款加密文件必须配置在网站目录外');
        $key=@file_get_contents($path);
        if ($key===false || strlen($key)!==32) throw new \RuntimeException('收款加密服务尚未配置');
        return $key;
    }
    public static function encrypt(array $data,$uid)
    {
        $iv=random_bytes(12);
        $encrypted=openssl_encrypt(json_encode($data,JSON_THROW_ON_ERROR),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'bepusdt:'.$uid);
        if ($encrypted===false) throw new \RuntimeException('收款配置加密失败');
        return base64_encode($iv.$tag.$encrypted);
    }
    public static function decrypt($value,$uid)
    {
        $bytes=base64_decode($value,true);
        if ($bytes===false || strlen($bytes)<29) throw new \RuntimeException('收款配置不可读取');
        $plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16),'bepusdt:'.$uid);
        if ($plain===false) throw new \RuntimeException('收款配置不可读取');
        return json_decode($plain,true,64,JSON_THROW_ON_ERROR);
    }
}
