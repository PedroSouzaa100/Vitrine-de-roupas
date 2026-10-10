<?php
require_once __DIR__ . '/includes/header.php';

$st = db()->query("
    SELECT l.*, u.name AS admin_name
    FROM admin_logs l
    LEFT JOIN users u ON u.id = l.admin_id
    ORDER BY l.created_at DESC
    LIMIT 200
");
$logs = $st->fetchAll();
?>
<div class="table-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Data</th><th>Admin</th><th>Ação</th><th>Alvo</th><th>Detalhes</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="small text-muted text-nowrap"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
          <td class="small"><?= e($l['admin_name'] ?: '—') ?></td>
          <td><span class="badge text-bg-light"><?= e($l['action']) ?></span></td>
          <td class="small"><?= e($l['target_type'] ?: '—') ?><?= $l['target_id'] ? ' #' . (int) $l['target_id'] : '' ?></td>
          <td class="small text-muted"><?= e($l['details'] ?: '—') ?></td>
          <td class="small text-muted"><?= e($l['ip_address'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma atividade registrada.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>