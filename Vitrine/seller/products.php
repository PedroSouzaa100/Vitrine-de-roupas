<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$storeId = (int) $store['id'];

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where  = " WHERE store_id = ? ";
$params = [$storeId];

if ($q !== '') {
    $where .= " AND name LIKE ? ";
    $params[] = '%' . $q . '%';
}
if (in_array($status, ['active','inactive'], true)) {
    $where .= " AND status = ? ";
    $params[] = $status;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM products" . $where);
$st->execute($params);
$total = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT * FROM products" . $where . " ORDER BY created_at DESC LIMIT "
    . (($page - 1) * $perPage) . ", $perPage");
$st->execute($params);
$products = $st->fetchAll();
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap">
    <input type="search" name="q" class="form-control" placeholder="Buscar…" value="<?= e($q) ?>" style="min-width:220px">
    <select name="status" class="form-select" style="min-width:160px">
      <option value="">Todos os status</option>
      <option value="active"   <?= $status === 'active' ? 'selected' : '' ?>>Ativos</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inativos</option>
    </select>
    <button class="btn btn-outline-brand">Filtrar</button>
  </form>
  <a href="<?= base_url('seller/product-form.php') ?>" class="btn btn-brand">
    <i class="fa-solid fa-plus"></i> Novo produto
  </a>
</div>

<div class="table-card">
  <?php if (!$products): ?>
    <div class="p-5 text-center">
      <i class="fa-solid fa-shirt fa-2x text-muted mb-3"></i>
      <p class="mb-3">Você ainda não tem produtos cadastrados.</p>
      <a class="btn btn-brand" href="<?= base_url('seller/product-form.php') ?>">Adicionar primeiro produto</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Produto</th>
            <th>Preço</th>
            <th>Estoque</th>
            <th>Status</th>
            <th>Views</th>
            <th class="text-end">Ações</th>
          </tr>
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
              <td>
                <?php if ($p['promo_price'] > 0 && $p['promo_price'] < $p['price']): ?>
                  <div class="text-muted small text-decoration-line-through"><?= money($p['price']) ?></div>
                  <div class="fw-bold text-accent"><?= money($p['promo_price']) ?></div>
                <?php else: ?>
                  <div class="fw-bold"><?= money($p['price']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['stock'] > 0): ?>
                  <span class="badge text-bg-light"><?= (int) $p['stock'] ?> un.</span>
                <?php else: ?>
                  <span class="status status-rejected">Sem estoque</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="status <?= $p['status'] === 'active' ? 'status-approved' : 'status-suspended' ?>">
                  <?= $p['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td><?= (int) $p['views'] ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-icon" href="<?= base_url('seller/product-form.php?id=' . $p['id']) ?>" title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <a class="btn btn-sm btn-icon" target="_blank" href="<?= e(url_product($p['slug'])) ?>" title="Ver">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <a class="btn btn-sm btn-icon" href="<?= base_url('seller/product-delete.php?id=' . $p['id']) ?>"
                   data-confirm="Excluir este produto?" title="Excluir">
                  <i class="fa-regular fa-trash-can"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="mt-3"><?= paginate_links($total, $perPage, $page) ?></div>

<?php require __DIR__ . '/includes/footer.php'; ?>