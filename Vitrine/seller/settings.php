<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) $errors[] = 'Senha atual incorreta.';
    if (strlen($new) < 8)                              $errors[] = 'A nova senha precisa ter 8+ caracteres.';
    if ($new !== $confirm)                             $errors[] = 'A confirmação não confere.';

    if (!$errors) {
        $st = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $st->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        set_flash('success', 'Senha alterada com sucesso.');
        redirect('seller/settings.php');
    }
}
?>
<h1 class="h4 fw-bold mb-4">Configurações</h1>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="form-card">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Alterar senha</h2>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Senha atual</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Nova senha</label>
          <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirmar nova senha</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button class="btn btn-brand">Salvar</button>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="form-card">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Dados da conta</h2>
      <dl class="row mb-0">
        <dt class="col-4 small text-muted">Nome</dt><dd class="col-8"><?= e($user['name']) ?></dd>
        <dt class="col-4 small text-muted">E-mail</dt><dd class="col-8"><?= e($user['email']) ?></dd>
        <dt class="col-4 small text-muted">Telefone</dt><dd class="col-8"><?= e($user['phone'] ?: '—') ?></dd>
        <dt class="col-4 small text-muted">Tipo</dt><dd class="col-8">Vendedor</dd>
      </dl>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>