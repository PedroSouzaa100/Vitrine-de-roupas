</main>

<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="brand brand-light mb-3">
          <span class="brand-mark"><i class="fa-solid fa-shirt"></i></span>
          <span class="brand-text"><?= e(SITE_NAME) ?></span>
        </div>
        <p class="text-muted-soft"><?= e(SITE_TAGLINE) ?>. Conectamos clientes a lojas de moda de todo o Brasil.</p>
        <div class="social-row">
          <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
          <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Navegar</h6>
        <ul class="footer-links">
          <li><a href="<?= base_url('index.php') ?>">Início</a></li>
          <li><a href="<?= base_url('stores.php') ?>">Lojas</a></li>
          <li><a href="<?= base_url('search.php') ?>">Produtos</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Vendedores</h6>
        <ul class="footer-links">
          <li><a href="<?= base_url('register-seller.php') ?>">Criar minha loja</a></li>
          <li><a href="<?= base_url('login.php') ?>">Entrar</a></li>
          <li><a href="<?= base_url('page.php?slug=termos') ?>">Termos de uso</a></li>
          <li><a href="<?= base_url('page.php?slug=privacidade') ?>">Privacidade</a></li>
        </ul>
      </div>
    </div>
    <hr>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <small class="text-muted-soft">© <?= date('Y') ?> <?= e(SITE_NAME) ?>. Todos os direitos reservados.</small>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/main.js') ?>"></script>
</body>
</html>