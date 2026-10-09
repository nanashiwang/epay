<?php
require __DIR__.'/merchant-operations.php';
use lib\PaymentReview as Review;
$start=$checks;
foreach(explode(';',file_get_contents(ROOT.'install/payment-review.sql')) as $sql) if(trim($sql)!=='')$DB->exec($sql);
$DB->exec('DELETE FROM pre_payment_review');
$DB->update('order',['status'=>0,'addtime'=>date('Y-m-d H:i:s',time()-3600)],['trade_no'=>$be]);$DB->update('bepusdt_order',['state'=>'unknown'],['trade_no'=>$be]);
check(in_array($be,array_column(Review::listing($DB,1000)['rows'],'trade_no'),true),'unknown order in owner queue');
check(!Review::listing($DB,1001,1,$be)['rows'],'cannot search another merchant order');
check(count(Review::listing($DB,null,1,$be)['rows'])===1,'admin can find merchant order');
$input=['trade_no'=>$be,'action'=>'note','reason'=>'已在网关核对待确认','reference'=>'case-001','request_key'=>str_repeat('a',32)];
$svc=new Review();reject(fn()=>$svc->act($DB,1001,'merchant:1001',$input),'cross merchant write rejected');
$before=$DB->find('order','status,money',['trade_no'=>$be]);$svc->act($DB,1000,'merchant:1000',$input);
check($DB->find('order','status,money',['trade_no'=>$be])===$before,'notes never change payment');
$svc->act($DB,1000,'merchant:1000',$input);check($DB->getColumn('SELECT COUNT(*) FROM pre_payment_review')==1,'request replay not repeated');
reject(fn()=>$svc->act($DB,1000,'merchant:1000',array_replace($input,['request_key'=>str_repeat('b',32)])),'rate limit enforced');
$html=result(spawn(['page'=>'payment-review','method'=>'POST','post'=>array_replace($input,['csrf'=>'bad'])]));check(str_contains($html,'页面验证已过期'),'CSRF enforced on real page');
check(result(spawn(['page'=>'payment-review-admin','method'=>'GET']))==='','ordinary user cannot load admin page');
$html=result(spawn(['page'=>'payment-review','method'=>'GET','get'=>['trade'=>$be]]));check(str_contains($html,'case-001') && !str_contains($html,'config_snapshot'),'safe history rendered');
$DB->exec('DELETE FROM pre_payment_review');
class ReviewProbe extends Review {public $calls=0; protected function inspect($db,array $r){$this->calls++;return '合成查询结果';}}
$stub=new ReviewProbe();$input['action']='inspect';
$DB->exec("CREATE TRIGGER review_audit_failure BEFORE INSERT ON pre_payment_review FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
reject(fn()=>$stub->act($DB,1000,'merchant:1000',$input),'audit failure stops external operation');check($stub->calls===0,'no request without audit intent');$DB->exec('DROP TRIGGER review_audit_failure');
$stub->act($DB,1000,'merchant:1000',$input);$stub->act($DB,1000,'merchant:1000',$input);check($stub->calls===1,'inspect replay calls provider once');
$DB->exec('DELETE FROM pre_payment_review');$input['action']='retry';reject(fn()=>$svc->act($DB,1000,'merchant:1000',$input),'unpaid order cannot retry business notification');
class InspectProbe extends lib\BepusdtClient {public $reply;protected function post($path,array $params,$decode=true){check($path==='api/v1/pay/info' && $params===['trade_id'=>'provider-001'],'provider lookup uses known ID only');return $this->reply;}}
$client=new InspectProbe('https://gateway.example/','synthetic-token');
$snap=['trade_no'=>$be,'provider_id'=>'provider-001','network'=>'usdt.trc20','address'=>'TsyntheticReceiverAddress','money'=>'10.00','coin_amount'=>'1.5'];
$data=['trade_id'=>'provider-001','order_id'=>$be,'trade_type'=>'usdt.trc20','fiat'=>'CNY','token'=>$snap['address'],'money'=>'10.00','actual_amount'=>'1.5','status'=>2];
$client->reply=['status_code'=>200,'data'=>$data];check(str_contains($client->inspect($snap),'重发原始签名回调'),'paid query is informational');
foreach(['order_id'=>'wrong','token'=>'wrong','money'=>'11','trade_type'=>'wrong','actual_amount'=>'2','status'=>99] as $k=>$v){$client->reply=['status_code'=>200,'data'=>array_replace($data,[$k=>$v])];reject(fn()=>$client->inspect($snap),'mismatch rejected '.$k);}
reject(fn()=>$client->inspect(array_replace($snap,['provider_id'=>null])),'unknown create not blindly queried');
$input['action']='note';$input['reason']='<script>alert(1)</script> 核对记录';$svc->act($DB,1000,'merchant:1000',$input);
$html=result(spawn(['page'=>'payment-review','method'=>'GET','get'=>['trade'=>$be]]));check(!str_contains($html,'<script>alert(1)</script>') && str_contains($html,'&lt;script&gt;'),'stored XSS escaped');
echo 'Payment review: '.($checks-$start)." checks passed\n";
