<?php
require_once __DIR__ . '/includes/header.php'; // já carrega auth e define $seller, $store

$pdo = db();
$storeId = $store ? (int) $store['id'] : 0;

// Estatísticas
$stats = ['total'=>0,'active'=>0,'out'=>0,'views'=>0,'favs'=>0];

if ($storeId) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id = ?");
    $st->execute([$storeId]); $stats['total'] = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id = ? AND status='active'");
    $st->execute([$storeId]); $stats['active'] = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id = ? AND stock <= 0");
    $st->execute([$storeId]); $stats['out'] = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(views),0) FROM products WHERE store_id = ?");
    $st->execute([$storeId]); $stats['views'] = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM favorites f
                         JOIN products p ON p.id = f.product_id
                         WHERE p.store_id = ?");
    $st->execute([$storeId]); $stats['favs'] = (int) $st->fetchColumn();
}

// Produtos mais vistos
$topProducts = [];
if ($storeId) {
    $st = $pdo->prepare("SELECT id,name,slug,views,main_image FROM products WHERE store_id = ? ORDER BY views DESC LIMIT 5");
    $st->execute([$storeId]);
    $topProducts = $st->fetchAll();
}
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-shirt"></i></div>
      <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Produtos</div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div><div class="stat-value"><?= $stats['active'] ?></div><div class="stat-label">Ativos</div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-box-open"></i></div>
      <div><div class="stat-value"><?= $stats['out'] ?></div><div class="stat-label">Sem estoque</div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-eye"></i></div>
      <div><div class="stat-value"><?= $stats['views'] ?></div><div class="stat-label">Visualizações</div></div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <h2 class="h6 fw-bold mb-0">Produtos mais visualizados</h2>
        <a class="link-more" href="<?= base_url('seller/products.php') ?>">Ver todos</a>
      </div>
      <?php if (!$topProducts): ?>
        <div class="p-4 text-center text-muted small">Sem dados ainda. Cadastre seu primeiro produto.</div>
      <?php else: ?>
        <table class="table">
          <tbody>
          <?php foreach ($topProducts as $p): ?>
            <tr>
              <td style="width:60px">
                <img class="thumb-sm" src="<?= e(img_url($p['main_image'])) ?>" alt="">
              </td>
              <td><a href="<?= e(url_product($p['slug'])) ?>" target="_blank"><?= e($p['name']) ?></a></td>
              <td class="text-end"><strong><?= (int) $p['views'] ?></strong> <small class="text-muted">views</small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="form-card">
      <h2 class="h6 fw-bold mb-3">Ações rápidas</h2>
      <div class="d-grid gap-2">
        <a class="btn btn-brand" href="<?= base_url('seller/product-form.php') ?>">
          <i class="fa-solid fa-plus me-2"></i> Adicionar produto
        </a>
        <a class="btn btn-outline-brand" href="<?= base_url('seller/store.php') ?>">
          <i class="fa-solid fa-store me-2"></i> Editar dados da loja
        </a>
        <a class="btn btn-outline-brand" href="<?= base_url('seller/settings.php') ?>">
          <i class="fa-solid fa-key me-2"></i> Alterar senha
        </a>
      </div>

      <hr class="my-4">
      <h3 class="h6 fw-bold mb-2">Favoritos recebidos</h3>
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon"><i class="fa-regular fa-heart"></i></div>
        <div>
          <div class="stat-value"><?= $stats['favs'] ?></div>
          <div class="stat-label">produtos favoritados</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
