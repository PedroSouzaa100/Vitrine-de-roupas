<?php
require_once __DIR__ . '/includes/auth.php';

if (store_request_slug() !== null) {
    require __DIR__ . '/store.php';
    exit;
}

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

<div class="container py-4 home-content">
<nav class="discovery-nav" aria-label="Explore os catálogos">
  <span>ENCONTRE SEU ESTILO</span>
  <a href="<?= base_url('search.php') ?>">Todos os produtos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
  <?php foreach ($genders as $g): ?>
    <a href="<?= base_url('search.php?gender=' . urlencode($g['slug'])) ?>"><?= e($g['name']) ?></a>
  <?php endforeach; ?>
  <a href="<?= base_url('search.php?promo=1') ?>"><i class="fa-solid fa-tag" aria-hidden="true"></i> Em promoção</a>
</nav>
<section class="hero" aria-labelledby="hero-title">
  <div class="hero-inner hero-layout">
    <div class="hero-copy">
      <span class="badge-soft mb-3"><i class="fa-solid fa-shirt" aria-hidden="true"></i> Moda feminina, masculina e unissex</span>
      <h1 id="hero-title">Seu estilo.<br><em>Novas possibilidades.</em></h1>
      <p class="mt-3 mb-4">Diferentes lojas, estilos e possibilidades para você. Explore moda feminina, masculina e unissex e converse diretamente com quem vende.</p>
      <div class="hero-actions">
        <a href="<?= base_url('search.php') ?>" class="btn btn-brand">Explorar produtos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        <a href="<?= base_url('stores.php') ?>" class="btn btn-outline-brand"><i class="fa-solid fa-store" aria-hidden="true"></i> Conhecer as lojas</a>
      </div>
      <div class="hero-note"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Descubra online. Converse com a loja. Compre do seu jeito.</div>
    </div>
    <div class="hero-showcase">
      <?php foreach (array_slice($recentProducts, 0, 2) as $rp): ?>
        <a href="<?= e(url_product($rp['slug'])) ?>" class="hero-look">
          <img src="<?= e(img_url($rp['main_image'])) ?>" alt="<?= e($rp['name']) ?>" width="400" height="500" decoding="async">
          <div class="hero-look-caption">
            <span><?= e($rp['name']) ?></span>
            <strong><?= money(!empty($rp['promo_price']) && $rp['promo_price'] > 0 && $rp['promo_price'] < $rp['price'] ? $rp['promo_price'] : $rp['price']) ?></strong>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$recentProducts): ?>
        <div class="hero-placeholder">
          <i class="fa-solid fa-store" aria-hidden="true"></i>
          <h2>Um novo espaço para a sua loja.</h2>
          <p>Publique seu catálogo e ajude novos clientes a descobrir seus produtos.</p>
          <a class="link-more mt-3" href="<?= base_url('register-seller.php') ?>">Criar minha loja <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<div class="shopping-guide" aria-label="Como comprar no Vitrine">
  <div class="shopping-guide-item"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><div><strong>Encontre sua próxima peça</strong><p>Explore categorias, novidades e promoções.</p></div></div>
  <div class="shopping-guide-item"><i class="fa-solid fa-store" aria-hidden="true"></i><div><strong>Conheça quem vende</strong><p>Veja o catálogo e as informações da loja.</p></div></div>
  <div class="shopping-guide-item"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><div><strong>Converse e combine a compra</strong><p>Tire dúvidas diretamente com o vendedor.</p></div></div>
</div>

<!-- Categorias populares -->
<section class="section pt-0">
  <div class="section-head">
    <div>
      <span class="section-kicker">Explore do seu jeito</span>
      <h2>O que você procura?</h2>
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
      <span class="section-kicker">Novas descobertas</span>
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
    <div class="empty-state"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><h3>Novas peças estão a caminho</h3><p>Enquanto isso, conheça as lojas e seus catálogos.</p><a class="btn btn-outline-brand" href="<?= base_url('stores.php') ?>">Explorar lojas</a></div>
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
      <span class="section-kicker">Conheça os vendedores</span>
      <h2>Lojas em destaque</h2>
      <p>Explore os catálogos e encontre novas lojas favoritas</p>
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
              <?php if ($store['verified']): ?><i class="fa-solid fa-circle-check text-accent small"></i><?php endif; ?>
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
    <span class="section-kicker text-white">Para quem empreende</span>
    <h2>Sua loja merece ser descoberta.</h2>
    <p class="mx-auto mb-4">
      Crie seu catálogo, adicione produtos com fotos, tamanhos e cores e receba contatos direto no seu WhatsApp.
    </p>
    <a href="<?= base_url('register-seller.php') ?>" class="btn btn-brand px-4">
      <i class="fa-solid fa-store me-2"></i> Criar minha loja
    </a>
  </div>
</section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
