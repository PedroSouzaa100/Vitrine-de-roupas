<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = 'Criar conta';
$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = strtolower(trim($_POST['email'] ?? ''));
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (mb_strlen($old['name']) < 3)                              $errors[] = 'Informe seu nome completo.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))        $errors[] = 'Informe um e-mail válido.';
    if (strlen($password) < 8)                                    $errors[] = 'A senha deve ter ao menos 8 caracteres.';
    if ($password !== $confirm)                                   $errors[] = 'As senhas não coincidem.';

    if (!$errors) {
        $st = db()->prepare("SELECT 1 FROM users WHERE email = ?");
        $st->execute([$old['email']]);
        if ($st->fetchColumn()) {
            $errors[] = 'Este e-mail já está cadastrado.';
        }
    }

    if (!$errors) {
        $st = db()->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?,'customer')");
        $st->execute([$old['name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        $_SESSION['role'] = 'customer';
        session_regenerate_id(true);
        set_flash('success', 'Conta criada com sucesso! Bem-vindo(a).');
        redirect('index.php');
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="form-card">
        <h1 class="h4 fw-bold mb-1">Criar conta de cliente</h1>
        <p class="text-muted small mb-4">Favorite produtos e lojas, e fale direto com os vendedores.</p>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Nome completo</label>
            <input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required>
          </div>
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Telefone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>" placeholder="(00) 00000-0000">
            </div>
          </div>
          <div class="row g-3 mt-0">
            <div class="col-md-6">
              <label class="form-label">Senha</label>
              <input type="password" name="password" class="form-control" required>
              <div class="helper">Mínimo de 8 caracteres.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirmar senha</label>
              <input type="password" name="password_confirm" class="form-control" required>
            </div>
          </div>
          <button class="btn btn-brand w-100 py-2 mt-4">Criar conta</button>
        </form>

        <p class="text-center small mt-4 mb-0">
          Já tem conta? <a class="fw-semibold" href="<?= base_url('login.php') ?>">Entrar</a>
        </p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
