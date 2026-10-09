(() => {
  'use strict';
  const root=document.getElementById('bepusdt'), $=id=>document.getElementById(id), form=$('bep-form'), dialog=$('bep-dialog');
  let tab='accounts',page=1,revision=0,active=false,loaded=false,networks=[];
  const networkName=type=>networks.find(n=>n.type===type)?.name||type;
  function addressHint(){const family=networks.find(n=>n.type===form.elements.trade_type.value)?.family;form.elements.address.placeholder=({tron:'T 开头的 TRON 地址',evm:'0x 开头的 42 位 EVM 地址',solana:'Solana Base58 地址'}[family]||'该网络的收款地址')+'；留空由网关分配';}
  const node=(tag,value,cls)=>{const n=document.createElement(tag);n.textContent=value??'—';if(cls)n.className=cls;return n;};
  const message=value=>{$('bep-message').textContent=value;$('bep-message').hidden=!value;};
  async function api(act,fields) {
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),25000);
    try {
      const options={cache:'no-store',credentials:'same-origin',signal:controller.signal};
      if(fields){const body=new URLSearchParams(fields);body.set('csrf',root.dataset.csrf);Object.assign(options,{method:'POST',body});}
      const response=await fetch('bepusdt_api.php?'+new URLSearchParams({act,...(!fields&&act==='records'?{kind:tab,page}:{})}),options);
      const result=await response.json();if(result.code!==0)throw new Error(result.msg||'操作失败');return result;
    } catch(e){if(e.name==='AbortError')throw new Error('请求超时，请刷新确认结果后再操作');throw e;}finally{clearTimeout(timer);}
  }
  function button(label,run,disabled=false){const b=node('button',label,'btn btn-default');b.type='button';b.disabled=disabled;b.onclick=async()=>{b.disabled=true;try{await run();}catch(e){message(e.message);}finally{b.disabled=disabled;}};return b;}
  function edit(account){
    form.reset();$('bep-form-error').textContent='';
    for(const key of ['id','name','endpoint','address','timeout'])form.elements[key].value=account?account[key]:({id:0,timeout:1200}[key]??'');
    const options=[...networks];if(account&&!options.some(n=>n.type===account.trade_type))options.push({type:account.trade_type,name:account.trade_type+'（当前不可新建）'});
    const select=form.elements.trade_type;select.replaceChildren(...options.map(n=>{const option=node('option',n.name);option.value=n.type;return option;}));select.value=account?.trade_type||options[0]?.type||'';select.disabled=!!account;addressHint();
    $('bep-title').textContent=account?'编辑网关':'添加网关';dialog.showModal();
  }
  async function action(a,act,prompt){if(prompt&&!window.confirm(prompt))return;const r=await api(act,{id:a.id});message(r.msg);await accounts();}
  function loadError(e){message(e.message);if(!loaded){$('bep-plan').textContent='套餐读取失败，请重试；恢复前不能新增网关。';$('bep-accounts').replaceChildren(button('重新加载',()=>accounts().catch(loadError)));}}
  async function accounts(){
    const r=await api('list'),plan=r.subscription;active=plan.active;loaded=true;networks=r.networks||[];
    $('bep-plan').textContent=`${plan.name} · ${plan.active?'使用中':'未开通或已到期'} · 有效期 ${plan.endtime||'未开通'} · 已用 ${plan.used} / ${plan.limit} 个自助账号。`+(!networks.length?' 尚未开放币种与网络，请联系管理员更新收款模板。':'')+(plan.active?'':' 可查看历史记录，请前往“我的套餐”购买或续期。');
    $('bep-add').disabled=!active||plan.used>=plan.limit||!networks.length;$('bep-add').title=!networks.length?'尚未开放币种与网络，请联系管理员更新收款模板':plan.used>=plan.limit?'账号数量已满，请归档闲置账号后再添加':'';$('bep-accounts').replaceChildren();
    if(!r.data.length)$('bep-accounts').append(node('p',active?'还没有网关账号。添加你自己的 BEpusdt 地址和 Token 即可开始接入。':'还没有网关账号。开通套餐后可添加自己的 BEpusdt。','collection-empty'));
    for(const a of r.data){
      const card=node('article','','collection-card'),top=node('div','','collection-card-top');
      top.append(node('h2',a.name),node('span',a.tested_at?'已测试到账':a.verified_at?'待测试到账':'待校验','collection-badge'));card.append(top);
      const dl=document.createElement('dl');
      for(const [key,value] of [['币种与网络',networkName(a.trade_type)],['使用状态',(Number(a.status)?(active?'已启用':'套餐到期，已暂停收款'):'已停用')+(a.default_id?' · 默认收款':'')],['网关地址',a.endpoint],['收款地址',a.address||'网关自动分配'],['接口校验',a.verified_at],['测试到账',a.tested_at],['最近回调',a.last_callback]]){const row=document.createElement('div');row.append(node('dt',key),node('dd',value));dl.append(row);}card.append(dl);
      if(a.last_error)card.append(node('p',a.last_error));const actions=node('div','','collection-actions');
      actions.append(button('编辑',()=>edit(a),!active||!!Number(a.status)),button('校验接口',()=>action(a,'verify'),!active));
      actions.append(button('测试收款',async()=>{const amount=window.prompt('测试金额（人民币 0.10–100.00 元）。进入收银台后由你自行转账，不会自动扣款。','1.00');if(amount===null)return;const result=await api('test',{id:a.id,amount});window.location.assign(result.url);},!active||!a.verified_at));
      actions.append(button(Number(a.status)?'停用':'启用',()=>action(a,Number(a.status)?'disable':'enable',Number(a.status)?'停用后不再创建新交易，已创建的订单仍会处理到账。':null),!Number(a.status)&&(!active||!a.tested_at)));
      actions.append(button(a.default_id?'取消默认':'设为默认',()=>action(a,a.default_id?'unroute':'default',a.default_id?'取消后此方式将停止使用该账号收款。确认取消？':'新 '+networkName(a.trade_type)+' 订单将收至此网关的钱包。确认切换？'),!a.default_id&&(!active||!Number(a.status))));
      actions.append(button('归档',()=>action(a,'archive','归档后保留历史记录和在途订单处理。确认归档？'),!!Number(a.status)||!!a.default_id));card.append(actions);$('bep-accounts').append(card);
    }
  }
  async function records(){
    const kind=tab,p=page,rev=++revision,r=await api('records');if(rev!==revision||kind!==tab||p!==page)return;
    const table=document.createElement('table'),thead=document.createElement('thead'),tr=document.createElement('tr');
    const labels=kind==='orders'?['时间','订单号','人民币金额','币种与网络','代币数量','状态','链上交易','操作']:kind==='notify'?['通知时间','订单号','通知地址','HTTP / 耗时','结果']:['时间','账号','操作','说明'];labels.forEach(x=>tr.append(node('th',x)));thead.append(tr);table.append(thead);const body=document.createElement('tbody');
    const states={creating:'创建中',unknown:'待核对',waiting:'等待付款',expired:'已超时',paid:'已到账'};
    for(const row of r.data){const tr=document.createElement('tr');const cells=kind==='orders'?[row.created_at,row.trade_no,'¥ '+row.money,networkName(row.network),row.coin_amount,row.state==='waiting'&&row.expires_at&&Date.parse(row.expires_at.replace(' ','T')+'+08:00')<Date.now()?'已超时':(states[row.state]||row.state),row.txid]:kind==='notify'?[row.created_at,row.trade_no,row.target,`${row.http_code} / ${row.duration_ms} ms`,Number(row.success)?'成功':row.response_summary]:[row.created_at,row.account_id,row.action,row.detail];cells.forEach(v=>tr.append(node('td',v)));if(kind==='orders'){const td=document.createElement('td');if(row.state==='paid'&&Number(row.tid)===0)td.append(button('重试通知',async()=>{const result=await api('retry',{trade_no:row.trade_no});message(result.msg);}));else td.textContent=row.last_error||'—';tr.append(td);}body.append(tr);}
    if(!r.data.length){const tr=document.createElement('tr'),td=node('td','暂无记录');td.colSpan=labels.length;tr.append(td);body.append(tr);}table.append(body);$('bep-records').replaceChildren(table);$('bep-page').textContent=`第 ${page} 页`;$('bep-prev').disabled=page===1;$('bep-next').disabled=!r.more;
  }
  async function select(next){tab=next;page=1;revision++;root.querySelectorAll('[data-bep-tab]').forEach(b=>b.classList.toggle('selected',b.dataset.bepTab===tab));$('bep-accounts').hidden=tab!=='accounts';$('bep-record-view').hidden=tab==='accounts';try{if(tab==='accounts')await accounts();else{$('bep-records').replaceChildren(node('p','正在读取记录…'));await records();}}catch(e){if(tab==='accounts')loadError(e);else{message(e.message);$('bep-records').replaceChildren(node('p','读取失败，请重新选择记录页重试。'));}}}
  form.elements.trade_type.onchange=addressHint;
  $('bep-add').onclick=()=>edit();for(const id of ['bep-close','bep-cancel'])$(id).onclick=()=>dialog.close();dialog.addEventListener('close',()=>form.reset());
  form.onsubmit=async e=>{e.preventDefault();const submit=form.querySelector('[type=submit]');submit.disabled=true;$('bep-form-error').textContent='';try{const r=await api('save',{...Object.fromEntries(new FormData(form)),trade_type:form.elements.trade_type.value});dialog.close();message(r.msg);await accounts();}catch(err){$('bep-form-error').textContent=err.message;}finally{submit.disabled=false;}};
  root.querySelectorAll('[data-bep-tab]').forEach(b=>b.onclick=()=>select(b.dataset.bepTab));
  for(const [id,delta] of [['bep-prev',-1],['bep-next',1]])$(id).onclick=async()=>{page=Math.max(1,page+delta);try{await records();}catch(e){message(e.message);}};
  accounts().catch(loadError);
})();
