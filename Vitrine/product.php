<?php
require_once __DIR__ . '/includes/auth.php';

$slug = trim($_GET['slug'] ?? '');
$pdo  = db();

$st = $pdo->prepare("
    SELECT p.*, s.id AS store_id, s.name AS store_name, s.slug AS store_slug,
           s.logo AS store_logo, s.whatsapp AS store_whatsapp, s.instagram AS store_instagram,
           s.city, s.state, s.verified AS store_verified,
           se.status AS seller_status, c.name AS category_name,
           sc.name AS subcategory_name
    FROM products p
    JOIN stores s  ON s.id = p.store_id
    JOIN sellers se ON se.id = s.seller_id
    LEFT JOIN categories c  ON c.id = p.category_id
    LEFT JOIN categories sc ON sc.id = p.subcategory_id
    WHERE p.slug = ? LIMIT 1
");
$st->execute([$slug]);
$product = $st->fetch();

if (!$product || $product['status'] !== 'active'
    || $product['seller_status'] !== 'approved') {
    http_response_code(404);
    $pageTitle = 'Produto não encontrado';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container py-5 text-center"><h1 class="h4">Produto não encontrado.</h1>'
       . '<a class="btn btn-brand mt-3" href="' . base_url('search.php') . '">Ver outros produtos</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Contador de views
$pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$product['id']]);
$pdo->prepare("INSERT INTO product_views (product_id, user_id, ip_address) VALUES (?,?,?)")
    ->execute([$product['id'], current_user()['id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? null]);

// Imagens adicionais
$st = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
$st->execute([$product['id']]);
$gallery = $st->fetchAll();

// Relacionados
$st = $pdo->prepare("
    SELECT p.*, s.name AS store_name, s.slug AS store_slug, s.city, s.state
    " . products_public_from() . "
      AND p.category_id = ? AND p.id <> ?
    ORDER BY RAND() LIMIT 4
");
$st->execute([$product['category_id'], $product['id']]);
$related = $st->fetchAll();

$hasPromo = !empty($product['promo_price']) && $product['promo_price'] > 0 && $product['promo_price'] < $product['price'];

$waMsg = "Olá! Encontrei o produto {$product['name']} na loja {$product['store_name']} "
       . "pela plataforma Vitrine e gostaria de saber mais informações.";

$pageTitle = $product['name'] . ' — ' . $product['store_name'];
$metaDescription = excerpt($product['description'], 160);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">

  <!-- Navegação: voltar para a loja e para todas as abas -->
  <nav class="product-nav mb-3" aria-label="Navegação de contexto">
    <a class="btn btn-outline-brand btn-sm" href="<?= e(url_store($product['store_slug'])) ?>">
      <i class="fa-solid fa-arrow-left me-2"></i> Voltar para <?= e($product['store_name']) ?>
    </a>
  </nav>

  <div class="row g-4">
    <!-- Galeria -->
    <div class="col-lg-6">
      <div class="gallery-main">
        <img id="mainImage" src="<?= e(img_url($product['main_image'])) ?>" alt="<?= e($product['name']) ?>">
      </div>
      <?php if ($gallery): ?>
        <div class="gallery-thumbs">
          <img src="<?= e(img_url($product['main_image'])) ?>" alt="Miniatura 1" class="active">
          <?php foreach ($gallery as $i => $img): ?>
            <img src="<?= e(img_url($img['image_path'])) ?>" alt="Miniatura <?= $i + 2 ?>" loading="lazy">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="col-lg-6">
      <a class="product-store mb-2 d-inline-block" href="<?= e(url_store($product['store_slug'])) ?>">
        <i class="fa-solid fa-store"></i> <?= e($product['store_name']) ?>
        <?php if ($product['store_verified']): ?><i class="fa-solid fa-circle-check text-primary"></i><?php endif; ?>
      </a>

      <h1 class="h3 fw-bold mb-2"><?= e($product['name']) ?></h1>

      <?php if ($product['brand']): ?>
        <div class="text-muted small mb-3">Marca: <strong><?= e($product['brand']) ?></strong></div>
      <?php endif; ?>

      <div class="price-block mb-3">
        <?php if ($hasPromo): ?>
          <span class="old"><?= money($product['price']) ?></span>
          <span class="now text-accent"><?= money($product['promo_price']) ?></span>
          <span class="badge-promo" style="position:static">
            -<?= round(100 - ($product['promo_price'] / $product['price'] * 100)) ?>%
          </span>
        <?php else: ?>
          <span class="now"><?= money($product['price']) ?></span>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <?php if ($product['stock'] > 0): ?>
          <span class="status status-approved">Em estoque (<?= (int) $product['stock'] ?>)</span>
        <?php else: ?>
          <span class="status status-rejected">Sem estoque</span>
        <?php endif; ?>
      </div>

      <?php if ($product['sizes']): ?>
        <div class="mb-3">
          <div class="form-label mb-2">Tamanhos disponíveis</div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach (explode(',', $product['sizes']) as $size): ?>
              <span class="pill"><?= e(trim($size)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($product['colors']): ?>
        <div class="mb-3">
          <div class="form-label mb-2">Cores disponíveis</div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach (explode(',', $product['colors']) as $color): ?>
              <span class="pill"><?= e(trim($color)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($product['description']): ?>
        <div class="mb-4">
          <div class="form-label mb-2">Descrição</div>
          <p class="text-muted" style="white-space:pre-line"><?= e($product['description']) ?></p>
        </div>
      <?php endif; ?>

      <!-- Ação principal: WhatsApp -->
      <div class="d-grid gap-2 mb-3">
        <?php if ($product['store_whatsapp']): ?>
          <a href="<?= e(wa_link($product['store_whatsapp'], $waMsg)) ?>"
             target="_blank" rel="noopener" class="btn btn-accent py-3">
            <i class="fa-brands fa-whatsapp me-2"></i> Tenho interesse — falar no WhatsApp
          </a>
        <?php else: ?>
          <div class="alert alert-warning mb-0 small">Este vendedor ainda não cadastrou WhatsApp.</div>
        <?php endif; ?>

        <a class="btn btn-outline-brand" href="<?= base_url('report.php?type=product&id=' . (int) $product['id']) ?>">
          <i class="fa-regular fa-flag me-2"></i> Denunciar produto
        </a>
      </div>

      <!-- Card do vendedor -->
      <div class="form-card p-3">
        <div class="d-flex align-items-center gap-3">
          <img src="<?= e(img_url($product['store_logo'])) ?>" alt="<?= e($product['store_name']) ?>"
               style="width:54px;height:54px;border-radius:14px;object-fit:cover">
          <div class="flex-grow-1">
            <div class="fw-semibold small">Vendido por</div>
            <a class="fw-bold" href="<?= e(url_store($product['store_slug'])) ?>">
              <?= e($product['store_name']) ?>
            </a>
            <?php if ($product['city']): ?>
              <div class="text-muted small"><?= e($product['city']) ?>/<?= e($product['state']) ?></div>
            <?php endif; ?>
          </div>
          <a href="<?= e(url_store($product['store_slug'])) ?>" class="btn btn-sm btn-outline-brand">Ver loja</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Relacionados -->
  <?php if ($related): ?>
    <section class="section">
      <div class="section-head"><div><h2>Produtos relacionados</h2></div></div>
      <div class="row g-3 g-md-4">
        <?php foreach ($related as $p): ?>
          <div class="col-6 col-md-3">
            <?php include __DIR__ . '/includes/product-card.php'; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
// Troca de imagem da galeria
document.querySelectorAll('.gallery-thumbs img').forEach(function(t){
  t.addEventListener('click', function(){
    document.getElementById('mainImage').src = this.src;
    document.querySelectorAll('.gallery-thumbs img').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
  });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>