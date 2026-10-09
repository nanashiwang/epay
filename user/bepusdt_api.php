<?php
require '../includes/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function bepusdt_reply($data) { echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if ($islogin2!=1) { http_response_code(401); bepusdt_reply(['code'=>-3,'msg'=>'请先登录']); }
if (empty($conf['bepusdt_parent'])) bepusdt_reply(['code'=>-1,'msg'=>'BEpusdt 自助收款尚未开通']);
$uid=(int)$userrow['uid']; $svc=new \lib\BepusdtAccount($DB); $act=$_GET['act']??'list';
try {
    if (!is_string($act)) throw new InvalidArgumentException('请求不正确');
    foreach ($_POST as $value) if (!is_string($value)) throw new InvalidArgumentException('请求字段格式不正确');
    if (!in_array($act,['list','records'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !is_string($_POST['csrf']??null) || empty($_SESSION['bepusdt_csrf']) || !hash_equals($_SESSION['bepusdt_csrf'],$_POST['csrf'])) { http_response_code(403); throw new InvalidArgumentException('页面验证已过期，请刷新重试'); }
        if ((int)$userrow['status']!==1) throw new InvalidArgumentException('商户当前不可操作收款账号');
    }
    $id=(int)($_POST['id']??0);
    switch ($act) {
        case 'list':
            [$u,$g,$policy]=\lib\MerchantSubscription::current($DB,$uid);
            bepusdt_reply(['code'=>0,'data'=>$svc->listing($uid),'subscription'=>['name'=>$g['name']??'默认套餐','endtime'=>$u['endtime'],'active'=>$policy['active'],'limit'=>$policy['limit']]]);
        case 'save':
            $id=$svc->save($uid,$_POST,(int)$conf['bepusdt_parent']);
            bepusdt_reply(['code'=>0,'id'=>$id,'msg'=>'配置已保存，请校验接口并测试到账']);
        case 'verify':
            $svc->verify($uid,$id); bepusdt_reply(['code'=>0,'msg'=>'接口签名校验通过；请继续测试收款与到账回调']);
        case 'enable': case 'disable': case 'default': case 'unroute': case 'archive':
            $svc->action($uid,$id,$act); bepusdt_reply(['code'=>0,'msg'=>'设置已更新']);
        case 'test':
            \lib\MerchantSubscription::requireActive($DB,$uid);
            $r=$svc->owned($uid,$id);
            if (!$r['verified_at']) throw new InvalidArgumentException('请先校验接口');
            $amount=\lib\BepusdtClient::decimal($_POST['amount']??'1.00',2);
            if ((float)$amount<0.1 || (float)$amount>100) throw new InvalidArgumentException('测试金额须为 0.10–100.00 元');
            $trade=\lib\DbTransaction::run($DB,function() use($DB,$svc,$uid,$id,$r,$amount,$siteurl,$clientip) {
                $svc->owned($uid,$id,true);
                $old=$DB->getRow('SELECT O.trade_no FROM pre_order O WHERE O.uid=:uid AND O.subchannel=:id AND O.tid=3 AND O.status=0 AND O.addtime>DATE_SUB(NOW(),INTERVAL 1 HOUR) ORDER BY O.addtime DESC LIMIT 1',[':uid'=>$uid,':id'=>$id]);
                if ($old) return $old['trade_no'];
                $trade=date('YmdHis').random_int(10000,99999); $url=$siteurl.'user/bepusdt.php?paid='.$trade;
                $type=$DB->findColumn('channel','type',['id'=>$r['channel']]);
                $DB->insert('order',['trade_no'=>$trade,'out_trade_no'=>'bepusdt-test-'.$trade,'uid'=>$uid,'tid'=>3,'type'=>$type,'channel'=>$r['channel'],'subchannel'=>$id,'name'=>'USDT 收款测试','money'=>$amount,'realmoney'=>$amount,'getmoney'=>$amount,'addtime'=>'NOW()','status'=>0,'notify_url'=>$url,'return_url'=>$url,'domain'=>$_SERVER['HTTP_HOST'],'ip'=>$clientip,'param'=>json_encode(['bepusdt_test'=>1])]);
                $svc->audit($uid,$id,'创建测试订单',$trade); return $trade;
            });
            bepusdt_reply(['code'=>0,'url'=>'/pay/submit/'.$trade.'/']);
        case 'retry':
            $trade=$_POST['trade_no']??'';
            $o=$DB->getRow('SELECT O.trade_no FROM pre_order O JOIN pre_bepusdt_order B ON B.trade_no=O.trade_no AND B.uid=O.uid WHERE O.uid=:uid AND O.trade_no=:trade AND O.status=1 AND O.tid=0 AND B.account_id>0',[':uid'=>$uid,':trade'=>$trade]);
            if (!$o) throw new InvalidArgumentException('已付款业务订单不存在');
            $last=$DB->getColumn('SELECT MAX(created_at) FROM pre_collection_notify WHERE trade_no=:trade',[':trade'=>$trade]);
            if ($last && time()-strtotime($last)<15) throw new InvalidArgumentException('请稍等 15 秒再重试');
            $ok=\lib\CollectionNotify::retry($DB,$trade,true);
            bepusdt_reply(['code'=>0,'msg'=>$ok?'业务通知成功':'业务通知未成功，请查看通知记录']);
        case 'records':
            $kind=$_GET['kind']??'orders'; $page=max(1,min(10000,(int)($_GET['page']??1))); $offset=($page-1)*20;
            if ($kind==='orders') $rows=$DB->getAll('SELECT B.trade_no,B.account_id,B.money,B.network,B.provider_id,B.address,B.coin_amount,B.expires_at,B.state,B.txid,B.last_error,B.created_at,B.paid_at,O.tid,O.notify FROM pre_bepusdt_order B JOIN pre_order O ON O.trade_no=B.trade_no WHERE B.uid=:uid AND B.account_id>0 ORDER BY B.created_at DESC,B.trade_no DESC LIMIT '.$offset.',21',[':uid'=>$uid]);
            elseif ($kind==='notify') $rows=$DB->getAll('SELECT N.* FROM pre_collection_notify N JOIN pre_bepusdt_order B ON B.trade_no=N.trade_no AND B.uid=N.uid WHERE N.uid=:uid AND B.account_id>0 ORDER BY N.id DESC LIMIT '.$offset.',21',[':uid'=>$uid]);
            elseif ($kind==='audit') $rows=$DB->getAll('SELECT id,account_id,action,detail,created_at FROM pre_bepusdt_audit WHERE uid=:uid ORDER BY id DESC LIMIT '.$offset.',21',[':uid'=>$uid]);
            else throw new InvalidArgumentException('记录类型不正确');
            bepusdt_reply(['code'=>0,'data'=>array_slice($rows,0,20),'more'=>count($rows)>20]);
        default: throw new InvalidArgumentException('未知操作');
    }
} catch (InvalidArgumentException $e) { bepusdt_reply(['code'=>-1,'msg'=>$e->getMessage()]); }
catch (RuntimeException $e) { bepusdt_reply(['code'=>-1,'msg'=>$e instanceof PDOException?'数据库操作失败，请联系管理员':$e->getMessage()]); }
catch (Throwable $e) { bepusdt_reply(['code'=>-1,'msg'=>'操作失败，请刷新后重试']); }
