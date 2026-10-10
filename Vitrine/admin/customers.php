<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where  = " WHERE role='customer' ";
$params = [];
if ($q !== '') {
    $where .= " AND (name LIKE ? OR email LIKE ?) ";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$st = $pdo->prepare("SELECT COUNT(*) FROM users " . $where);
$st->execute($params);
$total = (int) $st->fetchColumn();

$st = $pdo->prepare("SELECT * FROM users " . $where . " ORDER BY created_at DESC LIMIT "
    . (($page - 1) * $perPage) . ", $perPage");
$st->execute($params);
$customers = $st->fetchAll();
?>
<form method="get" class="d-flex gap-2 flex-wrap mb-3">
  <input type="search" name="q" class="form-control" placeholder="Nome ou e-mail…" value="<?= e($q) ?>" style="min-width:260px">
  <button class="btn btn-outline-brand">Buscar</button>
</form>

<div class="table-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Cadastro</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td class="fw-semibold"><?= e($c['name']) ?></td>
          <td><?= e($c['email']) ?></td>
          <td><?= e($c['phone'] ?: '—') ?></td>
          <td class="text-muted small"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">Nenhum cliente.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3"><?= paginate_links($total, $perPage, $page) ?></div>

<?php require __DIR__ . '/includes/footer.php'; ?>