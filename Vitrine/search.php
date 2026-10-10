<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Buscar produtos';
$metaDescription = 'Busque produtos de moda por categoria, marca, faixa de preço e loja.';

$pdo = db();

// ---------- Parâmetros de filtro ----------
$q         = trim($_GET['q'] ?? '');
$gender    = trim($_GET['gender'] ?? '');
$category  = trim($_GET['category'] ?? '');
$storeSlug = trim($_GET['store'] ?? '');
$brand     = trim($_GET['brand'] ?? '');
$min       = isset($_GET['min']) && $_GET['min'] !== '' ? (float) $_GET['min'] : null;
$max       = isset($_GET['max']) && $_GET['max'] !== '' ? (float) $_GET['max'] : null;
$promo     = !empty($_GET['promo']);
$available = !empty($_GET['available']);
$sort      = $_GET['sort'] ?? 'recent';
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 12;

// ---------- Monta WHERE dinâmico ----------
$where  = '';
$params = [];

if ($q !== '') {
    $where .= " AND (p.name LIKE ? OR p.brand LIKE ? OR s.name LIKE ? OR p.description LIKE ?) ";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($gender !== '') {
    $where .= " AND s.category_id = (SELECT id FROM categories WHERE slug = ? AND type='gender' LIMIT 1) ";
    $params[] = $gender;
}
if ($category !== '') {
    $where .= " AND p.category_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1) ";
    $params[] = $category;
}
if ($storeSlug !== '') {
    $where .= " AND s.slug = ? ";
    $params[] = $storeSlug;
}
if ($brand !== '') {
    $where .= " AND p.brand LIKE ? ";
    $params[] = '%' . $brand . '%';
}
if ($min !== null) {
    $where .= " AND COALESCE(NULLIF(p.promo_price,0), p.price) >= ? ";
    $params[] = $min;
}
if ($max !== null && $max > 0) {
    $where .= " AND COALESCE(NULLIF(p.promo_price,0), p.price) <= ? ";
    $params[] = $max;
}
if ($promo) {
    $where .= " AND p.promo_price IS NOT NULL AND p.promo_price > 0 AND p.promo_price < p.price ";
}
if ($available) {
    $where .= " AND p.stock > 0 ";
}

// ---------- Ordenação ----------
$orderBy = match ($sort) {
    'price_asc'  => 'COALESCE(NULLIF(p.promo_price,0), p.price) ASC',
    'price_desc' => 'COALESCE(NULLIF(p.promo_price,0), p.price) DESC',
    'views'      => 'p.views DESC',
    default      => 'p.created_at DESC',
};

$baseFrom = products_public_from() . $where;

// ---------- Total ----------
$st = $pdo->prepare("SELECT COUNT(*) " . $baseFrom);
$st->execute($params);
$total = (int) $st->fetchColumn();

// ---------- Resultados paginados ----------
$sql = "SELECT p.*, s.name AS store_name, s.slug AS store_slug, s.city, s.state "
     . $baseFrom
     . " ORDER BY $orderBy LIMIT " . (($page - 1) * $perPage) . ", $perPage";
$st = $pdo->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

// ---------- Dados auxiliares para os filtros ----------
$categories = get_categories('category');
$genders    = get_categories('gender');
$stores     = $pdo->query("
    SELECT s.id, s.name, s.slug
    FROM stores s
    JOIN sellers se ON se.id = s.seller_id
    WHERE s.active = 1 AND se.status = 'approved'
    ORDER BY s.name
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>">Início</a></li>
      <li class="breadcrumb-item active">Buscar</li>
    </ol>
  </nav>

  <div class="row g-4">

    <!-- Filtros -->
    <aside class="col-lg-3">
      <form method="get" class="form-card">
        <h2 class="h6 fw-bold mb-3">Filtros</h2>

        <div class="mb-3">
          <label class="form-label" for="fGender">Público</label>
          <select id="fGender" name="gender" class="form-select" data-auto-submit>
            <option value="">Todos</option>
            <?php foreach ($genders as $g): ?>
              <option value="<?= e($g['slug']) ?>" <?= $gender === $g['slug'] ? 'selected' : '' ?>>
                <?= e($g['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="fCategory">Categoria</label>
          <select id="fCategory" name="category" class="form-select" data-auto-submit>
            <option value="">Todas</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= e($c['slug']) ?>" <?= $category === $c['slug'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="fStore">Loja</label>
          <select id="fStore" name="store" class="form-select" data-auto-submit>
            <option value="">Todas</option>
            <?php foreach ($stores as $s): ?>
              <option value="<?= e($s['slug']) ?>" <?= $storeSlug === $s['slug'] ? 'selected' : '' ?>>
                <?= e($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label" for="fMin">Preço mín.</label>
            <input type="number" step="0.01" min="0" id="fMin" name="min"
                   class="form-control" value="<?= e($_GET['min'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="form-label" for="fMax">Preço máx.</label>
            <input type="number" step="0.01" min="0" id="fMax" name="max"
                   class="form-control" value="<?= e($_GET['max'] ?? '') ?>">
          </div>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="promo" value="1"
                 id="fPromo" <?= $promo ? 'checked' : '' ?> data-auto-submit>
          <label class="form-check-label small" for="fPromo">Somente promoções</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="available" value="1"
                 id="fAvail" <?= $available ? 'checked' : '' ?> data-auto-submit>
          <label class="form-check-label small" for="fAvail">Somente disponíveis</label>
        </div>

        <button class="btn btn-brand w-100">Aplicar filtros</button>
        <a href="<?= base_url('search.php') ?>" class="btn btn-outline-brand w-100 mt-2">Limpar</a>
      </form>
    </aside>

    <!-- Resultados -->
    <div class="col-lg-9">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h1 class="h5 fw-bold mb-0">
            <?= $q !== '' ? 'Resultados para "' . e($q) . '"' : 'Todos os produtos' ?>
          </h1>
          <small class="text-muted"><?= $total ?> produto(s) encontrado(s)</small>
        </div>

        <form method="get" class="d-flex align-items-center gap-2">
          <?php foreach ($_GET as $k => $v):
            if ($k === 'sort' || $k === 'page') continue;
            if (is_array($v)) continue;
          ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
          <?php endforeach; ?>
          <label class="small text-muted mb-0" for="fSort">Ordenar</label>
          <select id="fSort" name="sort" class="form-select form-select-sm"
                  style="width:auto" data-auto-submit>
            <option value="recent"     <?= $sort === 'recent' ? 'selected' : '' ?>>Mais recentes</option>
            <option value="price_asc"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Menor preço</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Maior preço</option>
            <option value="views"      <?= $sort === 'views' ? 'selected' : '' ?>>Mais visualizados</option>
          </select>
        </form>
      </div>

      <?php if (!$products): ?>
        <div class="form-card text-center py-5">
          <i class="fa-solid fa-magnifying-glass fa-2x text-muted mb-3"></i>
          <h2 class="h6 fw-bold">Nenhum produto encontrado</h2>
          <p class="text-muted small mb-3">Tente ajustar os filtros ou buscar por outros termos.</p>
          <a href="<?= base_url('search.php') ?>" class="btn btn-outline-brand">Ver todos os produtos</a>
        </div>
      <?php else: ?>
        <div class="row g-3 g-md-4">
          <?php foreach ($products as $p): ?>
            <div class="col-6 col-md-4">
              <?php include __DIR__ . '/includes/product-card.php'; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-4"><?= paginate_links($total, $perPage, $page) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>