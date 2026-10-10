<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['open','reviewed','dismissed'], true) ? $_POST['status'] : 'open';
    $pdo->prepare("UPDATE reports SET status=? WHERE id=?")->execute([$status, $id]);
    log_admin('update_report', 'report', $id, $status);
    set_flash('success', 'Denúncia atualizada.');
    redirect('admin/reports.php');
}

$st = $pdo->query("
    SELECT r.*, u.name AS reporter_name,
           p.name AS product_name, p.slug AS product_slug,
           s.name AS store_name, s.slug AS store_slug
    FROM reports r
    LEFT JOIN users u ON u.id = r.reporter_id
    LEFT JOIN products p ON p.id = r.product_id
    LEFT JOIN stores s ON s.id = r.store_id
    ORDER BY r.created_at DESC
    LIMIT 100
");
$reports = $st->fetchAll();
?>
<div class="table-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>Alvo</th><th>Motivo</th><th>Denunciante</th><th>Status</th><th class="text-end">Ações</th></tr>
      </thead>
      <tbody>
      <?php foreach ($reports as $r): ?>
        <tr>
          <td>
            <?php if ($r['product_slug']): ?>
              <div class="small text-muted">Produto</div>
              <a href="<?= e(url_product($r['product_slug'])) ?>" target="_blank"><?= e($r['product_name']) ?></a>
            <?php elseif ($r['store_slug']): ?>
              <div class="small text-muted">Loja</div>
              <a href="<?= e(url_store($r['store_slug'])) ?>" target="_blank"><?= e($r['store_name']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <div class="fw-semibold small"><?= e($r['reason']) ?></div>
            <?php if ($r['description']): ?>
              <small class="text-muted"><?= e(excerpt($r['description'], 100)) ?></small>
            <?php endif; ?>
          </td>
          <td class="small"><?= e($r['reporter_name'] ?: 'Visitante') ?></td>
          <td>
            <span class="status status-<?= $r['status'] === 'open' ? 'pending' : ($r['status'] === 'reviewed' ? 'approved' : 'suspended') ?>">
              <?= e(ucfirst($r['status'])) ?>
            </span>
          </td>
          <td class="text-end">
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()">
                <option value="open"      <?= $r['status'] === 'open' ? 'selected' : '' ?>>Aberta</option>
                <option value="reviewed"  <?= $r['status'] === 'reviewed' ? 'selected' : '' ?>>Revisada</option>
                <option value="dismissed" <?= $r['status'] === 'dismissed' ? 'selected' : '' ?>>Ignorada</option>
              </select>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reports): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma denúncia registrada.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>