<?php
namespace lib;

final class BepusdtNetwork
{
    // Reviewed against BEpusdt v1.24.2; never form arbitrary coin/network pairs.
    public const TYPES = [
        'usdt.trc20'=>['coin'=>'USDT','network'=>'TRON (TRC20)','family'=>'tron'],
        'usdt.erc20'=>['coin'=>'USDT','network'=>'Ethereum (ERC20)','family'=>'evm'],
        'usdt.bep20'=>['coin'=>'USDT','network'=>'BSC (BEP20)','family'=>'evm'],
        'usdt.polygon'=>['coin'=>'USDT','network'=>'Polygon','family'=>'evm'],
        'usdt.arbitrum'=>['coin'=>'USDT','network'=>'Arbitrum One','family'=>'evm'],
        'usdt.solana'=>['coin'=>'USDT','network'=>'Solana','family'=>'solana'],
        'usdc.erc20'=>['coin'=>'USDC','network'=>'Ethereum (ERC20)','family'=>'evm'],
        'usdc.bep20'=>['coin'=>'USDC','network'=>'BSC (BEP20)','family'=>'evm'],
        'usdc.polygon'=>['coin'=>'USDC','network'=>'Polygon','family'=>'evm'],
        'usdc.arbitrum'=>['coin'=>'USDC','network'=>'Arbitrum One','family'=>'evm'],
        'usdc.base'=>['coin'=>'USDC','network'=>'Base','family'=>'evm'],
        'usdc.solana'=>['coin'=>'USDC','network'=>'Solana','family'=>'solana'],
    ];
    public static function label($type)
    {
        if (!isset(self::TYPES[$type])) throw new \InvalidArgumentException('不支持的币种与网络');
        return self::TYPES[$type]['coin'].' / '.self::TYPES[$type]['network'];
    }
    public static function address($type,$address)
    {
        self::label($type);
        if ($address==='') return;
        $patterns=['tron'=>'/\AT[1-9A-HJ-NP-Za-km-z]{33}\z/D','evm'=>'/\A0x[0-9a-fA-F]{40}\z/D','solana'=>'/\A[1-9A-HJ-NP-Za-km-z]{32,44}\z/D'];
        $family=self::TYPES[$type]['family'];
        if (!preg_match($patterns[$family],$address) || ($family==='solana' && self::base58Length($address)!==32)) throw new \InvalidArgumentException('收款地址与所选网络不符，或留空由网关分配');
    }
    private static function base58Length($value)
    {
        // A TRON address is also Base58, but is not a 32-byte Solana public key.
        $alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';$bytes=[];
        foreach (str_split($value) as $char) {
            $carry=strpos($alphabet,$char);
            foreach ($bytes as $i=>$byte) { $carry+=$byte*58;$bytes[$i]=$carry&255;$carry>>=8; }
            while ($carry>0) { $bytes[]=$carry&255;$carry>>=8; }
        }
        return count($bytes)+strspn($value,'1');
    }
    public static function parents(array $conf)
    {
        $parents=json_decode($conf['bepusdt_parents']??'{}',true);
        if (!is_array($parents)) $parents=[];
        $parents=array_intersect_key($parents,self::TYPES);
        if (!empty($conf['bepusdt_parent'])) $parents['usdt.trc20']=(int)$conf['bepusdt_parent'];
        return $parents;
    }
    public static function available($db,array $conf)
    {
        $result=[];
        foreach (self::parents($conf) as $type=>$id) {
            $p=$db->getRow('SELECT C.*,T.name typename,T.status type_status FROM pre_channel C JOIN pre_type T ON T.id=C.type WHERE C.id=:id',[':id'=>(int)$id]);
            if (!$p || $p['typename']!==$type || (int)$p['type_status']!==1 || $p['plugin']!=='bepusdt' || (int)$p['mode']!==1 || (int)$p['status']!==0 || (json_decode($p['config'],true)['bepusdt_managed']??null)!==1) continue;
            $result[]=['type'=>$type,'name'=>self::label($type),'family'=>self::TYPES[$type]['family']];
        }
        return $result;
    }
}
