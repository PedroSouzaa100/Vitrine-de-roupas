<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : (is_seller() ? 'seller/dashboard.php' : 'index.php'));
}

$pageTitle = 'Entrar';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }
    if ($password === '') {
        $errors[] = 'Informe sua senha.';
    }

    if (!$errors && attempt_login($email, $password)) {
        $user = current_user();
        set_flash('success', 'Bem-vindo(a), ' . $user['name'] . '!');
        $redirect = $_GET['redirect'] ?? '';
        if (is_string($redirect) && str_starts_with($redirect, BASE_URL) && !str_starts_with($redirect, '//') && !str_contains($redirect, '\\') && !preg_match('/[\r\n]/', $redirect)) {
            redirect(substr($redirect, strlen(BASE_URL)));
        }
        redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : ($user['role'] === 'seller' ? 'seller/dashboard.php' : 'index.php'));
    } elseif (!$errors) {
        $errors[] = 'E-mail ou senha incorretos.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="form-card">
        <h1 class="h4 fw-bold mb-1">Entrar</h1>
        <p class="text-muted small mb-4">Acesse o painel da sua loja ou a área administrativa.</p>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Senha</label>
            <input type="password" class="form-control" id="password" name="password" required>
          </div>
          <button class="btn btn-brand w-100 py-2">Entrar</button>
        </form>

        <hr class="my-4">
        <p class="text-center small mb-0">
          Quer vender?
          <a class="fw-semibold" href="<?= base_url('register-seller.php') ?>">Criar minha loja</a>
        </p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
