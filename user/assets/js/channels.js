(() => {
  'use strict';
  const root=document.getElementById('merchant-channels'),$=id=>document.getElementById(id),form=$('mc-form'),dialog=$('mc-dialog');
  let tab='accounts',page=1,revision=0,active=false,loaded=false,catalog={},types=[];
  const node=(tag,value,cls)=>{const n=document.createElement(tag);n.textContent=value??'—';if(cls)n.className=cls;return n;};
  const message=value=>{$('mc-message').textContent=value;$('mc-message').hidden=!value;};
  async function api(act,fields) {
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),25000);
    try {
      const options={cache:'no-store',credentials:'same-origin',signal:controller.signal};
      if(fields){const body=new URLSearchParams(fields);body.set('csrf',root.dataset.csrf);Object.assign(options,{method:'POST',body});}
      const response=await fetch('channels_api.php?'+new URLSearchParams({act,...(!fields&&act==='records'?{kind:tab,page}:{})}),options);
      const r=await response.json();if(r.code!==0)throw new Error(r.msg||'操作失败');return r;
    } catch(e){if(e.name==='AbortError')throw new Error('请求超时，请刷新确认结果后再操作');throw e;}finally{clearTimeout(timer);}
  }
  function button(label,run,disabled=false){const b=node('button',label,'btn btn-default');b.type='button';b.disabled=disabled;b.onclick=async()=>{b.disabled=true;try{await run();}catch(e){message(e.message);}finally{b.disabled=disabled;}};return b;}
  function fields(account){
    const spec=catalog[$('mc-plugin').value];if(!spec)return;
    $('mc-help').textContent=spec.note; const guides={alipay:'alipay',wxpayn:'wechat_v3',wxpay:'wechat_v2',qqpay:'qqpay',epay:'epay_gateway'}; const guide=node('a','查看本方式的字段说明 →');guide.href='/index.php?doc=help&topic='+guides[$('mc-plugin').value];guide.className='mc-inline-guide';$('mc-help').append(document.createElement('br'),guide);$('mc-type').replaceChildren();
    for(const t of types.filter(t=>spec.types.includes(t.name))){const option=node('option',t.showname);option.value=t.id;$('mc-type').append(option);}
    if(account)$('mc-type').value=account.type;
    $('mc-type').disabled=!!account;$('mc-fields').replaceChildren();
    for(const [key,field] of Object.entries(spec.fields)){
      const label=node('label',field.name),input=document.createElement(field.type==='textarea'?'textarea':'input');
      input.name=`config[${key}]`;input.maxLength=8192;
      if(field.type!=='textarea')input.type=field.secret?'password':'text';else input.rows=3;
      input.autocomplete=field.secret?'new-password':'off';input.spellcheck=false;input.required=!account||!field.secret;
      input.value=field.secret?'':(account?.config[key]||'');input.placeholder=field.secret?'加密保存；编辑时留空保留':'';
      label.append(input);$('mc-fields').append(label);
    }
    $('mc-modes').replaceChildren(node('legend','已签约的收款产品'));$('mc-modes').hidden=!Object.keys(spec.modes).length;
    for(const [value,name] of Object.entries(spec.modes)){const label=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.name='config[apptype][]';input.value=value;input.checked=(account?.config.apptype||spec.default).includes(value);label.className='mc-mode';label.append(input,document.createTextNode(' '+name));$('mc-modes').append(label);}
  }
  function edit(account){
    form.reset();$('mc-form-error').textContent='';form.elements.id.value=account?.id||0;form.elements.name.value=account?.name||'';
    $('mc-plugin').replaceChildren();for(const [key,spec] of Object.entries(catalog)){if(!types.some(t=>spec.types.includes(t.name)))continue;const option=node('option',spec.name);option.value=key;$('mc-plugin').append(option);}
    if(account)$('mc-plugin').value=account.plugin;$('mc-plugin').disabled=!!account;fields(account);
    $('mc-title').textContent=account?'编辑支付通道':'添加支付通道';dialog.showModal();
  }
  async function action(a,act,prompt){if(prompt&&!window.confirm(prompt))return;const r=await api(act,{id:a.id});message(r.msg);await accounts();}
  function loadError(e){message(e.message);active=false;$('mc-add').disabled=true;if(!loaded){$('mc-plan').textContent='套餐读取失败，请重试。';$('mc-accounts').replaceChildren(button('重新加载',()=>accounts().catch(loadError)));}}
  async function accounts(){
    const r=await api('list'),plan=r.subscription;active=plan.active;loaded=true;catalog=r.catalog;types=r.types;
    $('mc-plan').textContent=`${plan.name} · ${active?'使用中':'未开通或已到期'} · 有效期 ${plan.endtime||'未开通'} · 自助账号合计 ${plan.used} / ${plan.limit}。`+(active?'支付宝收款码和 USDT 账号也计入总数。':'可查看历史、停用或归档，请前往“我的套餐”开通或续期。');
    $('mc-add').disabled=!active||plan.used>=plan.limit;$('mc-add').title=plan.used>=plan.limit?'账号总数已满，请归档闲置账号':'';$('mc-accounts').replaceChildren();
    if(!r.data.length)$('mc-accounts').append(node('p',active?'还没有通道。添加自己的支付宝、微信、QQ 或易支付账号，完成测试后即可收款。':'还没有通道。开通商户自助套餐后，即可配置自己的支付账号。','collection-empty'));
    for(const a of r.data){
      const isDefault=Number(a.default_id)===Number(a.id),card=node('article','','collection-card'),top=node('div','','collection-card-top');
      top.append(node('h2',a.name),node('span',a.tested_at?'已测试到账':'待测试','collection-badge'));card.append(top);
      const dl=document.createElement('dl');
      for(const [key,value] of [['接入方式',catalog[a.plugin]?.name||a.plugin],['支付方式',a.type_name],['使用状态',(Number(a.status)?(active?'已启用':'套餐到期，已暂停收款'):'已停用')+(isDefault?' · 默认收款':'')],['测试到账',a.tested_at],['最近回调',a.last_callback]]){const row=document.createElement('div');row.append(node('dt',key),node('dd',value));dl.append(row);}card.append(dl);
      const steps=node('ol','','mc-progress');steps.setAttribute('aria-label','配置进度');for(const [label,done] of [['已保存',true],['测试到账',!!a.tested_at],['启用',!!Number(a.status)],['默认收款',isDefault]]) steps.append(node('li',label,done?'done':''));card.append(steps);
      const actions=node('div','','collection-actions');
      actions.append(button('编辑',()=>edit(a),!active||!!Number(a.status)));
      actions.append(button('测试收款',()=>{$('mc-test-form').reset();$('mc-test-form').elements.id.value=a.id;$('mc-test-error').textContent='';$('mc-test-dialog').showModal();},!active));
      actions.append(button(Number(a.status)?'停用':'启用',()=>action(a,Number(a.status)?'disable':'enable',Number(a.status)?'停用后停止新交易，在途订单仍会确认到账。':null),!Number(a.status)&&(!active||!a.tested_at)));
      actions.append(button(isDefault?'取消默认':'设为默认',()=>action(a,isDefault?'unroute':'default',isDefault?'取消后此支付方式停止接收新订单，直到选择新的默认账号。':'此支付方式的新订单将收至该账号。确认切换？'),!isDefault&&(!active||!Number(a.status))));
      actions.append(button('归档',()=>action(a,'archive','归档后保留历史记录和在途订单处理。确认归档？'),!!Number(a.status)||isDefault));card.append(actions);$('mc-accounts').append(card);
    }
  }
  async function records(){
    const kind=tab,p=page,rev=++revision,r=await api('records');if(rev!==revision||kind!==tab||p!==page)return;
    const table=document.createElement('table'),head=document.createElement('thead'),tr=document.createElement('tr');
    const labels=kind==='orders'?['时间','订单号','金额','状态','支付流水','操作']:kind==='notify'?['通知时间','订单号','通知地址','HTTP / 耗时','结果']:['时间','账号','操作','说明'];labels.forEach(x=>tr.append(node('th',x)));head.append(tr);table.append(head);const body=document.createElement('tbody');
    for(const row of r.data){const tr=document.createElement('tr');const values=kind==='orders'?[row.created_at,row.trade_no,'¥ '+row.money,row.paid_at?'已到账':'待确认',row.provider_id]:kind==='notify'?[row.created_at,row.trade_no,row.target,`${row.http_code} / ${row.duration_ms} ms`,Number(row.success)?'成功':row.response_summary]:[row.created_at,row.account_id,row.action,row.detail];values.forEach(v=>tr.append(node('td',v)));if(kind==='orders'){const td=document.createElement('td');if(row.paid_at&&Number(row.tid)===0)td.append(button('重试通知',async()=>message((await api('retry',{trade_no:row.trade_no})).msg)));else td.textContent='—';tr.append(td);}body.append(tr);}
    if(!r.data.length){const tr=document.createElement('tr'),td=node('td','暂无记录');td.colSpan=labels.length;tr.append(td);body.append(tr);}table.append(body);$('mc-records').replaceChildren(table);$('mc-page').textContent=`第 ${page} 页`;$('mc-prev').disabled=page===1;$('mc-next').disabled=!r.more;
  }
  async function select(next){tab=next;page=1;revision++;root.querySelectorAll('[data-mc-tab]').forEach(b=>b.classList.toggle('selected',b.dataset.mcTab===tab));$('mc-accounts').hidden=tab!=='accounts';$('mc-record-view').hidden=tab==='accounts';try{if(tab==='accounts')await accounts();else await records();}catch(e){if(tab==='accounts')loadError(e);else{message(e.message);$('mc-records').replaceChildren(node('p','读取失败，请点击刷新重试。'));}}}
  $('mc-plugin').onchange=()=>fields();$('mc-add').onclick=()=>edit();for(const id of ['mc-close','mc-cancel'])$(id).onclick=()=>dialog.close();dialog.addEventListener('close',()=>form.reset());
  form.onsubmit=async e=>{e.preventDefault();const submit=form.querySelector('[type=submit]');submit.disabled=true;$('mc-form-error').textContent='';try{if(!$('mc-modes').hidden&&!form.querySelector('[name="config[apptype][]"]:checked'))throw new Error('请至少选择一种已签约产品');const r=await api('save',new FormData(form));dialog.close();message(r.msg);await accounts();}catch(err){$('mc-form-error').textContent=err.message;}finally{submit.disabled=false;}};
  $('mc-test-close').onclick=()=>$('mc-test-dialog').close();$('mc-test-form').onsubmit=async e=>{e.preventDefault();const submit=e.target.querySelector('[type=submit]');submit.disabled=true;try{const r=await api('test',new FormData(e.target));window.location.assign(r.url);}catch(err){$('mc-test-error').textContent=err.message;}finally{submit.disabled=false;}};
  root.querySelectorAll('[data-mc-tab]').forEach(b=>b.onclick=()=>select(b.dataset.mcTab));$('mc-refresh').onclick=()=>select(tab);
  for(const [id,delta] of [['mc-prev',-1],['mc-next',1]])$(id).onclick=async()=>{page=Math.max(1,page+delta);try{await records();}catch(e){message(e.message);}};
  accounts().catch(loadError);
})();
