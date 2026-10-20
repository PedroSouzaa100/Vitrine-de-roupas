<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/catalog.php';

$slug = store_request_slug() ?? trim($_GET['slug'] ?? '');
$pdo  = db();

$st = $pdo->prepare("
    SELECT s.*, se.status AS seller_status, se.id AS seller_id, c.name AS gender_name,
           u.status AS owner_status
    FROM stores s
    JOIN sellers se ON se.id = s.seller_id
    JOIN users u ON u.id = se.user_id
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE s.slug = ? LIMIT 1
");
$st->execute([$slug]);
$store = $st->fetch();

if (!$store || !$store['active'] || $store['seller_status'] !== 'approved' || $store['owner_status'] !== 'active') {
    http_response_code(404);
    $pageTitle = 'Loja não encontrada';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container py-5 text-center"><h1 class="h4">Loja não encontrada ou indisponível.</h1>'
       . '<a class="btn btn-brand mt-3" href="' . base_url('stores.php') . '">Ver outras lojas</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Contador de visualizações da loja
$pdo->prepare("UPDATE stores SET views = views + 1 WHERE id = ?")->execute([$store['id']]);

// Filtros internos
$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 12;

$where  = " AND p.store_id = ? ";
$params = [$store['id']];

if ($q !== '') {
    $where .= " AND (p.name LIKE ? OR p.brand LIKE ?) ";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($category !== '') {
    $where .= " AND p.category_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1) ";
    $params[] = $category;
}

$st = $pdo->prepare("SELECT COUNT(*) " . products_public_from() . $where);
$st->execute($params);
$total = (int) $st->fetchColumn();

$st = $pdo->prepare(
    "SELECT p.*, s.name AS store_name, s.slug AS store_slug, s.city, s.state "
    . products_public_from() . $where
    . " ORDER BY p.featured DESC, p.created_at DESC LIMIT "
    . (($page - 1) * $perPage) . ", $perPage"
);
$st->execute($params);
$products = $st->fetchAll();

$st = $pdo->prepare("SELECT DISTINCT c.* FROM categories c JOIN products p ON p.category_id = c.id WHERE p.store_id = ? AND p.status = 'active' ORDER BY c.sort_order, c.name");
$st->execute([$store['id']]);
$categories = $st->fetchAll();
$showStoreCatalog = true;

$pageTitle = ($store['display_name'] ?: $store['name']) . ' — Loja';
$metaDescription = excerpt($store['description'], 160);

require __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

  <!-- Navegação -->
  <a class="btn btn-outline-brand btn-sm mb-3" href="<?= e(base_url('seller/dashboard.php')) ?>" aria-label="Voltar ao dashboard do vendedor"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i> Voltar ao dashboard</a>
  <nav class="product-nav mb-3" aria-label="Navegação de contexto">
    <a class="btn btn-outline-brand btn-sm" href="<?= base_url('index.php') ?>">
      <i class="fa-solid fa-house me-2"></i> Página inicial
    </a>
  </nav>

  <!-- Banner + Logo da loja -->
  <div class="store-hero mb-4">
    <?php if ($store['banner']): ?>
      <img src="<?= e(img_url($store['banner'])) ?>" alt="Capa <?= e($store['name']) ?>" class="store-hero-banner">
    <?php else: ?>
      <div class="store-hero-banner store-hero-empty"></div>
    <?php endif; ?>
    <div class="store-hero-body">
      <img class="store-hero-logo" src="<?= e(img_url($store['logo'])) ?>" alt="Logo <?= e($store['name']) ?>">
      <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-1">
          <?= e($store['display_name'] ?: $store['name']) ?>
          <?php if ($store['verified']): ?><i class="fa-solid fa-circle-check text-primary"></i><?php endif; ?>
        </h1>
        <div class="store-meta mb-2">
          <span><i class="fa-solid fa-shirt"></i> <?= $total ?> produto(s)</span>
          <?php if ($store['city']): ?>
            <span><i class="fa-solid fa-location-dot"></i> <?= e($store['city']) ?>/<?= e($store['state']) ?></span>
          <?php endif; ?>
          <?php if ($store['gender_name']): ?>
            <span><i class="fa-solid fa-tag"></i> <?= e($store['gender_name']) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($store['description']): ?>
          <p class="text-muted small mb-0"><?= e($store['description']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Ações da loja (somente contato e denúncia) -->
      <div class="store-hero-actions">
        <?php if (whatsapp_number($store['whatsapp'])): ?>
          <a class="btn btn-accent" href="<?= e(wa_link($store['whatsapp'], 'Olá! Vim pela plataforma Vitrine.')) ?>" target="_blank" rel="noopener">
            <i class="fa-brands fa-whatsapp me-2"></i> WhatsApp
          </a>
        <?php endif; ?>
        <?php if ($insta = instagram_url($store['instagram'])): ?>
          <a class="btn btn-outline-brand" href="<?= e($insta) ?>" target="_blank" rel="noopener">
            <i class="fa-brands fa-instagram me-2"></i> Instagram
          </a>
        <?php endif; ?>
        <a class="btn btn-outline-brand" href="<?= base_url('report.php?type=store&id=' . (int) $store['id']) ?>">
          <i class="fa-regular fa-flag me-2"></i> Denunciar loja
        </a>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="h5 fw-bold mb-0">Catálogo da loja</h2>
    <a class="btn btn-brand" href="<?= e(base_url('cart.php')) ?>"><i class="fa-solid fa-cart-shopping me-2" aria-hidden="true"></i> Ver carrinho <span data-cart-count class="cart-counter">0</span></a>
  </div>
  <div class="mb-3" data-cart-message role="status" aria-live="polite"></div>
  <noscript><p class="alert alert-info">Ative JavaScript para adicionar produtos ao carrinho.</p></noscript>
  <!-- Filtros internos -->
  <form class="form-card mb-4" method="get">
    <input type="hidden" name="slug" value="<?= e($store['slug']) ?>">
    <div class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label" for="storeQ">Buscar na loja</label>
        <input type="search" id="storeQ" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Nome ou marca…">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="storeCategory">Categoria</label>
        <select id="storeCategory" name="category" class="form-select">
          <option value="">Todas</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= $category === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-brand w-100">Filtrar</button>
      </div>
    </div>
  </form>

  <?php if (!$products): ?>
    <div class="form-card text-center py-5">
      <i class="fa-solid fa-shirt fa-2x text-muted mb-3"></i>
      <h2 class="h6 fw-bold">Nenhum produto encontrado</h2>
      <p class="text-muted small mb-0">Esta loja ainda não publicou produtos nesta categoria.</p>
    </div>
  <?php else: ?>
    <div class="row g-3 g-md-4">
      <?php foreach ($products as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <?php include __DIR__ . '/includes/product-card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-4"><?= paginate_links($total, $perPage, $page) ?></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
