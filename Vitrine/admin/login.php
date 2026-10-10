<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_admin()) { redirect('admin/dashboard.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (attempt_login($email, $pass)) {
        if (is_admin()) {
            set_flash('success', 'Bem-vindo ao painel administrativo.');
            redirect('admin/dashboard.php');
        } else {
            logout();
            session_start();
            $errors[] = 'Esta conta não é de administrador.';
        }
    } else {
        $errors[] = 'Credenciais inválidas.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login Admin · <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:var(--bg)">
<div class="container" style="max-width:420px">
  <div class="form-card">
    <div class="text-center mb-4">
      <div class="brand justify-content-center mb-2">
        <span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span>
        <span class="brand-text"><?= e(SITE_NAME) ?></span>
      </div>
      <h1 class="h5 fw-bold mb-0">Painel administrativo</h1>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Senha</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-brand w-100 py-2">Entrar</button>
    </form>

    <p class="text-center small text-muted mt-3 mb-0">
      <a href="<?= base_url('index.php') ?>">Voltar ao site</a>
    </p>
  </div>
</div>
</body>
</html>