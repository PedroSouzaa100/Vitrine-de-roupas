document.addEventListener('DOMContentLoaded', function () {

  /* =========================================================
   *  Header: sombra ao rolar
   * ========================================================= */
  const header = document.querySelector('.site-header');
  if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* =========================================================
   *  Galeria do produto
   * ========================================================= */
  const mainImg = document.getElementById('mainImage') || document.querySelector('.gallery-main img');
  const thumbs = document.querySelectorAll('.gallery-thumbs img');
  thumbs.forEach(thumb => {
    thumb.addEventListener('click', function () {
      if (!mainImg) return;
      mainImg.src = this.dataset.full || this.src;
      thumbs.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
    });
    thumb.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        thumb.click();
      }
    });
  });

  /* =========================================================
   *  Sidebar mobile do dashboard
   * ========================================================= */
  const toggle = document.querySelector('.dash-toggle');
  const side = document.querySelector('.dash-side');
  if (toggle && side) {
    toggle.addEventListener('click', () => side.classList.toggle('open'));
    document.addEventListener('click', e => {
      if (window.innerWidth < 992 && side.classList.contains('open')
          && !side.contains(e.target) && !toggle.contains(e.target)) {
        side.classList.remove('open');
      }
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && side.classList.contains('open')) {
        side.classList.remove('open');
      }
    });
  }

  /* =========================================================
   *  Confirmação de ações destrutivas
   * ========================================================= */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm)) e.preventDefault();
    });
  });

  /* =========================================================
   *  Auto-submit de filtros
   * ========================================================= */
  document.querySelectorAll('[data-auto-submit]').forEach(el => {
    el.addEventListener('change', () => el.closest('form').submit());
  });

  /* =========================================================
   *  Preview de imagem
   * ========================================================= */
  document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
    input.addEventListener('change', function () {
      const target = document.querySelector(this.dataset.preview);
      if (target && this.files[0]) target.src = URL.createObjectURL(this.files[0]);
    });
  });

  /* =========================================================
   *  Compartilhar
   * ========================================================= */
  document.querySelectorAll('[data-share]').forEach(btn => {
    btn.addEventListener('click', async function () {
      const url = this.dataset.share;
      const title = this.dataset.title || document.title;
      try {
        if (navigator.share) {
          await navigator.share({ title, url });
        } else {
          await navigator.clipboard.writeText(url);
          showToast('Link copiado para a área de transferência!');
        }
      } catch (err) {
        if (err.name !== 'AbortError') prompt('Copie o link:', url);
      }
    });
  });

  /* =========================================================
   *  Chips de tamanho
   * ========================================================= */
  document.querySelectorAll('.size-chip').forEach(chip => {
    chip.addEventListener('click', function () {
      const input = document.getElementById('sizesInput');
      if (!input) return;
      let sizes = input.value.split(',').map(s => s.trim()).filter(Boolean);
      const value = this.dataset.size;
      if (sizes.includes(value)) {
        sizes = sizes.filter(s => s !== value);
        this.classList.remove('active');
      } else {
        sizes.push(value);
        this.classList.add('active');
      }
      input.value = sizes.join(',');
    });
  });

  /* =========================================================
   *  Favoritos (AJAX)
   * ========================================================= */
  document.querySelectorAll('[data-fav-product],[data-fav-store]').forEach(btn => {
    btn.addEventListener('click', function () {
      const isProduct = this.hasAttribute('data-fav-product');
      const id = this.dataset.favProduct || this.dataset.favStore;

      if (this.dataset.logged !== '1') {
        if (confirm('Faça login para favoritar. Ir para o login?')) {
          window.location.href = 'login.php';
        }
        return;
      }

      const icon = this.querySelector('i');
      const baseUrl = document.body.dataset.base || '';
      const original = icon.className;

      // loading
      icon.className = 'fa-solid fa-spinner fa-spin';

      fetch(baseUrl + 'favorite-toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'type=' + (isProduct ? 'product' : 'store') + '&id=' + id
      })
      .then(r => r.json())
      .then(data => {
        if (data.error) { icon.className = original; showToast(data.error, 'error'); return; }
        if (data.active) {
          this.classList.add('active');
          icon.className = 'fa-solid fa-heart';
        } else {
          this.classList.remove('active');
          icon.className = 'fa-regular fa-heart';
        }
      })
      .catch(() => {
        icon.className = original;
        showToast('Não foi possível conectar. Tente novamente.', 'error');
      });
    });
  });

  /* =========================================================
   *  Toast helper
   * ========================================================= */
  function showToast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = 'toast-vitrine ' + type;
    el.textContent = message;
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
      el.classList.remove('show');
      setTimeout(() => el.remove(), 300);
    }, 3000);
  }
  window.showToast = showToast;

});