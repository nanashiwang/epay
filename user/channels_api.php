<?php
require '../includes/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function channels_reply($data) { echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if ($islogin2!=1) { http_response_code(401); channels_reply(['code'=>-3,'msg'=>'请先登录']); }
$uid=(int)$userrow['uid']; $svc=new \lib\MerchantChannel($DB); $act=$_GET['act']??'list';
try {
    if (!\lib\MerchantChannel::installed()) throw new InvalidArgumentException('商户自助通道尚未开通，请联系管理员完成初始化');
    if (!is_string($act)) throw new InvalidArgumentException('请求不正确');
    if (!in_array($act,['list','records'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !is_string($_POST['csrf']??null) || empty($_SESSION['channels_csrf']) || !hash_equals($_SESSION['channels_csrf'],$_POST['csrf'])) { http_response_code(403); throw new InvalidArgumentException('页面验证已过期，请刷新重试'); }
        if ((int)$userrow['status']!==1) throw new InvalidArgumentException('商户当前不可操作支付通道');
    }
    $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);
    if ($id===false || $id<0) throw new InvalidArgumentException('通道编号不正确');
    switch ($act) {
        case 'list':
            [$u,$g,$policy]=\lib\MerchantSubscription::current($DB,$uid);
            $types=$DB->getAll('SELECT T.id,T.name,T.showname FROM pre_type T WHERE T.status=1 AND EXISTS (SELECT 1 FROM pre_merchant_channel_template M WHERE M.type=T.id) ORDER BY T.id');
            channels_reply(['code'=>0,'data'=>$svc->listing($uid),'catalog'=>\lib\MerchantChannelCatalog::all(),'types'=>$types,'subscription'=>['name'=>$g['name']??'默认套餐','endtime'=>$u['endtime'],'active'=>$policy['active'] && $policy['self_service'],'limit'=>$policy['limit'],'used'=>\lib\MerchantChannel::count($DB,$uid)],'special'=>['collection'=>!empty($conf['collection_parent']),'bepusdt'=>!empty($conf['bepusdt_parent'])]]);
        case 'save':
            if (!is_array($_POST['config']??null)) throw new InvalidArgumentException('请填写支付配置');
            $id=$svc->save($uid,$_POST); channels_reply(['code'=>0,'id'=>$id,'msg'=>'配置已加密保存，请测试到账后启用']);
        case 'enable': case 'disable': case 'default': case 'unroute': case 'archive':
            $svc->action($uid,$id,$act); channels_reply(['code'=>0,'msg'=>'设置已更新']);
        case 'test':
            $amount=\lib\BepusdtClient::decimal($_POST['amount']??'0.01',2);
            if ((float)$amount<0.01 || (float)$amount>100) throw new InvalidArgumentException('测试金额须为 0.01–100.00 元');
            $trade=\lib\DbTransaction::run($DB,function() use($DB,$svc,$uid,$id,$amount,$siteurl,$clientip) {
                \lib\MerchantChannel::policy($DB,$uid,true); $r=$svc->owned($uid,$id,true); $svc->template($r['plugin'],$r['type']);
                $old=$DB->getRow('SELECT O.trade_no FROM pre_order O JOIN pre_merchant_channel_order M ON M.trade_no=O.trade_no WHERE O.uid=:uid AND O.subchannel=:id AND O.tid=3 AND O.status=0 AND M.revision=:rev AND O.addtime>DATE_SUB(NOW(),INTERVAL 1 MINUTE) ORDER BY O.addtime DESC LIMIT 1',[':uid'=>$uid,':id'=>$id,':rev'=>$r['revision']]);
                if ($old) return $old['trade_no'];
                $trade=date('YmdHis').random_int(10000,99999); $url=$siteurl.'user/channels.php?paid='.$trade;
                $DB->insert('order',['trade_no'=>$trade,'out_trade_no'=>'channel-test-'.$trade,'uid'=>$uid,'tid'=>3,'type'=>$r['type'],'channel'=>$r['channel'],'subchannel'=>$id,'name'=>'支付通道测试','money'=>$amount,'realmoney'=>$amount,'getmoney'=>$amount,'addtime'=>'NOW()','status'=>0,'notify_url'=>$url,'return_url'=>$url,'domain'=>$_SERVER['HTTP_HOST'],'ip'=>$clientip,'param'=>'{"merchant_channel_test":1}']);
                $o=$DB->find('order','*',['trade_no'=>$trade]);
                \lib\MerchantChannel::prepare($DB,$o,$svc->config($r),'submit');
                $svc->audit($uid,$id,'创建测试订单',$trade); return $trade;
            });
            channels_reply(['code'=>0,'url'=>'/pay/submit/'.$trade.'/','msg'=>'测试订单已创建，付款后请返回刷新到账状态']);
        case 'retry':
            $trade=$_POST['trade_no']??'';
            if (!is_string($trade)) throw new InvalidArgumentException('订单号不正确');
            $o=$DB->getRow('SELECT O.trade_no FROM pre_order O JOIN pre_merchant_channel_order M ON M.trade_no=O.trade_no AND M.uid=O.uid WHERE O.uid=:uid AND O.trade_no=:trade AND O.status=1 AND O.tid=0',[':uid'=>$uid,':trade'=>$trade]);
            if (!$o) throw new InvalidArgumentException('已付款业务订单不存在');
            $last=$DB->getColumn('SELECT MAX(created_at) FROM pre_collection_notify WHERE trade_no=:trade',[':trade'=>$trade]);
            if ($last && time()-strtotime($last)<15) throw new InvalidArgumentException('请稍等 15 秒后重试');
            $ok=\lib\CollectionNotify::retry($DB,$trade,true); channels_reply(['code'=>0,'msg'=>$ok?'通知成功':'通知未成功，请查看记录']);
        case 'records':
            $kind=$_GET['kind']??'orders'; $page=max(1,min(10000,(int)($_GET['page']??1))); $offset=($page-1)*20;
            if ($kind==='orders') $rows=$DB->getAll('SELECT M.trade_no,M.account_id,M.plugin,M.money,M.provider_id,M.created_at,M.paid_at,O.tid,O.notify FROM pre_merchant_channel_order M JOIN pre_order O ON O.trade_no=M.trade_no WHERE M.uid=:uid ORDER BY M.created_at DESC,M.trade_no DESC LIMIT '.$offset.',21',[':uid'=>$uid]);
            elseif ($kind==='notify') $rows=$DB->getAll('SELECT N.* FROM pre_collection_notify N JOIN pre_merchant_channel_order M ON M.trade_no=N.trade_no AND M.uid=N.uid WHERE N.uid=:uid ORDER BY N.id DESC LIMIT '.$offset.',21',[':uid'=>$uid]);
            elseif ($kind==='audit') $rows=$DB->getAll('SELECT id,account_id,action,detail,created_at FROM pre_merchant_channel_audit WHERE uid=:uid ORDER BY id DESC LIMIT '.$offset.',21',[':uid'=>$uid]);
            else throw new InvalidArgumentException('记录类型不正确');
            channels_reply(['code'=>0,'data'=>array_slice($rows,0,20),'more'=>count($rows)>20]);
        default: throw new InvalidArgumentException('未知操作');
    }
} catch (InvalidArgumentException $e) { channels_reply(['code'=>-1,'msg'=>$e->getMessage()]); }
catch (Throwable $e) { channels_reply(['code'=>-1,'msg'=>'操作未完成，请检查配置或联系管理员；密钥不会回显']); }
