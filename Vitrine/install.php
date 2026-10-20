<?php
require_once __DIR__ . '/includes/auth.php';

$isLocalInstall = DEBUG && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
if (!$isLocalInstall && strlen(INSTALL_TOKEN) < 32) {
    http_response_code(404); exit('Not found.');
}
header('Cache-Control: no-store');
if (!$isLocalInstall && ($_SESSION['installer_authorized'] ?? '') !== hash('sha256', INSTALL_TOKEN)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        rate_limit('installer', 5, 900);
        $token = $_POST['install_token'] ?? '';
        if (is_string($token) && hash_equals(INSTALL_TOKEN, $token)) {
            session_regenerate_id(true);
            $_SESSION['installer_authorized'] = hash('sha256', INSTALL_TOKEN);
            redirect('install.php');
        }
        http_response_code(403);
    }
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Instalacao</title><h1>Autorizar instalacao</h1><form method="post">'
        . csrf_field() . '<label>Chave de instalacao <input type="password" name="install_token" required autocomplete="off"></label><button>Continuar</button></form></html>';
    exit;
}

$pdo = db();
$st = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'");
$hasAdmin = $st->fetchColumn() > 0;
if ($hasAdmin && !$isLocalInstall) { http_response_code(404); exit('Not found.'); }

$done = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasAdmin) {
    csrf_verify();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $conf  = $_POST['password_confirm'] ?? '';

    if (mb_strlen($name) < 3)                          $errors[] = 'Nome muito curto.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'E-mail inválido.';
    if (strlen($pass) < 8)                             $errors[] = 'Senha deve ter 8+ caracteres.';
    if ($pass !== $conf)                               $errors[] = 'Senhas não conferem.';

    if (!$errors) {
        $chk = $pdo->prepare("SELECT 1 FROM users WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            $errors[] = 'Este e-mail já está cadastrado.';
        }
    }

    if (!$errors) {
        $st = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin')");
        $st->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        unset($_SESSION['installer_authorized']);
        $done = true;
    }
}

$pageTitle = 'Instalação';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="form-card">
        <?php if ($hasAdmin): ?>
          <div class="alert alert-warning">
            Já existe um administrador cadastrado. Apague o arquivo <code>install.php</code> e faça login em
            <a href="<?= base_url('admin/login.php') ?>">admin/login.php</a>.
          </div>
        <?php elseif ($done): ?>
          <div class="alert alert-success">
            <strong>Administrador criado com sucesso!</strong><br>
            <strong>Agora APAGUE o arquivo <code>install.php</code>.</strong>
            <a class="btn btn-brand mt-3" href="<?= base_url('admin/login.php') ?>">Ir para o login</a>
          </div>
        <?php else: ?>
          <h1 class="h4 fw-bold mb-1">Configurar administrador</h1>
          <p class="text-muted small mb-4">Crie a conta de administrador.</p>
          <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?></div>
          <?php endif; ?>
          <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label" for="nm">Nome</label>
              <input type="text" id="nm" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="em">E-mail</label>
              <input type="email" id="em" name="email" class="form-control" required>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="pw">Senha</label>
                <input type="password" id="pw" name="password" class="form-control" minlength="8" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="pw2">Confirmar senha</label>
                <input type="password" id="pw2" name="password_confirm" class="form-control" minlength="8" required>
              </div>
            </div>
            <button class="btn btn-brand w-100 mt-4">Criar administrador</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
