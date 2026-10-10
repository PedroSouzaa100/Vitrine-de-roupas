<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

// Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    $allowed = ['approve','reject','suspend','reactivate','delete'];
    if (in_array($action, $allowed, true)) {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM sellers WHERE id=?)")->execute([$id]);
            log_admin('delete_seller', 'seller', $id);
            set_flash('success', 'Vendedor excluído.');
            redirect('admin/sellers.php');
        }

        $newStatus = match($action) {
            'approve'    => 'approved',
            'reject'     => 'rejected',
            'suspend'    => 'suspended',
            'reactivate' => 'approved',
            default      => null,
        };

        $pdo->prepare("UPDATE sellers SET status=?, rejection_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$newStatus, $action === 'reject' ? $reason : null, current_user()['id'], $id]);

        log_admin('update_seller_status:' . $newStatus, 'seller', $id, $reason);

        set_flash('success', 'Status atualizado para ' . $newStatus . '.');
        redirect('admin/seller-view.php?id=' . $id);
    }
}

$st = $pdo->prepare("
    SELECT se.*, u.name AS owner_name, u.email, u.phone, u.created_at AS user_created,
           s.*
    FROM sellers se
    JOIN users u ON u.id = se.user_id
    LEFT JOIN stores s ON s.seller_id = se.id
    WHERE se.id = ?
");
$st->execute([$id]);
$row = $st->fetch();

if (!$row) { set_flash('danger', 'Vendedor não encontrado.'); redirect('admin/sellers.php'); }

// Produtos do vendedor
$st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id=?");
$st->execute([$row['id'] ?? 0]);
$productCount = (int) $st->fetchColumn();
?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="form-card mb-4">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <h2 class="h5 fw-bold mb-1"><?= e($row['name']) ?></h2>
          <div class="text-muted small"><?= e($row['owner_name']) ?> · <?= e($row['email']) ?></div>
        </div>
        <span class="status status-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span>
      </div>

      <dl class="row mb-0">
        <dt class="col-sm-3 small text-muted">Telefone</dt><dd class="col-sm-9"><?= e($row['phone'] ?: '—') ?></dd>
        <dt class="col-sm-3 small text-muted">CPF/CNPJ</dt><dd class="col-sm-9"><?= e($row['document'] ?: '—') ?></dd>
        <dt class="col-sm-3 small text-muted">Fantasia</dt><dd class="col-sm-9"><?= e($row['display_name'] ?: '—') ?></dd>
        <dt class="col-sm-3 small text-muted">Descrição</dt><dd class="col-sm-9"><?= nl2br(e($row['description'] ?: '—')) ?></dd>
        <dt class="col-sm-3 small text-muted">Localização</dt>
        <dd class="col-sm-9"><?= e($row['city'] ?: '—') ?><?= $row['state'] ? '/' . e($row['state']) : '' ?></dd>
        <dt class="col-sm-3 small text-muted">WhatsApp</dt><dd class="col-sm-9"><?= e($row['whatsapp'] ?: '—') ?></dd>
        <dt class="col-sm-3 small text-muted">Instagram</dt><dd class="col-sm-9"><?= e($row['instagram'] ?: '—') ?></dd>
        <dt class="col-sm-3 small text-muted">Site</dt><dd class="col-sm-9"><?= e($row['website'] ?: '—') ?></dd>
        <dt class="col-sm-3 small text-muted">Cadastro</dt>
        <dd class="col-sm-9"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></dd>
      </dl>

      <?php if ($row['rejection_reason']): ?>
        <div class="alert alert-danger mt-3 mb-0">
          <strong>Motivo da rejeição:</strong> <?= e($row['rejection_reason']) ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="row g-3">
      <?php if ($row['logo']): ?>
        <div class="col-md-4">
          <div class="form-card p-3 text-center">
            <div class="small text-muted mb-2">Logo</div>
            <img src="<?= e(img_url($row['logo'])) ?>" alt="" style="max-width:100%;border-radius:12px">
          </div>
        </div>
      <?php endif; ?>
      <?php if ($row['banner']): ?>
        <div class="col-md-8">
          <div class="form-card p-3">
            <div class="small text-muted mb-2">Banner</div>
            <img src="<?= e(img_url($row['banner'])) ?>" alt="" style="width:100%;border-radius:12px">
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="form-card mb-3">
      <h3 class="h6 fw-bold mb-3">Ações</h3>

      <?php if ($row['status'] !== 'approved'): ?>
        <form method="post" class="mb-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="approve">
          <button class="btn btn-brand w-100" data-confirm="Aprovar esta loja?">
            <i class="fa-solid fa-circle-check me-2"></i> Aprovar
          </button>
        </form>
      <?php endif; ?>

      <?php if ($row['status'] !== 'rejected'): ?>
        <button class="btn btn-outline-danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
          <i class="fa-solid fa-circle-xmark me-2"></i> Rejeitar
        </button>
      <?php endif; ?>

      <?php if ($row['status'] === 'approved'): ?>
        <form method="post" class="mb-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="suspend">
          <button class="btn btn-outline-warning w-100" data-confirm="Suspender esta loja?">
            <i class="fa-solid fa-ban me-2"></i> Suspender
          </button>
        </form>
      <?php elseif ($row['status'] === 'suspended'): ?>
        <form method="post" class="mb-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reactivate">
          <button class="btn btn-outline-brand w-100">
            <i class="fa-solid fa-rotate-right me-2"></i> Reativar
          </button>
        </form>
      <?php endif; ?>

      <form method="post" onsubmit="return confirm('Excluir definitivamente?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="btn btn-outline-danger w-100 mt-2">
          <i class="fa-regular fa-trash-can me-2"></i> Excluir
        </button>
      </form>
    </div>

    <div class="form-card">
      <h3 class="h6 fw-bold mb-3">Resumo</h3>
      <dl class="row mb-0">
        <dt class="col-7 small text-muted">Produtos</dt>
        <dd class="col-5 text-end fw-bold"><?= $productCount ?></dd>
        <dt class="col-7 small text-muted">Views da loja</dt>
        <dd class="col-5 text-end fw-bold"><?= (int) ($row['views'] ?? 0) ?></dd>
      </dl>
      <?php if (!empty($row['slug'])): ?>
        <a class="btn btn-outline-brand w-100 mt-3" target="_blank" href="<?= e(url_store($row['slug'])) ?>">
          <i class="fa-solid fa-arrow-up-right-from-square me-2"></i> Ver loja pública
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal de rejeição -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reject">
      <div class="modal-header">
        <h5 class="modal-title">Rejeitar loja</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Motivo da rejeição *</label>
        <textarea name="reason" rows="4" class="form-control" required></textarea>
        <div class="helper mt-2">Este motivo será exibido ao vendedor.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-brand">Rejeitar</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>