<?php
require '../includes/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function collection_reply($data) { echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if ($islogin2!=1) { http_response_code(401); collection_reply(['code'=>-3,'msg'=>'登录已过期，请重新登录']); }
if (empty($conf['collection_parent'])) collection_reply(['code'=>-1,'msg'=>'自助收款服务尚未开通']);
$uid=(int)$userrow['uid'];
$svc=new \lib\CollectionAccount($DB);
$act=(string)($_GET['act']??'list');
try {
    if (!in_array($act,['list','records'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_SESSION['collection_csrf']) || !is_string($_POST['csrf']??null) || !hash_equals($_SESSION['collection_csrf'],$_POST['csrf'])) { http_response_code(403); throw new InvalidArgumentException('页面验证已过期，请刷新后重试'); }
        if ((int)$userrow['status']!==1) throw new InvalidArgumentException('当前商户不可操作收款账号');
    }
    $id=(int)($_POST['id']??0);
    switch($act) {
        case 'list': collection_reply(['code'=>0,'data'=>$svc->listing($uid)]);
        case 'save':
            $id=$svc->save($uid,$_POST,(int)$conf['collection_parent']);
            collection_reply(['code'=>0,'id'=>$id,'msg'=>'已保存，请校验接口后启用监测']);
        case 'verify':
            $svc->verify($uid,$id); collection_reply(['code'=>0,'msg'=>'账单接口校验通过。请启用监测，再测试核对收款方和到账。']);
        case 'enable': case 'disable': case 'default': case 'unroute': case 'archive':
            if (in_array($act,['enable','default'],true) && ((int)$userrow['pay']!==1 || (!empty($conf['cert_force']) && !$userrow['cert']))) throw new InvalidArgumentException('请先完成商户审核及实名认证');
            $svc->action($uid,$id,$act); collection_reply(['code'=>0,'msg'=>'设置已更新']);
        case 'decode':
            $file=$_FILES['qr']??null;
            if (!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024 || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('请选择不超过 5 MB 的二维码图片');
            $size=@getimagesize($file['tmp_name']);
            if (!$size || !in_array($size[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP],true) || $size[0]*$size[1]>12000000) throw new InvalidArgumentException('请使用 PNG、JPEG 或 WebP 图片，像素总数不超过 1200 万');
            require_once ROOT.'includes/qrcodedecoder/bootstrap.php';
            require_once ROOT.'plugins/alipaycode/inc/NativeQr.php';
            $image=imagecreatefromstring(file_get_contents($file['tmp_name']));
            if (!$image) throw new InvalidArgumentException('无法读取图片');
            if (max($size[0],$size[1])>1400) { $small=imagescale($image,(int)round($size[0]*1400/max($size[0],$size[1]))); imagedestroy($image); $image=$small; }
            try { $decoded=(new \Zxing\QrReader($image,\Zxing\QrReader::SOURCE_TYPE_RESOURCE,false))->text(); }
            finally { imagedestroy($image); }
            if (!$decoded) throw new InvalidArgumentException('未识别到二维码，请裁剪二维码区域或粘贴原生码地址');
            collection_reply(['code'=>0,'url'=>AlipayCodeNativeQr::codeUrl($decoded)]);
        case 'test':
            $r=$svc->owned($uid,$id);
            if ((int)$userrow['pay']!==1 || (!empty($conf['cert_force']) && !$userrow['cert'])) throw new InvalidArgumentException('请先完成商户审核及实名认证');
            if ($r['status']!=1 || \lib\CollectionAccount::health($r,time())!=='在线') throw new InvalidArgumentException('请等待已启用账号的监测状态变为在线');
            $existing=$DB->getRow('SELECT O.trade_no FROM pre_order O WHERE O.uid=:uid AND O.subchannel=:id AND O.tid=3 AND O.status=0 AND O.addtime>=DATE_SUB(NOW(),INTERVAL 5 MINUTE) AND (O.addtime>=DATE_SUB(NOW(),INTERVAL 1 MINUTE) OR EXISTS (SELECT 1 FROM pre_collection_reservation R WHERE R.trade_no=O.trade_no)) ORDER BY O.addtime DESC LIMIT 1',[':uid'=>$uid,':id'=>$id]);
            if ($existing) collection_reply(['code'=>0,'url'=>'/pay/qrcode/'.$existing['trade_no'].'/']);
            $trade=date('YmdHis').random_int(10000,99999);
            $url=$siteurl.'user/collection.php?paid='.$trade;
            $type=$DB->getColumn('SELECT type FROM pre_channel WHERE id=:id',[':id'=>$r['channel']]);
            if ($DB->insert('order',['trade_no'=>$trade,'out_trade_no'=>'collection-test-'.$trade,'uid'=>$uid,'tid'=>3,'type'=>$type,'channel'=>$r['channel'],'subchannel'=>$id,'name'=>'收款账号测试','money'=>'0.01','realmoney'=>'0.01','getmoney'=>'0.01','addtime'=>'NOW()','status'=>0,'notify_url'=>$url,'return_url'=>$url,'domain'=>$_SERVER['HTTP_HOST'],'ip'=>$clientip])===false) throw new RuntimeException('测试订单创建失败');
            $svc->audit($uid,$id,'创建测试订单',$trade);
            collection_reply(['code'=>0,'url'=>'/pay/qrcode/'.$trade.'/']);
        case 'review':
            $note=trim((string)($_POST['note']??''));
            if ($note==='' || mb_strlen($note)>300) throw new InvalidArgumentException('核对说明请填写 1–300 个字');
            $receipt=$DB->getRow('SELECT id,account_id,receipt_no FROM pre_collection_receipt WHERE id=:id AND uid=:uid',[':id'=>$id,':uid'=>$uid]);
            if (!$receipt) throw new InvalidArgumentException('流水不存在');
            $svc->transaction(function() use($DB,$svc,$receipt,$note,$uid,$id) {
                $DB->update('collection_receipt',['review_note'=>$note,'reviewed_at'=>'NOW()'],['id'=>$id,'uid'=>$uid]);
                $svc->audit($uid,$receipt['account_id'],'流水核对',$receipt['receipt_no'].' '.$note);
            });
            collection_reply(['code'=>0,'msg'=>'已记录核对说明，订单付款状态保持不变']);
        case 'retry':
            $trade=(string)($_POST['trade_no']??'');
            $o=$DB->getRow('SELECT O.* FROM pre_order O JOIN pre_collection_account C ON C.id=O.subchannel AND C.uid=O.uid WHERE O.uid=:uid AND O.trade_no=:trade AND O.status=1 AND O.tid=0',[':uid'=>$uid,':trade'=>$trade]);
            if (!$o) throw new InvalidArgumentException('已付款的业务订单不存在');
            $last=$DB->getColumn('SELECT MAX(created_at) FROM pre_collection_notify WHERE trade_no=:trade',[':trade'=>$trade]);
            if ($last && time()-strtotime($last)<15) throw new InvalidArgumentException('请稍等 15 秒后再重试');
            $svc->audit($uid,$o['subchannel'],'重试业务通知',$trade);
            $ok=\lib\CollectionNotify::retry($DB,$trade,true);
            collection_reply(['code'=>0,'msg'=>$ok?'业务通知成功':'业务通知未成功，请查看通知记录']);
        case 'records':
            $kind=(string)($_GET['kind']??'receipt');
            $tables=['receipt'=>'collection_receipt','notify'=>'collection_notify','audit'=>'collection_audit'];
            if (!isset($tables[$kind])) throw new InvalidArgumentException('记录类型无效');
            $page=max(1,(int)($_GET['page']??1)); $offset=min($page-1,10000)*20;
            $sql='uid=:uid'; $params=[':uid'=>$uid];
            if (!empty($_GET['account']) && $kind!=='notify') { $sql.=' AND account_id=:account'; $params[':account']=(int)$_GET['account']; }
            $kw=trim((string)($_GET['kw']??''));
            if ($kw!=='' && $kind!=='audit') { $sql.=' AND trade_no=:trade'; $params[':trade']=$kw; }
            if ($kind==='receipt' && in_array($_GET['state']??'',['unmatched','ambiguous','late','matched'],true)) { $sql.=' AND state=:state'; $params[':state']=$_GET['state']; }
            $rows=$DB->getAll('SELECT * FROM pre_'.$tables[$kind].' WHERE '.$sql.' ORDER BY id DESC LIMIT '.$offset.',21',$params);
            if (!is_array($rows)) throw new RuntimeException('读取记录失败');
            $more=count($rows)>20; collection_reply(['code'=>0,'data'=>array_slice($rows,0,20),'more'=>$more]);
        default: throw new InvalidArgumentException('未知操作');
    }
} catch (InvalidArgumentException $e) { collection_reply(['code'=>-1,'msg'=>$e->getMessage()]); }
catch (PDOException $e) { collection_reply(['code'=>-1,'msg'=>$e->getCode()==='23000'?'该支付宝账号、应用或收款码已绑定，请勿重复添加':'数据库操作失败，请联系管理员']); }
catch (RuntimeException $e) { collection_reply(['code'=>-1,'msg'=>$e->getMessage()]); }
catch (Throwable $e) { collection_reply(['code'=>-1,'msg'=>'操作失败，请检查配置或联系管理员']); }
