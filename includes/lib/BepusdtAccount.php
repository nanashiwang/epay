<?php
namespace lib;

final class BepusdtAccount
{
    private $db;
    public function __construct($db) { $this->db=$db; }
    public function owned($uid,$id,$lock=false)
    {
        $r=$this->db->getRow('SELECT A.*,S.name,S.status,S.channel FROM pre_bepusdt_account A JOIN pre_subchannel S ON S.id=A.id AND S.uid=A.uid WHERE A.uid=:uid AND A.id=:id AND A.deleted_at IS NULL'.($lock?' FOR UPDATE':''),[':uid'=>$uid,':id'=>$id]);
        if (!$r) throw new \InvalidArgumentException('收款账号不存在');
        return $r;
    }
    public function audit($uid,$id,$action,$detail='') { $this->db->insert('bepusdt_audit',['uid'=>$uid,'account_id'=>$id,'action'=>$action,'detail'=>$detail,'created_at'=>'NOW()']); }
    public function config(array $r)
    {
        return ['appurl'=>$r['endpoint'],'appkey'=>GatewaySecrets::decrypt($r['secret'],$r['uid'])['token'],'address'=>$r['address'],'timeout'=>(int)$r['timeout'],'bepusdt_managed'=>1,'account_id'=>(int)$r['id'],'owner_uid'=>(int)$r['uid'],'mode'=>1,'costrate'=>0];
    }
    public function save($uid,array $input,$parent)
    {
        $endpoint=GatewayHttp::endpoint($input['endpoint']??'');
        GatewayHttp::addresses(parse_url($endpoint,PHP_URL_HOST));
        $id=(int)($input['id']??0); $name=trim((string)($input['name']??'')); $address=trim((string)($input['address']??''));
        if ($name==='' || mb_strlen($name)>30) throw new \InvalidArgumentException('账号名称请填写 1–30 个字');
        if ($address!=='' && !preg_match('/\AT[1-9A-HJ-NP-Za-km-z]{33}\z/D',$address)) throw new \InvalidArgumentException('请填写正确的 TRON 收款地址，或留空由网关分配');
        $timeout=filter_var($input['timeout']??1200,FILTER_VALIDATE_INT);
        if ($timeout<120 || $timeout>3600) throw new \InvalidArgumentException('付款窗口须为 120–3600 秒');
        return DbTransaction::run($this->db,function() use($uid,$input,$parent,$id,$name,$endpoint,$address,$timeout) {
            $policy=MerchantSubscription::requireActive($this->db,$uid,true);
            $old=$id?$this->owned($uid,$id,true):null;
            if ($old && (int)$old['status']!==0) throw new \InvalidArgumentException('请先停用账号再编辑');
            $token=trim((string)($input['token']??''));
            if ($token==='' && $old) $token=GatewaySecrets::decrypt($old['secret'],$uid)['token'];
            if (!preg_match('/\A[\x21-\x7e]{8,256}\z/D',$token)) throw new \InvalidArgumentException('请填写 8–256 位 API Token');
            $p=$this->db->getRow('SELECT C.*,T.status type_status FROM pre_channel C JOIN pre_type T ON T.id=C.type WHERE C.id=:id',[':id'=>$parent]);
            if (!$p || (int)$p['type_status']!==1 || $p['plugin']!=='bepusdt' || (int)$p['mode']!==1 || (int)$p['status']!==0 || (json_decode($p['config'],true)['bepusdt_managed']??null)!==1) throw new \RuntimeException('BEpusdt 自助收款模板未配置');
            if (!$id) {
                $count=$this->db->getColumn('SELECT COUNT(*) FROM pre_bepusdt_account WHERE uid=:uid AND deleted_at IS NULL',[':uid'=>$uid]);
                if ($count>=$policy['limit']) throw new \InvalidArgumentException('已达到套餐账号数量，请归档闲置账号或联系管理员调整套餐');
                $id=$this->db->insert('subchannel',['uid'=>$uid,'channel'=>$parent,'name'=>$name,'status'=>0,'info'=>'{}','addtime'=>'NOW()','usetime'=>'NOW()']);
                $this->db->insert('bepusdt_account',['id'=>$id,'uid'=>$uid,'endpoint'=>$endpoint,'secret'=>GatewaySecrets::encrypt(['token'=>$token],$uid),'address'=>$address,'timeout'=>$timeout,'created_at'=>'NOW()']);
            } else {
                $this->db->update('subchannel',['name'=>$name],['id'=>$id]);
                $this->db->update('bepusdt_account',['endpoint'=>$endpoint,'secret'=>GatewaySecrets::encrypt(['token'=>$token],$uid),'address'=>$address,'timeout'=>$timeout,'verified_at'=>null,'tested_at'=>null,'last_error'=>null],['id'=>$id]);
            }
            $this->audit($uid,$id,$old?'修改配置':'新增账号'); return $id;
        });
    }
    public function listing($uid)
    {
        return $this->db->getAll('SELECT A.id,A.endpoint,A.address,A.timeout,A.verified_at,A.tested_at,A.last_callback,A.last_error,S.name,S.status,R.account_id default_id FROM pre_bepusdt_account A JOIN pre_subchannel S ON S.id=A.id AND S.uid=A.uid LEFT JOIN pre_bepusdt_route R ON R.account_id=A.id AND R.uid=A.uid WHERE A.uid=:uid AND A.deleted_at IS NULL ORDER BY A.id DESC',[':uid'=>$uid]);
    }
    public function verify($uid,$id)
    {
        MerchantSubscription::requireActive($this->db,$uid);
        $r=$this->owned($uid,$id); $c=$this->config($r);
        if ($r['verified_at'] && time()-strtotime($r['verified_at'])<15) throw new \InvalidArgumentException('请稍等 15 秒再校验');
        try { (new BepusdtClient($c['appurl'],$c['appkey']))->probe(); }
        catch (\Throwable $e) { $this->db->update('bepusdt_account',['verified_at'=>null,'last_error'=>'接口校验失败，请检查地址、证书及 Token'],['id'=>$id,'secret'=>$r['secret']]); throw $e; }
        // Do not approve a concurrently replaced credential.
        if (!$this->db->exec('UPDATE pre_bepusdt_account SET verified_at=NOW(),last_error=NULL WHERE id=:id AND secret=:secret',[':id'=>$id,':secret'=>$r['secret']])) throw new \RuntimeException('配置已变更，请重新校验');
        $this->audit($uid,$id,'接口校验通过');
    }
    public function action($uid,$id,$action)
    {
        return DbTransaction::run($this->db,function() use($uid,$id,$action) {
            [$u]=MerchantSubscription::current($this->db,$uid,true);
            $r=$this->owned($uid,$id,true);
            if (in_array($action,['enable','default'],true)) {
                $policy=MerchantSubscription::requireActive($this->db,$uid);
                if (!$r['verified_at'] || !$r['tested_at']) throw new \InvalidArgumentException('请先通过接口校验和测试订单到账验收');
                $enabled=$this->db->getColumn('SELECT COUNT(*) FROM pre_bepusdt_account A JOIN pre_subchannel S ON S.id=A.id WHERE A.uid=:uid AND A.deleted_at IS NULL AND S.status=1 AND A.id<>:id',[':uid'=>$uid,':id'=>$id]);
                if ($enabled>=$policy['limit']) throw new \InvalidArgumentException('已达到套餐启用账号数量，请先停用其它账号');
            }
            if ($action==='enable') $this->db->update('subchannel',['status'=>1],['id'=>$id]);
            elseif ($action==='disable') $this->db->update('subchannel',['status'=>0],['id'=>$id]);
            elseif ($action==='default') {
                if ((int)$r['status']!==1) throw new \InvalidArgumentException('请先启用账号');
                $type=$this->db->findColumn('channel','type',['id'=>$r['channel']]);
                $this->db->exec('INSERT INTO pre_bepusdt_route (uid,type,account_id) VALUES (:uid,:type,:id) ON DUPLICATE KEY UPDATE account_id=VALUES(account_id)',[':uid'=>$uid,':type'=>$type,':id'=>$id]);
            } elseif ($action==='unroute') $this->db->delete('bepusdt_route',['uid'=>$uid,'account_id'=>$id]);
            elseif ($action==='archive') {
                if ($r['status'] || $this->db->find('bepusdt_route','uid',['uid'=>$uid,'account_id'=>$id])) throw new \InvalidArgumentException('请先停用并取消默认');
                $this->db->update('bepusdt_account',['deleted_at'=>'NOW()'],['id'=>$id]);
            } else throw new \InvalidArgumentException('未知操作');
            $this->audit($uid,$id,$action);
        });
    }
    public static function route($db,$uid,$type,$name,$money)
    {
        if (empty($GLOBALS['conf']['bepusdt_parent']) || !empty($GLOBALS['platform_payment'])) return null;
        $route=$db->find('bepusdt_route','*',['uid'=>$uid,'type'=>$type]);
        if (!$route) return null;
        try {
            $policy=MerchantSubscription::requireActive($db,$uid);
            if (isset($policy['channels'][$type]) && (int)$policy['channels'][$type]['channel']===0) return false;
            $r=(new self($db))->owned($uid,$route['account_id']);
            $enabled=$db->getColumn('SELECT COUNT(*) FROM pre_bepusdt_account A JOIN pre_subchannel S ON S.id=A.id WHERE A.uid=:uid AND A.deleted_at IS NULL AND S.status=1',[':uid'=>$uid]);
            if ((int)$r['status']!==1 || !$r['verified_at'] || !$r['tested_at'] || $enabled>$policy['limit']) return false;
            $p=$db->getRow('SELECT C.*,T.status type_status FROM pre_channel C JOIN pre_type T ON T.id=C.type WHERE C.id=:id',[':id'=>$r['channel']]);
            if (!$p || (int)$p['type_status']!==1 || $p['plugin']!=='bepusdt' || (int)$p['mode']!==1 || (int)$p['type']!==(int)$type || !empty($p['daystatus']) || $name!=='usdt.trc20' || (int)$p['status']!==0 || (json_decode($p['config'],true)['bepusdt_managed']??null)!==1) return false;
            if ($money>0 && ((!empty($p['paymin']) && $money<$p['paymin']) || (!empty($p['paymax']) && $money>$p['paymax']))) return false;
            if (!empty($p['timestart']) || !empty($p['timestop'])) {
                $hour=(int)date('H');
                if ($p['timestart']<$p['timestop'] ? ($hour<$p['timestart'] || $hour>$p['timestop']) : ($hour<$p['timestart'] && $hour>$p['timestop'])) return false;
            }
            return ['typeid'=>$type,'typename'=>$name,'plugin'=>'bepusdt','channel'=>$p['id'],'subchannel'=>$r['id'],'rate'=>100,'mode'=>1,'apptype'=>$p['apptype'],'paymin'=>$p['paymin'],'paymax'=>$p['paymax'],'bepusdt_managed'=>1];
        } catch (\Throwable $e) { return false; }
    }
}
