(() => {
  'use strict';
  const form=document.getElementById('inventoryForm');
  if(!form) return;
  const summary=document.getElementById('formErrors');
  const list=document.getElementById('formErrorList');
  const save=document.getElementById('saveProduct');
  const saveLabel=save.innerHTML;
  const fields=Array.from(form.querySelectorAll('input[name],select[name],textarea[name]')).filter(field=>field.type!=='hidden');
  const labels={name:'Item name',category_id:'Category',selling_price:'Selling price',cost_price:'Acquisition cost',quantity:'Quantity',sku:'SKU',reorder_level:'Reorder level',photo:'Product photo'};
  function feedback(field) {
    let node=document.getElementById('field-'+field.name+'-error');
    if(!node) {
      node=document.createElement('div'); node.id='field-'+field.name+'-error'; node.dataset.fieldError=field.name;
      node.className='invalid-feedback'; (field.closest('.input-group')||field).after(node);
      field.setAttribute('aria-describedby',node.id);
    }
    return node;
  }
  function show(field,message) {
    field.classList.toggle('is-invalid',!!message);
    if(message) field.setAttribute('aria-invalid','true'); else field.removeAttribute('aria-invalid');
    const node=feedback(field); node.textContent=message; node.classList.toggle('d-block',!!message);
  }
  function focus(field) {
    const more=field.closest('details'); if(more) more.open=true;
    if(field.type==='file') document.getElementById('chooseProductPhoto').focus();
    else field.focus();
    field.closest('.card')?.scrollIntoView({block:'start',behavior:'smooth'});
  }
  function updateSummary() {
    list.replaceChildren();
    fields.forEach(field=>{
      const message=document.getElementById('field-'+field.name+'-error')?.textContent;
      if(!message) return;
      const li=document.createElement('li'); const link=document.createElement('a');
      link.href='#'+field.id; link.textContent=message;
      link.addEventListener('click',event=>{event.preventDefault();focus(field);}); li.append(link);list.append(li);
    });
    summary.classList.toggle('d-none',list.children.length===0);
  }
  fields.forEach(field=>{
    field.addEventListener('input',()=>{show(field,'');updateSummary();});
    field.addEventListener('change',()=>{show(field,'');updateSummary();});
  });
  form.addEventListener('submit',event=>{
    let first=null;
    fields.forEach(field=>{
      let message=''; const label=labels[field.name]||'This field';
      if(!field.validity.valid) {
        if(field.validity.valueMissing) message=field.name==='category_id'?'Choose a category.':'Enter '+label.toLowerCase()+'.';
        else if(field.validity.rangeUnderflow) message=field.name==='selling_price'?'Selling price must be greater than zero.':label+' cannot be negative.';
        else if(field.validity.badInput || field.validity.stepMismatch) message=label+' needs a valid '+(field.step==='1'?'whole number.':'number.');
        else message=field.validationMessage;
      }
      if(field.name==='selling_price' && !message && Number(field.value)<Number(form.elements.namedItem('cost_price').value)) message='Selling price is below acquisition cost. Check both amounts.';
      show(field,message);if(message&&!first) first=field;
    });
    updateSummary();
    if(first) {event.preventDefault();focus(first);return;}
    save.disabled=true;save.textContent='Saving…';save.setAttribute('aria-busy','true');
  });
  window.addEventListener('pageshow',()=>{save.disabled=false;save.innerHTML=saveLabel;save.removeAttribute('aria-busy');});
  if(list.children.length) {
    const first=fields.find(field=>field.getAttribute('aria-invalid')==='true');
    if(first) focus(first);
    updateSummary();
  }
})();
