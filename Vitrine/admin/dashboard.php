<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();

$stat = [
  'sellers'        => (int) $pdo->query("SELECT COUNT(*) FROM sellers")->fetchColumn(),
  'pending'        => (int) $pdo->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn(),
  'approved'       => (int) $pdo->query("SELECT COUNT(*) FROM sellers WHERE status='approved'")->fetchColumn(),
  'rejected'       => (int) $pdo->query("SELECT COUNT(*) FROM sellers WHERE status='rejected'")->fetchColumn(),
  'suspended'      => (int) $pdo->query("SELECT COUNT(*) FROM sellers WHERE status='suspended'")->fetchColumn(),
  'products'       => (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
  'customers'      => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
  'activeStores'   => (int) $pdo->query("SELECT COUNT(*) FROM stores WHERE active=1")->fetchColumn(),
];

$recentSellers = $pdo->query("
    SELECT s.id, s.status, s.created_at, st.name AS store_name, u.name AS owner_name, u.email
    FROM sellers s
    JOIN users u ON u.id = s.user_id
    LEFT JOIN stores st ON st.seller_id = s.id
    ORDER BY s.created_at DESC LIMIT 6
")->fetchAll();

$recentProducts = $pdo->query("
    SELECT p.id, p.name, p.slug, p.main_image, p.created_at, st.name AS store_name
    FROM products p
    JOIN stores st ON st.id = p.store_id
    ORDER BY p.created_at DESC LIMIT 6
")->fetchAll();
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-user-tie"></i></div>
      <div><div class="stat-value"><?= $stat['sellers'] ?></div><div class="stat-label">Vendedores</div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-hourglass"></i></div>
      <div><div class="stat-value"><?= $stat['pending'] ?></div><div class="stat-label">Pendentes</div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-shirt"></i></div>
      <div><div class="stat-value"><?= $stat['products'] ?></div><div class="stat-label">Produtos</div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-users"></i></div>
      <div><div class="stat-value"><?= $stat['customers'] ?></div><div class="stat-label">Clientes</div></div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div><div class="stat-value"><?= $stat['approved'] ?></div><div class="stat-label">Aprovados</div></div></div>
  </div>
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div><div class="stat-value"><?= $stat['rejected'] ?></div><div class="stat-label">Rejeitados</div></div></div>
  </div>
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-store"></i></div>
      <div><div class="stat-value"><?= $stat['activeStores'] ?></div><div class="stat-label">Lojas ativas</div></div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="table-card mb-4">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <h2 class="h6 fw-bold mb-0">Solicitações recentes</h2>
        <a class="link-more" href="<?= base_url('admin/sellers.php') ?>">Ver todos</a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Loja</th><th>Responsável</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($recentSellers as $s): ?>
            <tr>
              <td class="fw-semibold"><?= e($s['store_name'] ?? '—') ?></td>
              <td>
                <div><?= e($s['owner_name']) ?></div>
                <small class="text-muted"><?= e($s['email']) ?></small>
              </td>
              <td>
                <span class="status status-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-icon" href="<?= base_url('admin/seller-view.php?id=' . $s['id']) ?>">
                  <i class="fa-solid fa-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <h2 class="h6 fw-bold mb-0">Produtos recentes</h2>
        <a class="link-more" href="<?= base_url('admin/products.php') ?>">Ver todos</a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <tbody>
          <?php foreach ($recentProducts as $p): ?>
            <tr>
              <td style="width:60px"><img class="thumb-sm" src="<?= e(img_url($p['main_image'])) ?>" alt=""></td>
              <td>
                <div class="fw-semibold small"><?= e($p['name']) ?></div>
                <small class="text-muted"><?= e($p['store_name']) ?></small>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-icon" target="_blank" href="<?= e(url_product($p['slug'])) ?>">
                  <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>