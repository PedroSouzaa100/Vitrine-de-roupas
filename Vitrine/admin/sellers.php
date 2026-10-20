<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where = " WHERE 1=1 ";
$params = [];

if ($q !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR s.name LIKE ?) ";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($status, ['pending','approved','rejected','suspended'], true)) {
    $where .= " AND se.status = ? ";
    $params[] = $status;
}

$sqlFrom = " FROM sellers se
             JOIN users u ON u.id = se.user_id
             LEFT JOIN stores s ON s.seller_id = se.id
             $where ";

$st = $pdo->prepare("SELECT COUNT(*) " . $sqlFrom);
$st->execute($params);
$total = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT se.*, u.name AS owner_name, u.email, s.name AS store_name, s.slug AS store_slug "
    . $sqlFrom . " ORDER BY se.created_at DESC LIMIT " . (($page - 1) * $perPage) . ", $perPage");
$st->execute($params);
$sellers = $st->fetchAll();
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap">
    <input type="search" name="q" class="form-control" placeholder="Buscar…" value="<?= e($q) ?>" style="min-width:220px">
    <select name="status" class="form-select" style="min-width:160px">
      <option value="">Todos</option>
      <option value="pending"   <?= $status === 'pending' ? 'selected' : '' ?>>Pendentes</option>
      <option value="approved"  <?= $status === 'approved' ? 'selected' : '' ?>>Aprovados</option>
      <option value="rejected"  <?= $status === 'rejected' ? 'selected' : '' ?>>Rejeitados</option>
      <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspensos</option>
    </select>
    <button class="btn btn-outline-brand">Filtrar</button>
  </form>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>Loja</th><th>Responsável</th><th>Status</th><th>Cadastro</th><th class="text-end">Ações</th></tr>
      </thead>
      <tbody>
      <?php foreach ($sellers as $s): ?>
        <tr>
          <td class="fw-semibold"><?= e($s['store_name'] ?? '—') ?></td>
          <td>
            <div><?= e($s['owner_name']) ?></div>
            <small class="text-muted"><?= e($s['email']) ?></small>
          </td>
          <td><span class="status status-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></td>
          <td class="text-muted small"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-icon" href="<?= base_url('admin/seller-view.php?id=' . $s['id']) ?>" title="Ver">
              <i class="fa-solid fa-eye"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sellers): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum vendedor encontrado.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3"><?= paginate_links($total, $perPage, $page) ?></div>

<?php require __DIR__ . '/includes/footer.php'; ?>
