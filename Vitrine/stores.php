<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Lojas';
$pdo = db();

$q      = trim($_GET['q'] ?? '');
$gender = trim($_GET['gender'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$where = " WHERE s.active = 1 AND se.status = 'approved' ";
$params = [];

if ($q !== '') {
    $where .= " AND (s.name LIKE ? OR s.display_name LIKE ? OR s.description LIKE ?) ";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($gender !== '') {
    $where .= " AND s.category_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1) ";
    $params[] = $gender;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM stores s JOIN sellers se ON se.id = s.seller_id" . $where);
$st->execute($params);
$total = (int) $st->fetchColumn();

$sql = "SELECT s.*,
          (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id AND p.status='active') AS total_products
        FROM stores s JOIN sellers se ON se.id = s.seller_id"
     . $where . " ORDER BY s.is_featured DESC, s.views DESC LIMIT "
     . (($page - 1) * $perPage) . ", $perPage";

$st = $pdo->prepare($sql);
$st->execute($params);
$stores = $st->fetchAll();

$genders = get_categories('gender');

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="section-head">
    <div>
      <h1 class="h4 fw-bold mb-1">Lojas na plataforma</h1>
      <p class="text-muted mb-0"><?= $total ?> loja(s) disponíveis</p>
    </div>
  </div>

  <form class="form-card mb-4" method="get">
    <div class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Buscar loja</label>
        <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Nome da loja…">
      </div>
      <div class="col-md-4">
        <label class="form-label">Público</label>
        <select name="gender" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($genders as $g): ?>
            <option value="<?= e($g['slug']) ?>" <?= $gender === $g['slug'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-brand w-100">Filtrar</button>
      </div>
    </div>
  </form>

  <div class="row g-3 g-md-4">
    <?php foreach ($stores as $store): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="store-card">
          <a href="<?= e(url_store($store['slug'])) ?>" class="store-cover d-block">
            <?php if ($store['banner']): ?>
              <img src="<?= e(img_url($store['banner'])) ?>" alt="Capa <?= e($store['name']) ?>" loading="lazy">
            <?php endif; ?>
          </a>
          <div class="store-card-body">
            <a href="<?= e(url_store($store['slug'])) ?>">
              <img class="store-logo" src="<?= e(img_url($store['logo'])) ?>" alt="Logo <?= e($store['name']) ?>" loading="lazy">
            </a>
            <h2 class="store-name">
              <a href="<?= e(url_store($store['slug'])) ?>"><?= e($store['display_name'] ?: $store['name']) ?></a>
            </h2>
            <p class="small text-muted mb-2"><?= e(excerpt($store['description'], 90)) ?></p>
            <div class="store-meta">
              <span><i class="fa-solid fa-shirt"></i> <?= (int) $store['total_products'] ?> produtos</span>
              <?php if ($store['city']): ?>
                <span><i class="fa-solid fa-location-dot"></i> <?= e($store['city']) ?>/<?= e($store['state']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$stores): ?>
    <div class="alert alert-light border">Nenhuma loja encontrada.</div>
  <?php endif; ?>

  <div class="mt-4"><?= paginate_links($total, $perPage, $page) ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
