<?php
namespace lib;

final class WorkerRuntime
{
    public static function beat($db,$name,$state='ok')
    {
        if (!in_array($name,['collection','subscription'],true) || !in_array($state,['ok','error','disabled'],true)) throw new \InvalidArgumentException('Invalid worker state');
        $data=json_encode(['at'=>time(),'pid'=>getmypid(),'state'=>$state]);
        $db->exec('INSERT INTO pre_cache (k,v,expire) VALUES (:name,:data,0) ON DUPLICATE KEY UPDATE v=VALUES(v),expire=0',[':name'=>'worker_'.$name,':data'=>$data]);
    }

    public static function read($db,$name)
    {
        if (!in_array($name,['collection','subscription'],true)) throw new \InvalidArgumentException('Invalid worker');
        return json_decode($db->getColumn('SELECT v FROM pre_cache WHERE k=:name',[':name'=>'worker_'.$name])?:'null',true);
    }
}
