<?php
namespace lib;

final class MerchantOperations
{
    public static function table($db,$name)
    {
        if (!preg_match('/\A[a-z_]+\z/D',$name)) throw new \InvalidArgumentException('Invalid table');
        return (bool)$db->getColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='pre_".$name."'");
    }

    // Historical classification must not change when a merchant changes plans.
    public static function referralWhere($db)
    {
        $where=['O.tid<>3'];
        foreach (['merchant_channel_order','subscription_purchase'] as $table) if (self::table($db,$table))
            $where[]='NOT EXISTS (SELECT 1 FROM pre_'.$table.' X WHERE X.trade_no=O.trade_no)';
        if (self::table($db,'bepusdt_order')) $where[]='NOT EXISTS (SELECT 1 FROM pre_bepusdt_order X WHERE X.trade_no=O.trade_no AND X.account_id>0)';
        if (self::table($db,'collection_account')) $where[]='NOT (O.realmoney=O.getmoney AND EXISTS (SELECT 1 FROM pre_collection_account X WHERE X.id=O.subchannel AND X.uid=O.uid))';
        return implode(' AND ',$where);
    }

    public static function reminder(array $user,$now=null)
    {
        if (empty($user['endtime'])) return null;
        $seconds=strtotime($user['endtime'])-($now??time());
        $days=(int)ceil($seconds/86400);
        if ($seconds<=0) return ['stage'=>'expired','days'=>0,'text'=>'套餐已到期，新收款已暂停。历史订单和配置仍可查看，请及时续期。'];
        if ($days>7) return null;
        return ['stage'=>$days<=1?'1':($days<=3?'3':'7'),'days'=>$days,'text'=>'套餐将在 '.$days.' 天内到期，请及时续期，避免新收款中断。'];
    }

    public static function entitlementVersion(array $u)
    {
        return hash('sha256',$u['uid'].'|'.$u['gid'].'|'.($u['endtime']??''));
    }

    public static function resolve($db,$trade,$action,$reason,$reference,$expected,$actor,$confirmed=false)
    {
        if (!is_string($trade) || !preg_match('/\A[0-9]{10,32}\z/D',$trade) || !in_array($action,['apply','close'],true)) throw new \InvalidArgumentException('处理请求不正确');
        foreach ([$reason,$reference,$expected,$actor] as $s) if (!is_string($s)) throw new \InvalidArgumentException('处理参数不正确');
        $reason=trim($reason); $reference=trim($reference);
        if (mb_strlen($reason)<5 || mb_strlen($reason)>500 || mb_strlen($reference)>200 || $actor==='' || mb_strlen($actor)>100) throw new \InvalidArgumentException('请填写 5–500 字处理原因，凭证编号不超过 200 字');
        if ($action==='close' && $reference==='') throw new \InvalidArgumentException('登记线下处理必须填写凭证编号');
        if (!$confirmed) throw new \InvalidArgumentException('请确认本次处理对商户权益的影响');
        return DbTransaction::run($db,function() use($db,$trade,$action,$reason,$reference,$expected,$actor) {
            $o=$db->getRow('SELECT * FROM pre_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$trade]);
            $e=$db->getRow('SELECT * FROM pre_subscription_event WHERE trade_no=:trade FOR UPDATE',[':trade'=>$trade]);
            $p=$db->find('subscription_purchase','*',['trade_no'=>$trade]);
            if (!$o || !MerchantSubscription::isPurchase($o) || (int)$o['status']!==1 || !$e || !$p || $e['state']!=='review' || $e['uid']!=$p['uid'] || $e['gid']!=$p['gid']) throw new \InvalidArgumentException('该订单不是待处理的已付款套餐，或已被处理');
            $snapshot=json_decode($o['param'],true);
            if ($snapshot['uid']!=$e['uid'] || $snapshot['gid']!=$e['gid'] || $snapshot['months']!=$e['months'] || BepusdtClient::decimal($o['money'],2)!==BepusdtClient::decimal($e['money'],2)) throw new \RuntimeException('套餐付款凭证不一致');
            [$u,$g]=MerchantSubscription::current($db,$e['uid'],true);
            if (!hash_equals(self::entitlementVersion($u),$expected)) throw new \InvalidArgumentException('商户权益已变化，请刷新核对后重新处理');
            $end=$u['endtime']; $gid=$u['gid'];
            if ($action==='apply') {
                $target=$db->find('group','*',['gid'=>$e['gid']]);
                if (!$target || !MerchantSubscription::policy($target)['enabled'] || (int)$u['status']!==1) throw new \InvalidArgumentException('目标套餐已不可用或商户已停用，请核对后处理');
                $base=$u['gid']==$e['gid'] && $u['endtime'] && strtotime($u['endtime'])>time()?$u['endtime']:date('Y-m-d H:i:s');
                $end=MerchantSubscription::addMonths($base,(int)$e['months']); $gid=$e['gid'];
                $db->update('user',['gid'=>$gid,'endtime'=>$end],['uid'=>$u['uid']]);
            }
            $db->insert('subscription_resolution',['trade_no'=>$trade,'uid'=>$e['uid'],'action'=>$action,'reason'=>$reason,'reference'=>$reference,'actor'=>$actor,'old_gid'=>$u['gid'],'new_gid'=>$gid,'old_endtime'=>$u['endtime'],'new_endtime'=>$end,'created_at'=>'NOW()']);
            $db->update('subscription_event',['state'=>$action==='apply'?'applied':'closed','new_endtime'=>$action==='apply'?$end:null],['trade_no'=>$trade]);
            return $action==='apply'?'所购套餐已生效，处理记录已保存':'已登记线下处理结果；未转账、退款或变更商户权益';
        });
    }

    public static function reviewList($db,$page=1,$state='review')
    {
        $page=max(1,min(10000,(int)$page));
        $where=$state==='review'?"E.state='review'":"E.state IN ('applied','closed') AND R.trade_no IS NOT NULL";
        $rows=$db->getAll('SELECT E.*,P.created_at purchased_at,U.gid current_gid,U.endtime current_endtime,G.name current_name,T.name purchased_name,R.action resolved_action,R.reason,R.reference,R.actor,R.created_at resolved_at,R.old_gid resolved_old_gid,R.new_gid resolved_new_gid,R.old_endtime resolved_old_endtime,R.new_endtime resolved_new_endtime FROM pre_subscription_event E JOIN pre_subscription_purchase P ON P.trade_no=E.trade_no JOIN pre_user U ON U.uid=E.uid LEFT JOIN pre_group G ON G.gid=U.gid LEFT JOIN pre_group T ON T.gid=E.gid LEFT JOIN pre_subscription_resolution R ON R.trade_no=E.trade_no WHERE '.$where.' ORDER BY E.created_at DESC,E.trade_no DESC LIMIT '.(($page-1)*20).',21');
        foreach ($rows as &$r) $r['version']=self::entitlementVersion(['uid'=>$r['uid'],'gid'=>$r['current_gid'],'endtime'=>$r['current_endtime']]);
        return ['rows'=>array_slice($rows,0,20),'more'=>count($rows)>20];
    }

    public static function sendReminders($db,callable $send,$now=null)
    {
        $now=$now??time();
        if (!self::table($db,'subscription_reminder')) return 0;
        if ((int)$db->getColumn("SELECT GET_LOCK('epay:subscription-reminders',0)")!==1) return 0;
        $sent=0; $attempted=0;
        $previousMode=$db->db->getAttribute(\PDO::ATTR_ERRMODE);
        $db->db->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        try {
            $users=$db->getAll('SELECT U.uid,U.gid,U.endtime,U.email,G.name,G.config FROM pre_user U JOIN pre_group G ON G.gid=U.gid WHERE U.status=1 AND U.endtime BETWEEN :lower AND :upper AND U.email IS NOT NULL AND U.email<>\'\' ORDER BY U.uid', [':lower'=>date('Y-m-d H:i:s',$now-7*86400),':upper'=>date('Y-m-d H:i:s',$now+7*86400)]);
            foreach ($users as $u) {
                if (!MerchantSubscription::policy($u)['enabled'] || !filter_var($u['email'],FILTER_VALIDATE_EMAIL)) continue;
                $notice=self::reminder($u,$now); if (!$notice) continue;
                $key=['uid'=>$u['uid'],'endtime'=>$u['endtime'],'stage'=>$notice['stage']];
                $old=$db->find('subscription_reminder','*',$key);
                if ($old && ($old['sent_at'] || ($old['retry_at'] && strtotime($old['retry_at'])>$now))) continue;
                // Refresh immediately before delivery so a completed renewal is not sent as expired.
                [$fresh,$group,$policy]=MerchantSubscription::current($db,$u['uid']);
                if (!$policy['enabled'] || $fresh['endtime']!==$u['endtime'] || (int)$fresh['status']!==1) continue;
                $attempts=(int)($old['attempts']??0)+1;
                if (!$old) $db->insert('subscription_reminder',$key+['attempts'=>0]);
                $db->update('subscription_reminder',['attempts'=>$attempts,'retry_at'=>date('Y-m-d H:i:s',$now+3600)],$key);
                try { $ok=$send($fresh,$group,$notice)===true; } catch (\Throwable $e) { $ok=false; }
                if ($ok) { $db->update('subscription_reminder',['sent_at'=>date('Y-m-d H:i:s',$now),'retry_at'=>null],$key); $sent++; }
                if (++$attempted>=50) break;
            }
        } finally {
            try { $db->getColumn("SELECT RELEASE_LOCK('epay:subscription-reminders')"); }
            finally { $db->db->setAttribute(\PDO::ATTR_ERRMODE,$previousMode); }
        }
        return $sent;
    }
}
