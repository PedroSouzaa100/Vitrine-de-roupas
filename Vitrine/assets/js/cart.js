document.addEventListener('DOMContentLoaded', () => {
  const base = document.body.dataset.base || '';
  const page = document.querySelector('[data-cart-page]');
  const money = cents => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
  let token = '';
  let busy = false;
  let ready = false;

  function message(text, error = false) {
    const target = document.querySelector('[data-cart-message]');
    if (target) {
      const alert = document.createElement('div');
      alert.className = 'alert ' + (error ? 'alert-danger' : 'alert-info');
      alert.textContent = text;
      target.replaceChildren(alert);
    } else if (window.showToast) window.showToast(text, error ? 'error' : 'success');
  }
  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }
  function hidden(form, name, value) {
    const input = element('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
  }
  function itemNode(item) {
    const row = element('article', 'cart-item'); row.dataset.cartItem = item.key;
    const variantLabel = item.name + (item.size ? ', tamanho ' + item.size : '');
    const image = element('img', 'cart-item-image'); image.src = item.image; image.alt = item.name;
    const info = element('div', 'cart-item-info');
    const link = element('a', 'fw-bold', item.name); link.href = item.url;
    info.append(link, element('div', 'small text-muted', 'Preço unitário: ' + money(item.unit_cents)));
    const form = element('form', 'cart-quantity'); form.method = 'post'; form.dataset.cartForm = '';
    hidden(form, 'action', 'update'); hidden(form, 'product_id', item.id); hidden(form, 'line_id', item.key); hidden(form, 'csrf_token', token);
    if (item.available_sizes.length || item.size) {
      const group = element('div', 'w-100');
      const label = element('label', 'small fw-semibold', 'Tamanho'); label.htmlFor = 'size-' + item.key;
      const select = element('select', 'form-select form-select-sm'); select.name = 'size'; select.id = label.htmlFor; select.required = item.available_sizes.length > 0; select.dataset.cartLineSize = '';
      const empty = element('option', '', item.available_sizes.length ? 'Selecione' : 'Sem tamanho'); empty.value = ''; select.append(empty);
      if (item.size && !item.available_sizes.includes(item.size)) {
        const invalid = element('option', '', item.size + ' (indisponível)'); invalid.value = item.size; invalid.disabled = true; select.append(invalid);
      }
      item.available_sizes.forEach(size => { const option = element('option', '', size); option.value = size; select.append(option); });
      select.value = item.size;
      group.append(label, select); form.append(group);
    } else hidden(form, 'size', '');
    const minus = element('button', 'btn btn-outline-brand btn-sm', '−'); minus.type = 'button'; minus.dataset.cartStep = '-1'; minus.disabled = item.quantity <= 1; minus.setAttribute('aria-label', 'Diminuir quantidade de ' + variantLabel);
    const input = element('input', 'form-control'); input.type = 'number'; input.name = 'quantity'; input.value = item.quantity; input.min = 1; input.max = item.max_quantity; input.required = true; input.setAttribute('aria-label', 'Quantidade de ' + variantLabel);
    const plus = element('button', 'btn btn-outline-brand btn-sm', '+'); plus.type = 'button'; plus.dataset.cartStep = '1'; plus.disabled = item.quantity >= item.max_quantity; plus.setAttribute('aria-label', 'Aumentar quantidade de ' + variantLabel);
    form.append(minus, input, plus, element('button', 'btn btn-outline-brand btn-sm', 'Atualizar'));
    const remove = element('form', 'mt-2'); remove.method = 'post'; remove.dataset.cartForm = '';
    hidden(remove, 'action', 'remove'); hidden(remove, 'product_id', item.id); hidden(remove, 'line_id', item.key); hidden(remove, 'csrf_token', token);
    const removeButton = element('button', 'btn btn-sm btn-outline-brand', 'Remover'); removeButton.setAttribute('aria-label', 'Remover ' + variantLabel); remove.append(removeButton);
    info.append(form, remove);
    row.append(image, info, element('strong', 'cart-item-subtotal', money(item.subtotal_cents)));
    return row;
  }
  function render(cart) {
    document.querySelectorAll('[data-cart-count]').forEach(node => { node.textContent = cart.count; });
    if (!page) return;
    page.querySelector('[data-cart-empty]').hidden = cart.items.length > 0;
    page.querySelector('[data-cart-content]').hidden = !cart.items.length;
    page.querySelector('[data-cart-items]').replaceChildren(...cart.items.map(itemNode));
    page.querySelector('[data-cart-store]').textContent = cart.store?.name || '';
    page.querySelector('[data-cart-total]').textContent = money(cart.total_cents);
    page.querySelector('[data-cart-total-count]').textContent = cart.count;
    page.querySelector('[data-cart-continue]').href = cart.store?.url || base + 'stores.php';
    page.querySelector('[data-cart-checkout]').disabled = !cart.can_checkout;
    page.querySelector('[data-cart-phone-warning]').hidden = cart.has_valid_phone;
    page.querySelector('[data-cart-size-warning]').hidden = !cart.needs_size;
    page.querySelector('input[name="revision"]').value = cart.revision;
  }
  async function request(params) {
    const options = { credentials: 'include', cache: 'no-store' };
    if (params) {
      params.set('csrf_token', token);
      options.method = 'POST'; options.body = params;
    }
    const response = await fetch(base + 'cart-api.php', options);
    const result = await response.json();
    if (result.csrf_token) token = result.csrf_token;
    const focusedRow = document.activeElement?.closest('[data-cart-item]');
    let focusedId = focusedRow?.dataset.cartItem;
    const focusedField = document.activeElement?.name === 'size' ? 'size' : 'quantity';
    if (focusedRow && focusedField === 'size' && result.cart) {
      const productId = Number(focusedRow.querySelector('[name="product_id"]').value);
      const size = focusedRow.querySelector('[name="size"]').value;
      focusedId = result.cart.items.find(item => item.id === productId && item.size === size)?.key || focusedId;
    }
    if (result.cart) render(result.cart);
    if (focusedId && page) {
      const field = page.querySelector('[data-cart-item="' + CSS.escape(focusedId) + '"] [name="' + focusedField + '"]');
      field?.focus({ preventScroll: true });
    }
    if (result.error) throw new Error(result.error);
    if (!response.ok) throw new Error('Não foi possível atualizar o carrinho.');
    return result;
  }
  function setBusy(value) {
    busy = value;
    page?.setAttribute('aria-busy', String(value));
    document.querySelectorAll('[data-cart-add]').forEach(button => { button.disabled = value || !ready; });
  }
  function selectedSize(scope) {
    const select = scope?.querySelector('[data-cart-size]');
    if (select && !select.reportValidity()) { select.focus(); message('Escolha o tamanho antes de continuar.', true); return null; }
    return select?.value || '';
  }
  function inquiryLink(link, size) {
    const text = 'Olá! Tenho interesse em ' + link.dataset.waInquiry + (size ? ', tamanho ' + size : '') + '. Está disponível?';
    link.href = 'https://wa.me/' + link.dataset.waPhone + '?text=' + encodeURIComponent(text);
  }
  document.addEventListener('change', event => {
    if (event.target.matches('[data-cart-size]')) {
      event.target.setCustomValidity('');
      event.target.closest('[data-cart-product]')?.querySelectorAll('[data-wa-inquiry]').forEach(link => inquiryLink(link, event.target.value));
    }
    if (event.target.matches('[data-cart-line-size]') && !busy) event.target.closest('form').requestSubmit();
  });
  document.addEventListener('click', async event => {
    const inquiry = event.target.closest('[data-wa-inquiry]');
    if (inquiry) {
      const size = selectedSize(inquiry.closest('[data-cart-product]'));
      if (size === null) { event.preventDefault(); return; }
      inquiryLink(inquiry, size);
      return;
    }
    const add = event.target.closest('[data-cart-add]');
    const step = event.target.closest('[data-cart-step]');
    if (step && !busy) {
      const form = step.closest('form');
      const input = form.querySelector('[name="quantity"]');
      input.value = Math.min(Number(input.max), Math.max(1, Number(input.value) + Number(step.dataset.cartStep)));
      form.requestSubmit(); return;
    }
    if (!add || busy || !ready) return;
    const size = selectedSize(add.closest('[data-cart-product]'));
    if (size === null) return;
    setBusy(true); add.setAttribute('aria-busy', 'true');
    try {
      const result = await request(new URLSearchParams({ action: 'add', product_id: add.dataset.cartAdd, size, quantity: '1' }));
      message(['Produto adicionado ao carrinho.', ...result.cart.notices].join(' '));
    } catch (error) { message(error.message || 'Não foi possível conectar. Tente novamente.', true); }
    finally { setBusy(false); add.removeAttribute('aria-busy'); }
  });
  document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-cart-form]');
    if (!form || !ready) return;
    event.preventDefault();
    if (busy) return;
    if (form.hasAttribute('data-cart-clear') && !confirm('Limpar todos os produtos do carrinho?')) return;
    setBusy(true);
    try {
      const data = new URLSearchParams(new FormData(form));
      const result = await request(data);
      if (result.url) { window.location.assign(result.url); return; }
      message(['Carrinho atualizado.', ...result.cart.notices].join(' '));
    } catch (error) { message(error.message || 'Não foi possível conectar. Tente novamente.', true); }
    finally { setBusy(false); }
  });
  document.querySelectorAll('[data-wa-inquiry]').forEach(link => inquiryLink(link, link.closest('[data-cart-product]')?.querySelector('[data-cart-size]')?.value || ''));
  request().then(result => {
    ready = true; setBusy(false);
    if (result.cart.notices.length) message(result.cart.notices.join(' '));
  }).catch(() => { message('Não foi possível carregar o carrinho. Atualize a página para tentar novamente.', true); });
});
