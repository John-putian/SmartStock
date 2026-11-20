(() => {
  'use strict';
  const form = document.getElementById('inventoryForm');
  const button = document.getElementById('suggestPrice');
  if (!form || !button) return;
  const name = form.elements.namedItem('name');
  const category = form.elements.namedItem('category_id');
  const price = form.elements.namedItem('selling_price');
  const panel = document.getElementById('priceSuggestionPanel');
  const status = document.getElementById('priceSuggestionStatus');
  const use = document.getElementById('useSuggestedPrice');
  let draft = null, request = null, revision = 0;
  const context = () => JSON.stringify([name.value.trim(), category.value]);
  function reset() {
    revision++; if (request) request.abort(); request = null; draft = null;
    panel.classList.add('d-none'); button.disabled = false; button.setAttribute('aria-busy','false');
  }
  function message(text, error=false) {status.textContent=text; status.classList.toggle('text-danger',error);}
  [name,category].forEach(field => field.addEventListener('input', () => {
    if (draft || request) {reset(); message('Item details changed. Request another initial price.');}
  }));
  category.addEventListener('change', () => {if (draft || request) {reset(); message('Category changed. Request another initial price.');}});
  button.addEventListener('click', async () => {
    if (!name.value.trim()) {message('Enter an item name first, or use the photo-generated name.',true); name.focus(); return;}
    reset(); const current=revision, details=context(); request=new AbortController();
    const controller=request; const timer=setTimeout(()=>controller.abort(),15000);
    button.disabled=true; button.setAttribute('aria-busy','true'); message('Checking similar sales…');
    const data=new FormData(); data.append('_token',form.elements.namedItem('_token').value);
    data.append('name',name.value.trim()); data.append('category_id',category.value);
    try {
      const response=await fetch(button.dataset.url,{method:'POST',body:data,signal:controller.signal,credentials:'same-origin'});
      if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Keep a copy of your entries and sign in again.');
      const result=await response.json();
      if (current!==revision || details!==context()) return;
      if (!response.ok || !result.ok) throw new Error(result.error || 'Enter an initial price manually.');
      draft=result.price; const amount=Number(draft.amount);
      document.getElementById('priceSuggestionAmount').textContent=Number.isFinite(amount)&&amount>0
        ? new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(amount) : 'No price suggestion';
      document.getElementById('priceSuggestionBasis').textContent=draft.explanation;
      use.disabled=!Number.isFinite(amount)||amount<=0;
      panel.classList.remove('d-none'); message('Review this initial price and your acquisition cost before using it.');
    } catch(error) {
      if(current===revision) message(error.name==='AbortError'?'Price request timed out. Try again or enter it manually.':error.message,true);
    } finally {
      clearTimeout(timer); if(current===revision){request=null;button.disabled=false;button.setAttribute('aria-busy','false');}
    }
  });
  use.addEventListener('click',()=>{
    if(!draft || use.disabled) return;
    price.value=Number(draft.amount).toFixed(2);
    price.dispatchEvent(new Event('input',{bubbles:true})); price.dispatchEvent(new Event('change',{bubbles:true}));
    reset(); message('Initial price added. Edit it if needed, then save the item.'); price.focus();
  });
  document.getElementById('dismissSuggestedPrice').addEventListener('click',()=>{reset();message('Suggestion dismissed. Your price was kept.');});
  form.addEventListener('submit',event=>{if(!event.defaultPrevented) reset();});
})();
