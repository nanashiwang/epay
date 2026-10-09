<?php
namespace lib;

final class RuntimeHealth
{
    public static function check($db,array $conf,$now=null)
    {
        $now=$now??time();$checks=[];$warnings=[];$code=0;
        $add=static function($name,$ok,$detail,$retryable=false) use(&$checks,&$code) {
            $checks[]=['name'=>$name,'ok'=>(bool)$ok,'detail'=>$detail];
            if(!$ok) $code=($code===1 || !$retryable)?1:2;
        };
        preg_match("/define\('DB_VERSION',\s*'(\d+)'\)/",file_get_contents(ROOT.'includes/common.php'),$m);
        $versionOk=isset($m[1]) && (int)($conf['version']??0)>=(int)$m[1];
        $add('数据库版本',$versionOk,$versionOk?'版本匹配':'请完成 /install/update.php 数据库升级');
        $enabled=!empty($conf['merchant_channels']) || !empty($conf['bepusdt_parent']) || !empty($conf['collection_parent']);
        if (!$enabled) return ['ok'=>$code===0,'exit_code'=>$code,'checks'=>$checks,'warnings'=>['未启用商户自助收款，扩展任务检查已跳过']];
        $schema=true;
        // Read migration declarations; never execute migration SQL from a health check.
        foreach(['collection','bepusdt','merchant-channel','merchant-operations','payment-review'] as $file) {
            preg_match_all('/CREATE TABLE IF NOT EXISTS `pre_(\w+)` \((.*?)\) ENGINE/s',file_get_contents(ROOT.'install/'.$file.'.sql'),$tables,PREG_SET_ORDER);
            foreach($tables as $table) {
                preg_match_all('/`(\w+)`\s+(?:bigint|int|tinyint|varchar|char|text|mediumtext|datetime|decimal)\b/i',$table[2],$columns);
                try {if ($db->query('SELECT `'.implode('`,`',$columns[1]).'` FROM pre_'.$table[1].' LIMIT 0')===false) $schema=false;}
                catch(\Throwable $e) {$schema=false;}
            }
        }
        $add('收款扩展迁移',$schema,$schema?'所需表及字段完整':'请备份后执行 collection、bepusdt、merchant-channel、merchant-operations、payment-review 对应 setup.php --apply');
        $keyOk=true;$samples=0;
        try {
            $encrypted=GatewaySecrets::encrypt(['health'=>true],0);
            GatewaySecrets::decrypt($encrypted,0);
            if ($schema) foreach([
                ['collection_account','secret','collection','id'],['bepusdt_account','secret','bepusdt','id'],
                ['merchant_channel_account','secret','merchant-channel','id'],['bepusdt_order','config_snapshot','bepusdt','trade_no'],
                ['merchant_channel_order','config_snapshot','merchant-order','trade_no']
            ] as [$table,$field,$purpose,$sort]) {
                $row=$db->getRow('SELECT uid,'.$field.' value FROM pre_'.$table.' ORDER BY '.$sort.' DESC LIMIT 1');
                if($row){GatewaySecrets::decrypt($row['value'],$row['uid'],$purpose);$samples++;}
            }
        } catch(\Throwable $e) {$keyOk=false;}
        $add('收款主密钥',$keyOk,$keyOk?'可读取站点外密钥；已核对 '.$samples.' 类最近密文样本':'密钥不可读、位于站点内或无法解密历史样本；请恢复原密钥，勿生成替代密钥');
        foreach(['collection'=>120,'subscription'=>660] as $name=>$limit) {
            $beat=WorkerRuntime::read($db,$name);$at=(int)($beat['at']??0);
            $fresh=$at>0 && $now-$at<=$limit && $at<=$now+60;
            $states=$name==='subscription' && empty($conf['msgconfig_group'])?['ok','disabled']:['ok'];
            $instance=WorkerRuntime::instance();
            $fresh=$fresh && ($instance===null || ($instance!=='' && hash_equals($instance,(string)($beat['instance']??''))));
            $ok=$fresh && in_array($beat['state']??'',$states,true);
            $add($name.' 任务',$ok,$ok?'最近一轮已完成':('未发现有效完成心跳，请检查 '.$name.'-worker 日志'),true);
        }
        if($schema) {
            $r=$db->getRow('SELECT COUNT(*) pending,COALESCE(SUM(O.notify=-1),0) stopped,COALESCE(SUM(O.endtime<DATE_SUB(NOW(),INTERVAL 1 HOUR)),0) old FROM pre_order O WHERE O.tid=0 AND O.status=1 AND O.notify<>0 AND '.CollectionNotify::scope());
            $add('业务通知积压',true,'待完成 '.(int)$r['pending'].' 笔；停止重试 '.(int)$r['stopped'].' 笔；付款超过 1 小时 '.(int)$r['old'].' 笔');
            if((int)$r['pending']>0)$warnings[]='存在未完成通知，请在支付异常核对查看；验收不会主动发送通知';
        }
        return ['ok'=>$code===0,'exit_code'=>$code,'checks'=>$checks,'warnings'=>$warnings];
    }
}
