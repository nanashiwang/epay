(() => {
  'use strict';
  const root=document.getElementById('subscription-admin'),$=id=>document.getElementById(id),form=$('sa-form');
  let page=1,revision=0,current=null,readFailed=false;
  const node=(tag,text)=>{const n=document.createElement(tag);n.textContent=text??'—';return n;};
  async function api(act,fields){const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);try{
    const options={cache:'no-store',credentials:'same-origin',signal:controller.signal};
    if(fields){fields.set('csrf',root.dataset.csrf);options.method='POST';options.body=fields;}
    const response=await fetch('subscriptions_api.php?'+new URLSearchParams({act,page,state:$('sa-state').value}),options);
    const r=await response.json();if(r.code!==0)throw new Error(r.msg||'请求失败');return r;
  }catch(e){if(e.name==='AbortError')throw new Error('请求超时，请刷新核对最终处理结果');throw e;}finally{clearTimeout(timer);}}
  function effect(){const apply=form.elements.action.value==='apply';form.elements.reference.required=!apply;
    $('sa-effect').textContent=!apply?'仅登记已完成的线下处理，不会转账、退款或更改当前套餐。':(Number(current.gid)===Number(current.current_gid)?'同套餐从当前有效期与现在的较晚时间续期 '+current.months+' 个月。':'将替换当前套餐，按处理时起算所购 '+current.months+' 个月。原套餐剩余价值不会自动折算。');}
  function open(row){current=row;form.reset();form.elements.trade_no.value=row.trade_no;form.elements.version.value=row.version;$('sa-error').textContent='';$('sa-summary').textContent=`商户 ${row.uid} · ¥${row.money} · 已购 ${row.purchased_name||'已删除套餐'} / ${row.months} 个月；当前 ${row.current_name||'默认套餐'}，到期 ${row.current_endtime||'永久'}。`;effect();$('sa-dialog').showModal();}
  async function load(){const rev=++revision;$('sa-prev').disabled=$('sa-next').disabled=true;try{const r=await api('list');if(rev!==revision)return;
    if(readFailed){$('sa-message').textContent='';readFailed=false;}const list=document.createDocumentFragment();for(const row of r.rows){const card=node('article','');card.className='mw-card';card.append(node('h2',`商户 ${row.uid} · ${row.trade_no}`),node('p',`实付 ¥${row.money} · ${row.purchased_name||'已删除套餐'} / ${row.months} 个月 · ${row.created_at}`),node('p',`当前套餐：${row.current_name||'默认套餐'} · 到期 ${row.current_endtime||'永久'}`));
      if(row.state==='review'){const b=node('button','核对并处理');b.className='btn btn-primary';b.onclick=()=>open(row);card.append(b);}else{card.append(node('p',`${row.resolved_action==='apply'?'已开通':'已登记线下处理'} · ${row.actor} · ${row.resolved_at}`),node('p',`原因：${row.reason}`),node('p',`凭证：${row.reference||'—'}`),node('p',`处理时权益：用户组 ${row.resolved_old_gid} / ${row.resolved_old_endtime||'永久'} → 用户组 ${row.resolved_new_gid} / ${row.resolved_new_endtime||'永久'}`));}list.append(card);}
    if(!r.rows.length)list.append(node('p','暂无记录。'));$('sa-records').replaceChildren(list);$('sa-page').textContent=`第 ${page} 页`;$('sa-prev').disabled=page===1;$('sa-next').disabled=!r.more;
  }catch(e){if(rev===revision){readFailed=true;$('sa-message').textContent=e.message;$('sa-records').replaceChildren(node('p','记录读取失败，请点击刷新重试。'));}}}
  form.elements.action.onchange=effect;$('sa-cancel').onclick=()=>$('sa-dialog').close();
  form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;try{const r=await api('resolve',new FormData(form));$('sa-dialog').close();$('sa-message').textContent=r.msg;await load();}catch(err){$('sa-error').textContent=err.message;}finally{button.disabled=false;}};
  $('sa-state').onchange=()=>{page=1;load();};$('sa-refresh').onclick=load;for(const [id,delta] of [['sa-prev',-1],['sa-next',1]])$(id).onclick=()=>{page=Math.max(1,page+delta);load();};load();
})();
