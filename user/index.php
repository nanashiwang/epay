<?php
$is_defend=true;
include("../includes/common.php");

if(isset($_GET['invite'])){
    $invite_code = trim($_GET['invite']);
    $uid = get_invite_uid($invite_code);
    if($uid && is_numeric($uid)){
        $_SESSION['invite_uid'] = intval($uid);
    }
}

if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

if(!$conf['reg_input_settle'] && (empty($userrow['account']) || empty($userrow['username']))){
	exit("<script language='javascript'>window.location.href='./completeinfo.php';</script>");
}

if($userrow['status']==0){
	$status = '<font color="red">已封禁</font>';
}elseif($userrow['pay']==0 && $userrow['settle']==0){
	$status = '<font color="red">关闭支付、结算</font>';
}elseif($userrow['pay']==0){
	$status = '<font color="red">关闭支付</font>';
}elseif($userrow['settle']==0){
	$status = '<font color="red">关闭结算</font>';
}elseif($conf['cert_force']==1 && $userrow['cert']==0){
	$status = '<a href="certificate.php"><font color="red">未实名认证</font></a>';
}elseif($userrow['pay']==2){
	$status = '<font color="orange">待审核</font>';
}else{
	$status = '<font color="green">正常</font>';
}
$title='用户中心';
include './head.php';
?>
<style>
.round {
    line-height: 53px;
    color: #7266ba;
    width: 58px;
    height: 58px;
    font-size: 26px;
    margin-left:15px;
    display: inline-block;
    font-weight: 400;
    border: 3px solid #f8f8fe;
    text-align: center;
    border-radius: 50%;
    background: #e3dff9;
}
.dates{max-width:120px;}
#incomeTable th,#incomeTable td{text-align:center;vertical-align:middle;}
#incomeTable .income-empty{padding:30px 15px;color:#999;}
.income-summary{padding:12px 15px;border-top:1px solid #edf1f2;background:#fafbfc;}
.income-summary .summary-item{display:inline-block;margin-right:20px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
<?php
$rs=$DB->query("SELECT * FROM pre_settle WHERE uid={$uid} AND status=1 ORDER BY id DESC LIMIT 9");
$max_settle=0;
$chart='';
$i=0;
while($row = $rs->fetch())
{
	if($row['money']>$max_settle)$max_settle=$row['money'];
	$chart.='['.$i++.','.$row['money'].'],';
}
$chart=substr($chart,0,-1);

$list = $DB->getAll("SELECT * FROM pre_anounce WHERE status=1 ORDER BY sort ASC");
?>
 <div id="content" class="app-content" role="main">
    <div class="app-content-body ">
		<div class="modal inmodal fade" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">关闭</span>
						</button>
						<h4 class="modal-title">欢迎回来</h4>
					</div>
					<div class="modal-body">
<?php echo $conf['modal']?>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
					</div>
				</div>
			</div>
		</div>

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">用户中心</h1>
  <small class="text-muted">欢迎使用<?php echo $conf['sitename']?></small>
</div>
<div class="wrapper-md control">
<!-- stats -->
<?php
if($conf['cert_force']==1 && $userrow['cert']==0){
	echo '<div class="alert alert-danger"><span class="btn-sm btn-danger">重要</span>&nbsp;请完成实名认证，否则您的商户无法正常收款！ <a href="./certificate.php" class="btn btn-default btn-xs">立即实名认证</a></div>';
}
if($conf['verifytype']==1 && empty($userrow['phone'])){
	echo '<div class="alert alert-warning"><span class="btn-sm btn-warning">提示</span>&nbsp;您还没有绑定密保手机，请&nbsp;<a href="editinfo.php" class="btn btn-default btn-xs">尽快绑定</a></div>';
}elseif($conf['verifytype']==0 && empty($userrow['email'])){
	echo '<div class="alert alert-warning"><span class="btn-sm btn-warning">提示</span>&nbsp;您还没有绑定密保邮箱，请&nbsp;<a href="editinfo.php" class="btn btn-default btn-xs">尽快绑定</a></div>';
}
if(empty($userrow['pwd'])){
	echo '<div class="alert alert-warning"><span class="btn-sm btn-warning">提示</span>&nbsp;您还没有设置登录密码，请&nbsp;<a href="userinfo.php?mod=account" class="btn btn-default btn-xs">点此设置</a>，设置登录密码之后你就可以使用手机号/邮箱+密码登录</div>';
}
?>

          <div class="row row-sm text-center">
            <div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-money fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-primary-dk font-thin h1"><span class="text-muted text-md">¥</span><?php echo $userrow['money']?></div><span class="text-muted">商户当前余额</span></div>
			  </div>
			  </div>
            </div>
			<div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-check-square-o fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-dark-dk font-thin h1"><span class="text-muted text-md">¥</span><span id="settle_money"></span></div><span class="text-muted">已结算余额</span></div>
			  </div>
			  </div>
            </div>
			<div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-area-chart fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-success-dk font-thin h1"><span id="orders"></span><span class="text-muted text-md">个</span></div><span class="text-muted">订单总数</span></div>
			  </div>
			  </div>
            </div>
			<div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-cart-plus fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-info-dk font-thin h1"><span id="orders_today"></span><span class="text-muted text-md">个</span></div><span class="text-muted">今日订单</span></div>
			  </div>
			  </div>
            </div>
        </div>
	      <div class="row">
        <div class="col-md-6">

		<div class="panel b-a">
            <div class="panel-heading bg-info dk no-border wrapper-lg">
              <a class="btn btn-sm btn-rounded btn-info pull-right m-r" href="./editinfo.php"><i class="fa fa-cog fa-fw"></i>&nbsp;修改资料</a>
              <a class="btn btn-sm btn-rounded btn-info m-l" href="./userinfo.php?mod=api"><i class="fa fa-lock fa-fw"></i>&nbsp;API信息</a>
            </div>
            <div class="text-center m-b clearfix">
              <div class="thumb-lg avatar m-t-n-xxl">
                <img src="<?php echo ($userrow['qq'])?'//q2.qlogo.cn/headimg_dl?bs=qq&dst_uin='.$userrow['qq'].'&src_uin='.$userrow['qq'].'&fid='.$userrow['qq'].'&spec=100&url_enc=0&referer=bu_interface&term_type=PC':'assets/img/user.png'?>" alt="..." class="b b-3x b-white">
              </div>
			  <div class="h2 font-thin m-t-sm">欢迎您，<?php echo $userrow['username']?></div>
			  <div class="h4 font-thin m-t-sm">商户状态：<?php echo $status;?></div>
            </div>
            <div class="hbox text-center b-t b-light bg-light">          
              <a class="col padder-v text-muted b-r b-light">
                <div class="h3"><span id="order_today_all"></span></div>
                <i class="fa fa-plus fa-fw"></i><span>今日收入</span>
              </a>
              <a class="col padder-v text-muted">
                <div class="h3"><span id="order_lastday_all"></span></div>
                <i class="fa fa-plus-circle fa-fw"></i><span>昨日收入</span>
              </a>
            </div>
			<?php if($conf['user_transfer']==1){?>
			<div class="hbox text-center b-t b-light bg-light">          
              <a class="col padder-v text-muted b-r b-light">
                <div class="h3"><span id="transfer_today_all"></span></div>
                <i class="fa fa-send fa-fw"></i><span>今日支出</span>
              </a>
              <a class="col padder-v text-muted">
                <div class="h3"><span id="transfer_lastday_all"></span></div>
                <i class="fa fa-send-o fa-fw"></i><span>昨日支出</span>
              </a>
            </div>
			<?php }?>
          </div>

		  <div class="panel panel-default text-center">
		<div class="panel-heading font-bold">
			收入统计与通道费率
		</div>
		<div class="table-responsive">
		<table class="table table-striped">
		<thead><tr id="paytypes"></tr></thead>
		<tbody><tr id="order_today"></tr><tr id="order_lastday"></tr><tr id="success_rate"></tr><tr id="payrates"></tr></tbody>
		</table>
		</div>
		</div>

		  </div>
		<div class="col-md-6">

		  <div class="panel panel-default">
		<div class="panel-heading font-bold text-center">
			公告通知
		</div>
		<div class="list-group">
<?php foreach($list as $row){?>
			<a class="list-group-item"><em class="fa fa-fw fa-volume-up"></em><font color="<?php echo $row['color']?$row['color']:null?>"><?php echo $row['content']?></font><span class="text-xs text-muted">&nbsp;-<?php echo $row['addtime']?></span></a>
<?php }?>
		</div>
		</div>
		
          <div class="panel wrapper">
            <label class="i-switch bg-warning pull-right" ng-init="showSpline=true">
              <input type="checkbox" ng-model="showSpline">
              <i></i>
            </label>
            <h4 class="font-thin m-t-none m-b text-muted">结算统计表</h4>
            <div ui-jq="plot" ui-refresh="showSpline" ui-options="
              [
                { data: [ <?php echo $chart?> ], label:'结算金额', points: { show: true, radius: 1}, splines: { show: true, tension: 0.4, lineWidth: 1, fill: 0.8 } }
              ], 
              {
                colors: ['#23b7e5', '#7266ba'],
                series: { shadowSize: 3 },
                xaxis:{ font: { color: '#a1a7ac' } },
                yaxis:{ font: { color: '#a1a7ac' }, max:<?php echo ($max_settle+10)?> },
                grid: { hoverable: true, clickable: true, borderWidth: 0, color: '#dce5ec' },
                tooltip: true,
                tooltipOpts: { content: '结算金额¥%y',  defaultTheme: false, shifts: { x: 10, y: -25 } }
              }
            " style="height:246px" >
            </div>
          </div>
        </div>
      </div>
	  <div class="row">
		<div class="col-md-12">
		  <div class="panel panel-default">
			<div class="panel-heading font-bold">
				收入统计
			</div>
			<div class="panel-body">
				<div class="form-inline">
					<div class="input-group input-daterange m-r-sm">
						<input type="text" id="income_starttime" class="form-control dates" placeholder="开始日期" autocomplete="off">
						<span class="input-group-addon"><i class="fa fa-chevron-right"></i></span>
						<input type="text" id="income_endtime" class="form-control dates" placeholder="结束日期" autocomplete="off">
					</div>
					<button type="button" class="btn btn-primary" id="incomeSearchBtn"><i class="fa fa-search"></i> 查询</button>
					<button type="button" class="btn btn-default" id="incomeRecentBtn">最近7天</button>
					<button type="button" class="btn btn-default" id="incomeResetBtn"><i class="fa fa-refresh"></i> 重置</button>
				</div>
			</div>
			<div class="table-responsive">
				<table class="table table-striped table-bordered m-b-none" id="incomeTable">
					<thead id="incomeTableHead">
						<tr><th>日期</th><th>每日合计</th></tr>
					</thead>
					<tbody id="incomeTableBody">
						<tr><td colspan="2" class="income-empty">收入统计加载中...</td></tr>
					</tbody>
				</table>
			</div>
			<div class="income-summary clearfix">
				<span class="summary-item text-muted">统计范围：<span id="incomeRangeText">-</span></span>
				<span class="summary-item text-muted">已支付订单：<strong id="incomeTotalOrders">0</strong> 笔</span>
				<span class="pull-right">区间总收入：<strong class="text-primary">¥<span id="incomeTotalAmount">0.00</span></strong></span>
			</div>
		  </div>
		</div>
	  </div>
      <!-- / stats -->
</div>
    </div>
  </div>

<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script>
var incomeToday = '<?php echo date("Y-m-d");?>';

function formatIncomeDate(date) {
	var year = date.getFullYear();
	var month = date.getMonth() + 1;
	var day = date.getDate();
	return year + '-' + (month < 10 ? '0' + month : month) + '-' + (day < 10 ? '0' + day : day);
}

function shiftIncomeDate(dateText, offset) {
	var date = new Date(dateText.replace(/-/g, '/'));
	date.setDate(date.getDate() + offset);
	return formatIncomeDate(date);
}

function setIncomeLastDays(days) {
	$('#income_endtime').val(incomeToday);
	$('#income_starttime').val(shiftIncomeDate(incomeToday, -(days - 1)));
}

function renderIncomeEmpty(message, colspan) {
	$('#incomeTableHead').html('<tr><th>日期</th><th>每日合计</th></tr>');
	$('#incomeTableBody').html('<tr><td colspan="' + (colspan || 2) + '" class="income-empty">' + message + '</td></tr>');
}

function renderIncomeStats(data) {
	var headHtml = '<tr><th>日期</th>';
	$.each(data.channels, function(i, channel) {
		headHtml += '<th><img src="/assets/icon/' + channel.name + '.ico" width="16" onerror="this.style.display=\'none\'">&nbsp;' + channel.showname + '</th>';
	});
	headHtml += '<th>每日合计</th></tr>';
	$('#incomeTableHead').html(headHtml);

	var colspan = data.channels.length + 2;
	if(!data.has_data){
		$('#incomeTableBody').html('<tr><td colspan="' + colspan + '" class="income-empty">该时间段暂无收入</td></tr>');
	}else{
		var bodyHtml = '';
		$.each(data.rows, function(i, row) {
			bodyHtml += '<tr><td><strong>' + row.date + '</strong><br><span class="text-muted">' + row.order_count + ' 笔</span></td>';
			$.each(data.channels, function(j, channel) {
				var amount = row.amounts[channel.id] ? row.amounts[channel.id] : '0.00';
				bodyHtml += '<td>¥' + amount + '</td>';
			});
			bodyHtml += '<td class="text-primary"><strong>¥' + row.total_amount + '</strong></td></tr>';
		});
		$('#incomeTableBody').html(bodyHtml);
	}

	$('#incomeRangeText').text(data.starttime + ' 至 ' + data.endtime);
	$('#incomeTotalOrders').text(data.total_orders);
	$('#incomeTotalAmount').text(data.total_amount);
	$('#income_starttime').val(data.starttime);
	$('#income_endtime').val(data.endtime);
}

function loadIncomeStats() {
	renderIncomeEmpty('收入统计加载中...', $('#incomeTableHead th').length || 2);
	$.ajax({
		type : "GET",
		url : "ajax2.php?act=incomeStats",
		dataType : 'json',
		data : {
			starttime: $('#income_starttime').val(),
			endtime: $('#income_endtime').val()
		},
		success : function(data) {
			if(data.code == 0){
				renderIncomeStats(data);
			}else{
				renderIncomeEmpty(data.msg ? data.msg : '收入统计加载失败');
			}
		},
		error : function() {
			renderIncomeEmpty('收入统计加载失败，请稍后重试');
		}
	});
}

$(document).ready(function(){
	$.ajax({
		type : "GET",
		url : "ajax2.php?act=getcount",
		dataType : 'json',
		async: true,
		success : function(data) {
			$('#orders').html(data.orders);
			$('#orders_today').html(data.orders_today);
			$('#settle_money').html(data.settle_money);
			$('#order_today_all').html(data.order_today_all);
			$('#order_lastday_all').html(data.order_lastday_all);
			$('#transfer_today_all').html(data.transfer_today_all);
			$('#transfer_lastday_all').html(data.transfer_lastday_all);
			$.each(data.channels, function (i, item) {
				$('#paytypes').append('<th style="text-align:center;"><img src="/assets/icon/'+item.name+'.ico" width="18px">&nbsp;'+item.showname+'</th>');
			});
			$.each(data.channels, function (i, item) {
				$('#order_today').append('<td>今日：'+item.order_today+' 元</td>');
				$('#order_lastday').append('<td>昨日：'+item.order_lastday+' 元</td>');
				$('#success_rate').append('<td>成功率：'+item.success_rate+' %</td>');
				$('#payrates').append('<td>费率：'+item.rate+' %</td>');
			});
		}
	});
	$('.input-daterange').datepicker({
		format: 'yyyy-mm-dd',
		autoclose: true,
		clearBtn: true,
		language: 'zh-CN'
	});
	setIncomeLastDays(7);
	loadIncomeStats();
	$('#incomeSearchBtn').click(function(){
		loadIncomeStats();
	});
	$('#incomeRecentBtn, #incomeResetBtn').click(function(){
		setIncomeLastDays(7);
		loadIncomeStats();
	});
	<?php if(!empty($conf['modal'])){?>
	$('#myModal').modal('show');
	<?php }?>
});
</script>
