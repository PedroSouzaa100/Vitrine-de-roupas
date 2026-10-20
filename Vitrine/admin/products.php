<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();

// Excluir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $id = (int) $_POST['delete_id'];
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    log_admin('delete_product', 'product', $id);
    set_flash('success', 'Produto removido.');
    redirect('admin/products.php');
}

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where  = " WHERE 1=1 ";
$params = [];
if ($q !== '') {
    $where .= " AND (p.name LIKE ? OR s.name LIKE ?) ";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if (in_array($status, ['active','inactive'], true)) {
    $where .= " AND p.status = ? ";
    $params[] = $status;
}

$from = " FROM products p JOIN stores s ON s.id = p.store_id " . $where;

$st = $pdo->prepare("SELECT COUNT(*) " . $from);
$st->execute($params);
$total = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT p.*, s.name AS store_name " . $from
    . " ORDER BY p.created_at DESC LIMIT " . (($page - 1) * $perPage) . ", $perPage");
$st->execute($params);
$products = $st->fetchAll();
?>
<form method="get" class="d-flex gap-2 flex-wrap mb-3">
  <input type="search" name="q" class="form-control" placeholder="Buscar…" value="<?= e($q) ?>" style="min-width:220px">
  <select name="status" class="form-select" style="min-width:160px">
    <option value="">Todos</option>
    <option value="active"   <?= $status === 'active' ? 'selected' : '' ?>>Ativos</option>
    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inativos</option>
  </select>
  <button class="btn btn-outline-brand">Filtrar</button>
</form>

<div class="table-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>Produto</th><th>Loja</th><th>Preço</th><th>Status</th><th class="text-end">Ações</th></tr>
      </thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <img class="thumb-sm" src="<?= e(img_url($p['main_image'])) ?>" alt="">
              <div>
                <div class="fw-semibold"><?= e($p['name']) ?></div>
                <small class="text-muted"><?= e($p['brand'] ?: '—') ?></small>
              </div>
            </div>
          </td>
          <td><?= e($p['store_name']) ?></td>
          <td><?= money($p['promo_price'] > 0 && $p['promo_price'] < $p['price'] ? $p['promo_price'] : $p['price']) ?></td>
          <td><span class="status <?= $p['status'] === 'active' ? 'status-approved' : 'status-suspended' ?>">
            <?= $p['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
          <td class="text-end">
            <a class="btn btn-sm btn-icon" target="_blank" href="<?= e(url_product($p['slug'])) ?>">
              <i class="fa-solid fa-eye"></i>
            </a>
            <form method="post" class="d-inline" onsubmit="return confirm('Excluir este produto?')">
              <?= csrf_field() ?>
              <input type="hidden" name="delete_id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-sm btn-icon"><i class="fa-regular fa-trash-can"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3"><?= paginate_links($total, $perPage, $page) ?></div>

<?php require __DIR__ . '/includes/footer.php'; ?>
