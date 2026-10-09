<?php
namespace lib;

final class MerchantSubscription
{
    public static function policy(array $group)
    {
        $config=json_decode($group['config']??'{}',true)?:[];
        return ['enabled'=>(int)($config['bepusdt_enabled']??0)===1,'limit'=>max(1,min(20,(int)($config['bepusdt_accounts']??1)))];
    }

    public static function current($db,$uid,$lock=false)
    {
        $user=$db->getRow('SELECT * FROM pre_user WHERE uid=:uid'.($lock?' FOR UPDATE':''),[':uid'=>$uid]);
        if (!$user) throw new \InvalidArgumentException('商户不存在');
        $group=$db->find('group','*',['gid'=>$user['gid']])?:[];
        $policy=self::policy($group);
        $policy['active']=$policy['enabled'] && !empty($user['endtime']) && strtotime($user['endtime'])>time();
        return [$user,$group,$policy];
    }

    public static function requireActive($db,$uid,$lock=false)
    {
        [$user,$group,$policy]=self::current($db,$uid,$lock);
        if (!$policy['active']) throw new \InvalidArgumentException('当前套餐未开通 BEpusdt 或已到期，请先购买或续期');
        if ((int)$user['status']!==1 || (int)$user['pay']!==1 || (!empty($GLOBALS['conf']['cert_force']) && !$user['cert'])) throw new \InvalidArgumentException('请先完成商户审核及实名认证');
        $info=!empty($group['info'])?$group['info']:$db->findColumn('group','info',['gid'=>0]);
        $policy['channels']=json_decode($info?:'{}',true)?:[];
        return $policy;
    }

    public static function addMonths($base,$months)
    {
        if ($months<1 || $months>3600) throw new \InvalidArgumentException('订阅周期不正确');
        $d=new \DateTimeImmutable($base,new \DateTimeZone('Asia/Shanghai'));
        $target=$d->modify('first day of this month')->modify('+'.$months.' months');
        return $target->setDate((int)$target->format('Y'),(int)$target->format('m'),min((int)$d->format('d'),(int)$target->format('t')))->format('Y-m-d H:i:s');
    }

    public static function available(array $user,array $group)
    {
        if (empty($group['isbuy'])) throw new \InvalidArgumentException('当前套餐未上架');
        if (!empty($group['visible']) && !in_array((string)$user['gid'],explode(',',$group['visible']),true)) throw new \InvalidArgumentException('当前账号不可购买此套餐');
        if (!empty($user['gid']) && (int)$user['gid']!==(int)$group['gid'] && (!empty($user['endtime'])?strtotime($user['endtime'])>time():true)) throw new \InvalidArgumentException('当前套餐仍有效，请联系管理员处理套餐切换');
        if ((int)$group['expire']<1 || (int)$group['expire']>120) throw new \InvalidArgumentException('BEpusdt 套餐须设置 1–120 个月的周期');
    }

    public static function purchase($db,$uid,$gid,$num,$typeid,$requestKey)
    {
        global $conf,$siteurl,$clientip;
        if (empty($conf['group_buy']) || empty($conf['bepusdt_parent'])) throw new \InvalidArgumentException('订阅购买尚未开通');
        if ($typeid<0 || $num<1 || $num>30) throw new \InvalidArgumentException('购买数量须为 1–30');
        return DbTransaction::run($db,function() use($db,$uid,$gid,$num,$typeid,$requestKey,$conf,$siteurl,$clientip) {
            [$user]=self::current($db,$uid,true);
            if ((int)$user['status']!==1) throw new \InvalidArgumentException('商户状态不可购买套餐');
            $requestKey=hash('sha256',$uid.':'.$requestKey);
            $existing=$db->getRow('SELECT P.trade_no,O.status FROM pre_subscription_purchase P JOIN pre_order O ON O.trade_no=P.trade_no WHERE P.request_key=:key',[':key'=>$requestKey]);
            if ($existing) return ['code'=>(int)$existing['status']===1?1:0,'msg'=>'请查看已有订阅订单','url'=>'../submit2.php?typeid='.$typeid.'&trade_no='.$existing['trade_no']];
            $group=$db->find('group','*',['gid'=>$gid]);
            if (!$group || !self::policy($group)['enabled']) throw new \InvalidArgumentException('订阅套餐不存在');
            self::available($user,$group);
            $money=number_format(round((float)$group['price']*$num,2),2,'.','');
            if ((float)$money<=0 || (float)$money>99999999.99) throw new \InvalidArgumentException('套餐价格不正确');
            $months=$num*(int)$group['expire'];
            $trade=date('YmdHis').random_int(10000,99999);
            $param=['subscription_v2'=>1,'uid'=>$uid,'gid'=>$gid,'months'=>$months,'price'=>$money,'name'=>$group['name']];
            $url=$siteurl.'user/groupbuy.php?ok=1&trade_no='.$trade;
            $platform=$db->find('user','uid,gid,pay,status',['uid'=>$conf['reg_pay_uid']]);
            if (!$platform || (int)$platform['status']!==1 || (int)$platform['pay']!==1) throw new \InvalidArgumentException('平台订阅收款商户尚未配置');
            if ($typeid>0) {
                $previous=$GLOBALS['platform_payment']??false; $GLOBALS['platform_payment']=true;
                try { $types=Channel::getTypes($platform['uid'],$platform['gid']); }
                finally { $GLOBALS['platform_payment']=$previous; }
                if (!isset($types[$typeid])) throw new \InvalidArgumentException('该平台付款方式不可用');
            }
            $db->insert('order',['trade_no'=>$trade,'out_trade_no'=>$trade,'uid'=>$platform['uid'],'tid'=>4,'addtime'=>'NOW()','name'=>'订阅-'.$group['name'],'money'=>$money,'realmoney'=>$money,'getmoney'=>$money,'notify_url'=>$url,'return_url'=>$url,'domain'=>getdomain($url),'ip'=>$clientip??'','status'=>0,'param'=>json_encode($param,JSON_UNESCAPED_UNICODE)]);
            $db->insert('subscription_purchase',['trade_no'=>$trade,'uid'=>$uid,'gid'=>$gid,'request_key'=>$requestKey,'created_at'=>'NOW()']);
            if ($typeid===0) {
                if ((float)$user['money']<(float)$money) throw new \InvalidArgumentException('余额不足，请选择其他支付方式');
                $new=number_format((float)$user['money']-(float)$money,2,'.','');
                $db->update('user',['money'=>$new],['uid'=>$uid]);
                $db->insert('record',['uid'=>$uid,'action'=>2,'money'=>$money,'oldmoney'=>$user['money'],'newmoney'=>$new,'type'=>'购买会员','trade_no'=>$trade,'date'=>'NOW()']);
                self::settle($db,$trade,'balance');
                return ['code'=>1,'msg'=>'套餐已生效'];
            }
            return ['code'=>0,'msg'=>'订单已创建','url'=>'../submit2.php?typeid='.$typeid.'&trade_no='.$trade];
        });
    }

    public static function isPurchase(array $order)
    {
        return (int)($order['tid']??0)===4 && (json_decode($order['param']??'{}',true)['subscription_v2']??null)===1;
    }

    public static function settle($db,$trade,$providerId)
    {
        return DbTransaction::run($db,function() use($db,$trade,$providerId) {
            $o=$db->getRow('SELECT * FROM pre_order WHERE trade_no=:trade FOR UPDATE',[':trade'=>$trade]);
            if (!$o || !self::isPurchase($o)) throw new \InvalidArgumentException('订阅订单不存在');
            if ($db->find('subscription_event','trade_no',['trade_no'=>$trade])) return;
            if (!in_array((int)$o['status'],[0,1,4],true)) throw new \RuntimeException('订阅订单状态不允许确认');
            $p=json_decode($o['param'],true);
            $purchase=$db->find('subscription_purchase','*',['trade_no'=>$trade]);
            if (!empty($o['subchannel'])) {
                $managed=$db->find('bepusdt_account','id',['id'=>$o['subchannel']]) || $db->find('collection_account','id',['id'=>$o['subchannel']]);
                if ($managed) throw new \InvalidArgumentException('平台套餐不得使用商户自助收款凭证');
            }
            if (!$purchase || (int)$purchase['uid']!==(int)$p['uid'] || BepusdtClient::decimal($p['price'],2)!==BepusdtClient::decimal($o['money'],2)) throw new \RuntimeException('订阅订单快照不一致');
            [$u]=self::current($db,$p['uid'],true);
            $base=!empty($u['endtime']) && strtotime($u['endtime'])>time()?$u['endtime']:date('Y-m-d H:i:s');
            $state='applied';
            if ((int)$u['gid']!==0 && (int)$u['gid']!==(int)$p['gid'] && (empty($u['endtime']) || strtotime($u['endtime'])>time())) $state='review';
            $end=self::addMonths($base,(int)$p['months']);
            $db->insert('subscription_event',['trade_no'=>$trade,'uid'=>$p['uid'],'gid'=>$p['gid'],'months'=>$p['months'],'money'=>$o['money'],'old_endtime'=>$u['endtime'],'new_endtime'=>$state==='applied'?$end:null,'state'=>$state,'created_at'=>'NOW()']);
            if ($state==='applied') $db->update('user',['gid'=>$p['gid'],'endtime'=>$end],['uid'=>$p['uid']]);
            $db->update('order',['status'=>1,'api_trade_no'=>$providerId,'endtime'=>'NOW()','date'=>'CURDATE()'],['trade_no'=>$trade]);
        });
    }
}
