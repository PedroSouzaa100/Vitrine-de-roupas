<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = SITE_NAME . ' — Marketplace de moda';
$metaDescription = 'Descubra lojas de roupas masculinas, femininas e unissex. Catálogos completos, produtos em promoção e contato direto pelo WhatsApp.';

$pdo = db();

// Banner / destaques
$featuredStores = $pdo->query("
    SELECT s.*, c.name AS gender_name,
           (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id AND p.status='active') AS total_products
    FROM stores s
    JOIN sellers se ON se.id = s.seller_id
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE s.active = 1 AND se.status = 'approved'
    ORDER BY s.is_featured DESC, s.views DESC
    LIMIT 8
")->fetchAll();

// Produtos recentes
$recentProducts = $pdo->query("
    SELECT p.*, s.name AS store_name, s.slug AS store_slug, s.city, s.state
    " . products_public_from() . "
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

// Promoções
$promoProducts = $pdo->query("
    SELECT p.*, s.name AS store_name, s.slug AS store_slug, s.city, s.state
    " . products_public_from() . "
      AND p.promo_price IS NOT NULL AND p.promo_price > 0 AND p.promo_price < p.price
    ORDER BY (p.price - p.promo_price) DESC LIMIT 4
")->fetchAll();

// Lojas mais visualizadas
$topStores = $pdo->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id AND p.status='active') AS total_products
    FROM stores s
    JOIN sellers se ON se.id = s.seller_id
    WHERE s.active = 1 AND se.status = 'approved'
    ORDER BY s.views DESC LIMIT 4
")->fetchAll();

$categories = get_categories('category');
$genders = get_categories('gender');

require __DIR__ . '/includes/header.php';
?>

<section class="hero mb-4">
  <div class="hero-inner">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="badge-soft mb-3 d-inline-block">
          <i class="fa-solid fa-bolt"></i> Moda masculina, feminina e unissex
        </span>
        <h1>Encontre seu estilo nas melhores lojas de moda.</h1>
        <p class="mt-3 mb-4">
          Catálogos atualizados, contato direto com o vendedor e produtos de lojas verificadas de todo o Brasil.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= base_url('search.php') ?>" class="btn btn-accent px-4">
            <i class="fa-solid fa-bag-shopping me-2"></i> Explorar produtos
          </a>
          <a href="<?= base_url('register-seller.php') ?>" class="btn btn-outline-light rounded-pill px-4">
            Criar minha loja
          </a>
        </div>
      </div>
      <div class="col-lg-5 d-none d-lg-block">
        <div class="row g-3">
          <?php foreach (array_slice($recentProducts, 0, 2) as $rp): ?>
            <div class="col-6">
              <a href="<?= e(url_product($rp['slug'])) ?>" class="d-block rounded-4 overflow-hidden">
                <img src="<?= e(img_url($rp['main_image'])) ?>" alt="<?= e($rp['name']) ?>"
                     style="aspect-ratio:3/4;object-fit:cover;width:100%">
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Categorias populares -->
<section class="section pt-0">
  <div class="section-head">
    <div>
      <h2>Categorias populares</h2>
      <p>Navegue pelo que você procura</p>
    </div>
    <a class="link-more" href="<?= base_url('search.php') ?>">Ver tudo <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <div class="row g-3">
    <?php foreach (array_slice($categories, 0, 8) as $c): ?>
      <div class="col-6 col-md-3 col-lg-3 col-xl">
        <a class="cat-chip" href="<?= base_url('search.php?category=' . urlencode($c['slug'])) ?>">
          <i class="fa-solid fa-tag"></i>
          <span><?= e($c['name']) ?></span>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Produtos recentes -->
<section class="section pt-0">
  <div class="section-head">
    <div>
      <h2>Chegou agora</h2>
      <p>Os produtos mais recentes das lojas</p>
    </div>
    <a class="link-more" href="<?= base_url('search.php?sort=recent') ?>">Ver mais <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <?php if ($recentProducts): ?>
    <div class="row g-3 g-md-4">
      <?php foreach ($recentProducts as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <?php include __DIR__ . '/includes/product-card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-light border">Nenhum produto publicado ainda.</div>
  <?php endif; ?>
</section>

<!-- Promoções -->
<?php if ($promoProducts): ?>
<section class="section pt-0">
  <div class="section-head">
    <div>
      <h2>Em promoção</h2>
      <p>Ofertas selecionadas das lojas parceiras</p>
    </div>
    <a class="link-more" href="<?= base_url('search.php?promo=1') ?>">Ver mais <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <div class="row g-3 g-md-4">
    <?php foreach ($promoProducts as $p): ?>
      <div class="col-6 col-md-3">
        <?php include __DIR__ . '/includes/product-card.php'; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Lojas em destaque -->
<section class="section pt-0">
  <div class="section-head">
    <div>
      <h2>Lojas em destaque</h2>
      <p>Vendedores verificados na plataforma</p>
    </div>
    <a class="link-more" href="<?= base_url('stores.php') ?>">Ver todas <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <div class="row g-3 g-md-4">
    <?php foreach ($featuredStores as $store): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <div class="store-card">
          <a href="<?= e(url_store($store['slug'])) ?>" class="store-cover d-block">
            <?php if ($store['banner']): ?>
              <img src="<?= e(img_url($store['banner'])) ?>" alt="Capa da loja <?= e($store['name']) ?>" loading="lazy">
            <?php endif; ?>
          </a>
          <div class="store-card-body">
            <a href="<?= e(url_store($store['slug'])) ?>">
              <img class="store-logo" src="<?= e(img_url($store['logo'])) ?>" alt="Logo <?= e($store['name']) ?>" loading="lazy">
            </a>
            <h3 class="store-name">
              <a href="<?= e(url_store($store['slug'])) ?>"><?= e($store['display_name'] ?: $store['name']) ?></a>
              <?php if ($store['verified']): ?><i class="fa-solid fa-circle-check text-primary small"></i><?php endif; ?>
            </h3>
            <div class="store-meta">
              <span><i class="fa-solid fa-shirt"></i> <?= (int) $store['total_products'] ?> produtos</span>
              <?php if ($store['city']): ?>
                <span><i class="fa-solid fa-location-dot"></i> <?= e($store['city']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- CTA Vendedor -->
<section class="section pt-0">
  <div class="cta-seller">
    <h2>Tenha sua loja online em poucos minutos.</h2>
    <p class="text-muted mx-auto mb-4" style="max-width:560px">
      Crie seu catálogo, adicione produtos com fotos, tamanhos e cores e receba contatos direto no seu WhatsApp.
    </p>
    <a href="<?= base_url('register-seller.php') ?>" class="btn btn-brand px-4">
      <i class="fa-solid fa-store me-2"></i> Criar minha loja
    </a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>