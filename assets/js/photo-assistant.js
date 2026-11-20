(() => {
  'use strict';
  const form = document.getElementById('inventoryForm');
  if (!form) return;
  const file = document.getElementById('itemPhoto');
  const button = document.getElementById('suggestPhoto');
  const status = document.getElementById('photoStatus');
  const panel = document.getElementById('photoDraft');
  const apply = document.getElementById('applyPhoto');
  const fields = ['name', 'category_id', 'description', 'selling_price'].map(name => form.elements.namedItem(name));
  const checks = ['usePhotoName', 'usePhotoCategory', 'usePhotoDescription', 'usePhotoPrice'].map(id => document.getElementById(id));
  let draft = null, request = null, revision = 0, preview = null;
  const hero = file.closest('.card-body').querySelector('.item-hero');
  const originalHero = hero.cloneNode(true);
  const cameraButton = document.getElementById('takeProductPhoto');
  const galleryButton = document.getElementById('chooseProductPhoto');
  if (cameraButton && galleryButton) {
    cameraButton.disabled = false; galleryButton.disabled = false;
    file.classList.add('visually-hidden');
    cameraButton.addEventListener('click', () => {
      file.setAttribute('capture', 'environment');
      file.setAttribute('accept', 'image/*');
      file.click();
    });
    galleryButton.addEventListener('click', () => {
      file.removeAttribute('capture');
      file.setAttribute('accept', 'image/*');
      file.click();
    });
  }
  function message(text, error = false) {
    status.textContent = text;
    status.classList.toggle('text-danger', error);
  }
  /* --- Make a phone camera photo usable -------------------------------------
     An iPhone hands over a .HEIC file, which neither PHP nor the AI service can
     read, and a modern phone camera easily produces a 10 MB JPG. Both used to
     be refused outright. Here the picture is decoded in the phone itself and
     re-encoded as a modest JPG before anything is uploaded, so the staff member
     never has to know the difference - and the smaller file also uploads far
     faster on shop Wi-Fi. A photo that is already small and in an accepted
     format is left exactly as it is. */
  const OK_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const MAX_BYTES = 6 * 1024 * 1024;
  const CONVERT_OVER = 2.5 * 1024 * 1024;
  const MAX_EDGE = 1600;
  const kb = bytes => bytes >= 1024 * 1024
    ? (bytes / 1048576).toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB';

  async function decode(blob) {
    if (typeof createImageBitmap === 'function') {
      try { return await createImageBitmap(blob); } catch (e) { /* fall through */ }
    }
    // Older Safari: let the <img> decoder handle it instead.
    const url = URL.createObjectURL(blob);
    try {
      const image = new Image();
      image.src = url;
      await (image.decode ? image.decode() : new Promise((ok, fail) => {
        image.onload = ok; image.onerror = () => fail(new Error('decode failed'));
      }));
      return image;
    } finally { URL.revokeObjectURL(url); }
  }

  async function prepare(original) {
    const wrongType = !OK_TYPES.includes(original.type);
    if (!wrongType && original.size <= CONVERT_OVER) return {file: original};
    let source;
    try { source = await decode(original); } catch (e) { return {failed: true}; }
    const width = source.width || source.naturalWidth;
    const height = source.height || source.naturalHeight;
    if (!width || !height) return {failed: true};
    const scale = Math.min(1, MAX_EDGE / Math.max(width, height));
    const w = Math.max(1, Math.round(width * scale));
    const h = Math.max(1, Math.round(height * scale));
    const canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    const context = canvas.getContext('2d');
    if (!context) return {failed: true};
    context.drawImage(source, 0, 0, w, h);
    if (source.close) source.close();
    const blob = await new Promise(done => canvas.toBlob(done, 'image/jpeg', 0.82));
    if (!blob || blob.size > MAX_BYTES) return {failed: true};
    const name = (original.name || 'photo').replace(/\.[^.]*$/, '') + '.jpg';
    return {
      file: new File([blob], name, {type: 'image/jpeg', lastModified: Date.now()}),
      converted: true, was: original.size, pixels: w + '×' + h,
    };
  }

  /* Put the converted picture back into the file input, so the photo saved on
     the product record is the same one the AI was shown. */
  function adopt(replacement) {
    if (typeof DataTransfer !== 'function') return false;
    try {
      const bag = new DataTransfer();
      bag.items.add(replacement);
      file.files = bag.files;
      return file.files.length === 1 && file.files[0].size === replacement.size;
    } catch (e) { return false; }
  }
  function reset() {
    revision++;
    if (request) request.abort();
    request = null; draft = null;
    panel.classList.add('d-none');
    button.disabled = button.dataset.enabled !== '1' || !file.files.length;
    button.setAttribute('aria-busy', 'false');
  }
  let preparing = false;
  file.addEventListener('change', async () => {
    if (preparing) return;          // our own replacement, not a new choice
    reset(); message('');
    file.setCustomValidity('');
    if (preview) URL.revokeObjectURL(preview);
    hero.replaceChildren(...Array.from(originalHero.childNodes).map(node => node.cloneNode(true)));
    let selected = file.files[0];
    const fileName = document.getElementById('photoFileName');
    if (fileName) fileName.textContent = selected ? 'Selected: ' + selected.name : 'No new photo selected.';
    if (!selected) return;

    const tooBig = selected.size > CONVERT_OVER;
    const wrongType = !OK_TYPES.includes(selected.type);
    if (tooBig || wrongType) {
      button.disabled = true;
      message(wrongType ? 'Preparing the photo…' : 'Making the photo smaller so it uploads quickly…');
      const result = await prepare(selected);
      if (file.files[0] !== selected) return;   // they picked another one meanwhile
      if (result.failed || !result.file) {
        file.setCustomValidity('Choose a JPG, PNG or WEBP photo up to 6 MB.');
        message('This picture could not be read. Take the photo again, or choose a JPG, PNG or WEBP file up to 6 MB.', true);
        return;
      }
      if (result.converted) {
        preparing = true;
        const adopted = adopt(result.file);
        preparing = false;
        if (!adopted) {
          // The browser will not let us swap the file. Say so plainly rather
          // than letting the upload fail at the server.
          file.setCustomValidity('Choose a JPG, PNG or WEBP photo up to 6 MB.');
          message('This photo is ' + kb(result.was) + ' or in a format this browser cannot convert here.'
            + ' Choose a JPG, PNG or WEBP photo up to 6 MB.', true);
          return;
        }
        selected = result.file;
        if (fileName) fileName.textContent = 'Selected: ' + selected.name;
        message('Photo prepared: ' + kb(result.was) + ' → ' + kb(selected.size)
          + ' (' + result.pixels + '). The smaller copy is what gets saved and read.');
      }
    }
    if (!OK_TYPES.includes(selected.type) || selected.size > MAX_BYTES) {
      button.disabled = true;
      file.setCustomValidity('Choose a JPG, PNG or WEBP photo up to 6 MB.');
      message('Choose a JPG, PNG or WEBP photo up to 6 MB.', true); return;
    }
    button.disabled = button.dataset.enabled !== '1';
    const image = document.createElement('img'); image.alt = 'Selected product photo';
    preview = URL.createObjectURL(selected); image.src = preview; hero.replaceChildren(image);
    const remove = document.getElementById('rmPhoto'); if (remove) remove.checked = false;
  });
  const remove = document.getElementById('rmPhoto');
  if (remove) remove.addEventListener('change', () => {
    if (!remove.checked) return;
    file.value = ''; file.dispatchEvent(new Event('change')); message('Current photo will be removed when you save.');
  });
  button.addEventListener('click', async () => {
    if (button.disabled || !file.files.length) return;
    reset(); const current = revision;
    request = new AbortController();
    const controller = request;
    button.disabled = true; button.setAttribute('aria-busy', 'true');
    message('Creating a draft… This may take up to a minute. You can continue editing.');
    const data = new FormData();
    data.append('_token', form.elements.namedItem('_token').value);
    data.append('photo', file.files[0]);
    const timeout = setTimeout(() => controller.abort(), 60000);
    try {
      const response = await fetch(button.dataset.url, {method: 'POST', body: data, signal: controller.signal, credentials: 'same-origin'});
      if (current !== revision) return;
      if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Your session may have expired. Save your entered details elsewhere, refresh and sign in again.');
      }
      const result = await response.json();
      if (current !== revision) return;
      if (!response.ok || !result.ok) throw new Error(result.error || 'Suggestions are unavailable. Enter the details manually.');
      draft = result.draft;
      document.getElementById('photoName').textContent = draft.name;
      document.getElementById('photoDescription').textContent = draft.description;
      const price = Number(draft.price?.amount);
      document.getElementById('photoPrice').textContent = Number.isFinite(price) && price > 0
        ? new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(price) : 'Enter a price manually';
      document.getElementById('photoPriceBasis').textContent = draft.price?.explanation || 'No price suggestion is available.';
      const option = Array.from(fields[1].options).find(option => Number(option.value) === draft.category_id && option.value !== '');
      document.getElementById('photoCategory').textContent = option ? option.textContent.trim() : 'Could not identify a category. Please select one manually.';
      checks.forEach((check, i) => {check.checked = fields[i].value.trim() === ''; check.disabled = false;});
      if (!option) { checks[1].checked = false; checks[1].disabled = true; }
      checks[3].checked = false; // Money is always an explicit review choice.
      checks[3].disabled = !Number.isFinite(price) || price <= 0;
      panel.classList.remove('d-none'); message('Draft ready. Review it and choose which details to use.');
    } catch (error) {
      if (current !== revision) return;
      message(error.name === 'AbortError' ? 'The request took too long. Try again or enter the details manually.' : error.message, true);
    } finally {
      clearTimeout(timeout);
      if (current === revision) {request = null; button.disabled = false; button.setAttribute('aria-busy', 'false');}
    }
  });
  apply.addEventListener('click', () => {
    if (!draft) return;
    let count = 0;
    [draft.name, String(draft.category_id), draft.description, Number(draft.price?.amount).toFixed(2)].forEach((value, i) => {
      if (checks[i].checked && !checks[i].disabled) {
        fields[i].value = value;
        fields[i].dispatchEvent(new Event('input', {bubbles: true}));
        fields[i].dispatchEvent(new Event('change', {bubbles: true})); count++;
      }
    });
    if (!count) {message('Tick at least one suggested field to use.'); return;}
    panel.classList.add('d-none'); draft = null;
    message('Selected details added to the form. Review and edit them, then save the item.');
    fields[0].focus();
  });
  document.getElementById('dismissPhoto').addEventListener('click', () => {reset(); message('Draft dismissed. Your entries were kept.');});
  form.addEventListener('submit', event => {if (!event.defaultPrevented) reset();});
})();
