<?php
require_once __DIR__ . '/includes/auth.php';

$user = current_user();
$type = $_GET['type'] ?? 'product';      // product | store
$id   = (int) ($_GET['id'] ?? 0);
$pdo  = db();

// Validar alvo
if ($type === 'product') {
    $st = $pdo->prepare("SELECT id, name FROM products WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $target = $st->fetch();
    $label  = $target ? 'Produto: ' . $target['name'] : null;
} else {
    $st = $pdo->prepare("SELECT id, name FROM stores WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $target = $st->fetch();
    $label  = $target ? 'Loja: ' . $target['name'] : null;
    $type = 'store';
}

if (!$target) {
    set_flash('danger', 'Item não encontrado.');
    redirect('index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $reason = trim($_POST['reason'] ?? '');
    $desc   = trim($_POST['description'] ?? '');

    if ($reason === '') $errors[] = 'Selecione um motivo.';

    if (!$errors) {
        $st = $pdo->prepare("INSERT INTO reports (reporter_id, store_id, product_id, reason, description)
                             VALUES (?,?,?,?,?)");
        $st->execute([
            $user['id'] ?? null,
            $type === 'store'   ? $target['id'] : null,
            $type === 'product' ? $target['id'] : null,
            $reason,
            $desc ?: null,
        ]);
        set_flash('success', 'Denúncia enviada. Obrigado por ajudar a manter a plataforma segura.');
        redirect('index.php');
    }
}

$pageTitle = 'Denunciar';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="form-card">
        <h1 class="h5 fw-bold mb-1">Denunciar conteúdo</h1>
        <p class="text-muted small mb-4"><?= e($label) ?></p>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Motivo *</label>
            <select name="reason" class="form-select" required>
              <option value="">Selecione…</option>
              <option>Produto ou loja falsa</option>
              <option>Conteúdo inadequado</option>
              <option>Preço enganoso</option>
              <option>Spam ou golpe</option>
              <option>Direitos autorais</option>
              <option>Outro</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Descrição (opcional)</label>
            <textarea name="description" rows="4" class="form-control"></textarea>
          </div>
          <div class="d-flex gap-2">
            <a href="javascript:history.back()" class="btn btn-outline-brand">Cancelar</a>
            <button class="btn btn-brand flex-fill">Enviar denúncia</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>