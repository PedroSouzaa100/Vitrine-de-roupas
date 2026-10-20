document.addEventListener('DOMContentLoaded', () => {
  const editor = document.querySelector('[data-photo-editor]');
  if (!editor) return;
  const list = editor.querySelector('[data-photo-list]');
  const rows = () => [...list.querySelectorAll('[data-photo-item]')];
  const hasPhoto = row => !!row.querySelector('[data-photo-file]').files.length || (row.dataset.existing === '1' && !row.querySelector('[data-photo-remove]').checked);
  function update() {
    const photos = rows().filter(hasPhoto);
    editor.querySelector('[data-photo-count]').textContent = photos.length + ' de 5 fotos selecionadas';
    rows().forEach((row, index, all) => {
      row.querySelector('[data-photo-label]').textContent = 'Foto ' + (index + 1);
      row.querySelector('[data-photo-main]').hidden = photos[0] !== row;
      row.querySelector('[data-photo-first]').disabled = !hasPhoto(row) || photos[0] === row;
      row.querySelector('[data-photo-up]').disabled = index === 0;
      row.querySelector('[data-photo-down]').disabled = index === all.length - 1;
    });
  }
  editor.addEventListener('change', event => {
    const row = event.target.closest('[data-photo-item]');
    if (!row) return;
    const input = row.querySelector('[data-photo-file]');
    const preview = row.querySelector('[data-photo-preview]');
    if (event.target === input) {
      const file = input.files[0];
      input.setCustomValidity('');
      if (file && (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
        input.setCustomValidity('Escolha JPG, PNG, WEBP ou GIF com até 5 MB.'); input.reportValidity();
      }
      if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
      if (file) { preview.dataset.objectUrl = URL.createObjectURL(file); preview.src = preview.dataset.objectUrl; }
      else preview.src = preview.dataset.original;
    }
    if (event.target.matches('[data-photo-remove]') && event.target.checked) {
      input.value = ''; input.setCustomValidity(''); preview.src = preview.dataset.original;
    }
    preview.style.opacity = hasPhoto(row) ? '1' : '.35';
    update();
  });
  editor.addEventListener('click', event => {
    const row = event.target.closest('[data-photo-item]');
    if (!row) return;
    if (event.target.closest('[data-photo-first]')) list.prepend(row);
    else if (event.target.closest('[data-photo-up]') && row.previousElementSibling) row.previousElementSibling.before(row);
    else if (event.target.closest('[data-photo-down]') && row.nextElementSibling) row.nextElementSibling.after(row);
    update();
  });
  editor.closest('form').addEventListener('submit', event => {
    const count = rows().filter(hasPhoto).length;
    if (count < 1 || count > 5) { event.preventDefault(); alert('Cada produto precisa ter de 1 a 5 fotos.'); return; }
    const save = editor.closest('form').querySelector('[data-save-product]');
    save.disabled = true;
    save.setAttribute('aria-busy', 'true');
    save.textContent = 'Salvando produto…';
  });
  update();
});
