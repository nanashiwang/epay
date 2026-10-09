(() => {
  'use strict';
  const root = document.getElementById('collection');
  const csrf = root.dataset.csrf;
  const $ = id => document.getElementById(id);
  const form = $('account-form');
  const dialog = $('account-dialog');
  let subscription = null, accounts = [], tab = 'accounts', page = 1, loading = false, recordRevision = 0;
  const text = (tag, value, className) => {
    const el = document.createElement(tag);
    el.textContent = value == null || value === '' ? '—' : String(value);
    if (className) el.className = className;
    return el;
  };
  function message(value) { $('collection-message').textContent = value; $('collection-message').hidden = !value; }
  async function api(act, fields) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 45000);
    try {
      const body = fields instanceof FormData ? fields : new URLSearchParams(fields || {});
      body.set('csrf', csrf);
      const response = await fetch('collection_api.php?act=' + act, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal });
      const result = await response.json();
      if (result.code !== 0) throw new Error(result.msg || '操作失败');
      return result;
    } catch (e) { if (e.name === 'AbortError') throw new Error('请求超时，请刷新确认状态后重试'); throw e; }
    finally { clearTimeout(timer); }
  }
  async function read(query) {
    const response = await fetch('collection_api.php?' + new URLSearchParams(query), { cache: 'no-store', credentials: 'same-origin' });
    const result = await response.json();
    if (result.code !== 0) throw new Error(result.msg || '读取失败');
    return result;
  }
  function button(label, action, className = 'btn btn-default', disabled = false) {
    const b = text('button', label, className);
    b.type = 'button'; b.disabled=disabled;
    b.addEventListener('click', async () => {
      b.disabled = true;
      try { await action(); } catch (e) { message(e.message || '操作失败，请刷新重试'); }
      finally { b.disabled = disabled; }
    });
    return b;
  }
  function openForm(account) {
    form.reset(); $('form-error').textContent = ''; $('qr-hint').textContent = '图片仅用于解析，不保存到相册或公开目录';
    for (const name of ['id','name','alipay_uid','qr_url','appid']) form.elements[name].value = account ? account[name] : (name === 'id' ? '0' : '');
    for (const name of ['alipay_uid','qr_url']) form.elements[name].readOnly = !!account;
    $('qr-file').disabled = !!account;
    $('form-title').textContent = account ? '编辑收款账号' : '添加收款账号';
    dialog.showModal();
  }
  async function action(account, name, confirmText) {
    if (confirmText && !window.confirm(confirmText)) return;
    const result = await api(name, {id: account.id}); message(result.msg); await loadAccounts();
  }
  function renderAccount(a) {
    const card = document.createElement('article'); card.className = 'collection-card';
    const top = document.createElement('div'); top.className = 'collection-card-top';
    top.append(text('h2',a.name), text('span',a.health,'collection-badge ' + (a.health === '在线' ? 'online' : 'error'))); card.append(top);
    const dl = document.createElement('dl');
    const entries = [ ['收款方式','支付宝原生码 · 账单确认'], ['使用状态',(Number(a.status) ? '已启用监测' : '已停用') + (a.is_default ? ' · 默认收款' : '')], ['应用 APPID', a.appid], ['收款 UID',a.alipay_uid], ['今日已付',`¥ ${a.today.amount} / ${a.today.count} 笔`], ['最近成功查询',a.last_ok], ['接口校验',a.verified_at], ['匹配规则','订单备注优先，无备注时按唯一金额'] ];
    for (const [key,value] of entries) { const row = document.createElement('div'); row.append(text('dt',key),text('dd',value)); dl.append(row); }
    card.append(dl);
    if (a.last_error) card.append(text('p', a.last_error));
    const blocked=subscription && !subscription.active;
    const actions = document.createElement('div'); actions.className = 'collection-actions';
    actions.append(button('编辑',() => openForm(a),'btn btn-default',blocked||!!Number(a.status)),button('校验接口',() => action(a,'verify'),'btn btn-default',blocked));
    actions.append(button(Number(a.status) ? '停用' : '启用监测',() => action(a,Number(a.status) ? 'disable' : 'enable',a.is_default && Number(a.status) ? '停用后，本商户的支付宝新订单将暂停收款。在途订单继续查账。确定停用？' : null),'btn btn-default',!Number(a.status)&&blocked));
    actions.append(button('测试收款',async () => { const result = await api('test',{id:a.id}); window.location.assign(result.url); },'btn btn-default',blocked||!Number(a.status)));
    actions.append(button(a.is_default ? '取消默认' : '设为默认',() => action(a,a.is_default ? 'unroute' : 'default',a.is_default ? (subscription?'取消后支付宝新订单停止使用该账号。确定取消？':'取消后，新订单将恢复使用平台通道。确定取消？') : '请先测试并确认二维码收款方正确。设为默认后，你的支付宝新订单将直接收至此账号。确定切换？'),'btn btn-default',!a.is_default&&(blocked||!Number(a.status))));
    actions.append(button('查看流水',() => { $('filter-account').value = String(a.id); return selectTab('receipt'); }));
    actions.append(button('归档',()=>action(a,'archive','归档后保留历史记录。确认归档？'),'btn btn-default',!!Number(a.status)||a.is_default));
    card.append(actions); return card;
  }
  async function loadAccounts() {
    const result = await read({act:'list'}); accounts = result.data; subscription=result.subscription;
    $('add-account').disabled=!!subscription&&(!subscription.active||subscription.used>=subscription.limit);
    $('collection-plan').hidden=!subscription;
    if(subscription)$('collection-plan').textContent=`包月自助 · ${subscription.active?'使用中':'未开通或已到期'} · 到期 ${subscription.endtime||'未开通'} · 所有自助账号合计 ${subscription.used} / ${subscription.limit}`;
    const cards = $('accounts'); cards.replaceChildren();
    if (!accounts.length) cards.append(text('div','还没有收款账号。添加支付宝原生收款码与账单应用，开始配置。','collection-empty'));
    else accounts.forEach(a => cards.append(renderAccount(a)));
    const stats = $('account-stats'); stats.replaceChildren();
    for (const [label,value] of [['收款账号',accounts.length],['监测在线',accounts.filter(a => a.health === '在线').length],['默认账号',accounts.some(a => a.is_default) ? '已设置' : (subscription?'未配置':'沿用平台通道')]]) {
      const span = document.createElement('span'); span.append(text('strong',value),document.createTextNode(label)); stats.append(span);
    }
    const selected = $('filter-account').value;
    $('filter-account').replaceChildren(new Option('全部账号',''));
    accounts.forEach(a => $('filter-account').append(new Option(a.name,a.id)));
    $('filter-account').value = selected;
  }
  async function loadRecords() {
    const kind = tab, requestedPage = page, revision = ++recordRevision;
    const result = await read({act:'records',kind,page,account:$('filter-account').value,kw:$('filter-trade').value,state:$('filter-state').value});
    if (revision !== recordRevision || kind !== tab || page !== requestedPage) return;
    const container = $('records'); container.replaceChildren();
    const labels = { matched:'已匹配',unmatched:'未匹配',ambiguous:'金额歧义',late:'超出自动确认窗口' };
    const columns = kind === 'receipt' ? ['到账时间','金额','支付宝流水号','系统订单号','状态','核对说明','操作'] : kind === 'notify' ? ['通知时间','系统订单号','通知地址（GET）','HTTP / 耗时','结果','响应摘要','操作'] : ['操作时间','账号','操作','说明'];
    const table = document.createElement('table'); const head = document.createElement('thead'); const tr = document.createElement('tr'); columns.forEach(c => tr.append(text('th',c))); head.append(tr); table.append(head);
    const body = document.createElement('tbody');
    if (!result.data.length) { const row = document.createElement('tr'); const cell = text('td','暂无符合条件的记录'); cell.colSpan = columns.length; row.append(cell); body.append(row); }
    for (const r of result.data) {
      const row = document.createElement('tr');
      const values = kind === 'receipt' ? [r.paid_at,'¥ '+r.amount,r.receipt_no,r.trade_no,labels[r.state] || r.state,r.review_note] : kind === 'notify' ? [r.created_at,r.trade_no,r.target,`${r.http_code} / ${r.duration_ms} ms`,Number(r.success) ? '成功' : '失败',r.response_summary] : [r.created_at,accounts.find(a => String(a.id) === String(r.account_id))?.name || r.account_id,r.action,r.detail];
      values.forEach(v => row.append(text('td',v)));
      if (kind !== 'audit') {
        const cell = document.createElement('td');
        cell.append(button(kind === 'receipt' ? '记录核对' : '重试通知',async () => {
          let data;
          if (kind === 'receipt') {
            const note = window.prompt('填写核对说明。此操作仅留档，不会把订单改为已付款。',r.review_note || '');
            if (note === null) return;
            data = await api('review',{id:r.id,note});
          } else {
            if (!window.confirm('重新向业务站发送此已付款订单的签名通知？')) return;
            data = await api('retry',{trade_no:r.trade_no});
          }
          message(data.msg); await loadRecords();
        })); row.append(cell);
      }
      body.append(row);
    }
    table.append(body); container.append(table);
    $('prev-page').disabled = page <= 1; $('next-page').disabled = !result.more; $('page-label').textContent = `第 ${page} 页`;
  }
  async function selectTab(next) {
    tab = next; page = 1; recordRevision++;
    $('records').replaceChildren(text('p','正在读取记录…','collection-help'));
    $('prev-page').disabled = true; $('next-page').disabled = true; $('page-label').textContent = '';
    root.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('selected',b.dataset.tab === tab));
    $('account-view').hidden = tab !== 'accounts'; $('record-view').hidden = tab === 'accounts';
    $('filter-state-wrap').hidden = tab !== 'receipt'; $('filter-account').parentElement.hidden = tab === 'notify'; $('filter-trade').parentElement.hidden = tab === 'audit';
    $('record-help').textContent = tab === 'receipt' ? '仅显示接入后查询到的收入。未匹配、金额歧义和迟到付款需要人工核对；核对说明不会直接更改订单。' : tab === 'notify' ? '仅记录自助收款账号的业务通知。沿用易支付签名与自动重试；签名参数和原始响应不回显。' : '记录配置、启停、默认收款及流水核对操作。';
    try { if (tab === 'accounts') await loadAccounts(); else await loadRecords(); } catch(e) { message(e.message); }
  }
  $('add-account').addEventListener('click',() => openForm());
  for (const id of ['close-dialog','cancel-dialog']) $(id).addEventListener('click',() => { form.reset(); dialog.close(); });
  dialog.addEventListener('close',() => { form.elements.appsecret.value=''; form.elements.appkey.value=''; });
  form.addEventListener('submit',async e => {
    e.preventDefault(); const submit = form.querySelector('[type=submit]'); submit.disabled = true; $('form-error').textContent='';
    try { const result = await api('save',new FormData(form)); form.reset(); dialog.close(); message(result.msg); await loadAccounts(); }
    catch(err) { $('form-error').textContent=err.message; }
    finally { submit.disabled=false; }
  });
  $('qr-file').addEventListener('change',async e => {
    const file=e.target.files[0]; if (!file) return;
    $('qr-hint').textContent='正在解析…';
    try { const data=new FormData(); data.set('qr',file); const result=await api('decode',data); form.elements.qr_url.value=result.url; $('qr-hint').textContent='已识别支付宝原生收款码'; }
    catch(err) { $('qr-hint').textContent=err.message; }
    finally { e.target.value=''; }
  });
  root.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click',() => selectTab(b.dataset.tab)));
  $('record-filter').addEventListener('submit',async e => { e.preventDefault(); page=1; try { await loadRecords(); } catch(err) { message(err.message); } });
  $('prev-page').addEventListener('click',async () => { page=Math.max(1,page-1); try { await loadRecords(); } catch(e) { message(e.message); } });
  $('next-page').addEventListener('click',async () => { page++; try { await loadRecords(); } catch(e) { message(e.message); } });
  loadAccounts().catch(e => message(e.message));
  setInterval(async () => {
    if (document.hidden || dialog.open || loading || tab !== 'accounts') return;
    loading=true; try { await loadAccounts(); } catch(e) { message(e.message); } finally { loading=false; }
  },30000);
})();
