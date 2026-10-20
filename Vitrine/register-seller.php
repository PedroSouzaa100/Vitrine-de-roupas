<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(is_seller() ? 'seller/dashboard.php' : 'index.php');
}

require_once __DIR__ . '/includes/catalog.php';
$pageTitle = 'Criar minha loja';
$errors = [];
$genders = get_categories('gender');
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    rate_limit('register_seller', 8, 900);

    $fields = ['name','email','phone','document','store_name','display_name','description',
               'category_id','instagram','whatsapp','website','city','state','address'];
    foreach ($fields as $f) {
        $old[$f] = input_text($_POST, $f);
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $old['email'] = strtolower($old['email']);

    // Validação
    if (mb_strlen($old['name']) < 3)                        $errors[] = 'Informe seu nome completo.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))  $errors[] = 'Informe um e-mail válido.';
    if (strlen($password) < 8)                              $errors[] = 'A senha deve ter ao menos 8 caracteres.';
    if ($password !== $confirm)                             $errors[] = 'As senhas não coincidem.';
    if (mb_strlen($old['store_name']) < 3)                  $errors[] = 'Informe o nome da loja.';
    $phone = whatsapp_number($old['whatsapp'], false);
    if (!$phone) $errors[] = 'Informe WhatsApp com código do país e DDD, como +55 11 99999-9999.';
    else $old['whatsapp'] = $phone;

    if (!$errors) {
        $st = db()->prepare("SELECT 1 FROM users WHERE email = ?");
        $st->execute([$old['email']]);
        if ($st->fetchColumn()) {
            $errors[] = 'Este e-mail já está cadastrado.';
        }
    }

    if ($old['website'] !== '' && (!filter_var($old['website'], FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($old['website'], PHP_URL_SCHEME) ?: ''), ['http', 'https'], true))) $errors[] = 'Informe um site com http:// ou https://.';

    // Uploads
    $logoPath = $bannerPath = null;
    if (!$errors) {
        try {
            $logoPath   = upload_image($_FILES['logo'] ?? [], 'stores');
            $bannerPath = upload_image($_FILES['banner'] ?? [], 'stores');
        } catch (RuntimeException $ex) {
            delete_upload($logoPath);
            delete_upload($bannerPath);
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("INSERT INTO users (name,email,phone,password,role) VALUES (?,?,?,?,'seller')");
            $st->execute([$old['name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();

            $st = $pdo->prepare("INSERT INTO sellers (user_id, document, status) VALUES (?,?,'pending')");
            $st->execute([$userId, $old['document'] ?: null]);
            $sellerId = (int) $pdo->lastInsertId();

            $slug = unique_slug('stores', slugify($old['store_name']));

            $st = $pdo->prepare("
                INSERT INTO stores
                  (seller_id, category_id, name, display_name, slug, description, logo, banner,
                   instagram, whatsapp, website, city, state, address)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $st->execute([
                $sellerId,
                $old['category_id'] !== '' ? (int) $old['category_id'] : null,
                $old['store_name'],
                $old['display_name'] ?: $old['store_name'],
                $slug,
                $old['description'] ?: null,
                $logoPath,
                $bannerPath,
                $old['instagram'] ?: null,
                $old['whatsapp'] ?: null,
                $old['website'] ?: null,
                $old['city'] ?: null,
                $old['state'] ? strtoupper(substr($old['state'], 0, 2)) : null,
                $old['address'] ?: null,
            ]);

            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['role']    = 'seller';

            set_flash('success', 'Sua solicitação foi enviada e está aguardando aprovação do administrador.');
            redirect('seller/dashboard.php');
        } catch (Throwable $t) {
            $pdo->rollBack();
            delete_upload($logoPath);
            delete_upload($bannerPath);
            $errors[] = 'Não foi possível concluir o cadastro. Tente novamente.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="text-center mb-4">
    <h1 class="h3 fw-bold">Criar minha loja</h1>
    <p class="text-muted mb-0">Preencha os dados e aguarde a aprovação do administrador.</p>
  </div>

  <?php if ($errors): ?>
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="alert alert-danger">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="row justify-content-center g-4">
    <?= csrf_field() ?>
    <div class="col-lg-9">
      <div class="form-card mb-4">
        <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Dados pessoais</h2>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Nome completo *</label>
            <input type="text" name="name" class="form-control" value="<?= e($old['name'] ?? '') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">CPF/CNPJ</label>
            <input type="text" name="document" class="form-control" value="<?= e($old['document'] ?? '') ?>">
          </div>
          <div class="col-md-7">
            <label class="form-label">E-mail *</label>
            <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
          </div>
          <div class="col-md-5">
            <label class="form-label">Telefone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($old['phone'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Senha *</label>
            <input type="password" name="password" class="form-control" required>
            <div class="helper">Mínimo 8 caracteres.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirmar senha *</label>
            <input type="password" name="password_confirm" class="form-control" required>
          </div>
        </div>
      </div>

      <div class="form-card mb-4">
        <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Dados da loja</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome da loja *</label>
            <input type="text" name="store_name" class="form-control" value="<?= e($old['store_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nome fantasia</label>
            <input type="text" name="display_name" class="form-control" value="<?= e($old['display_name'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Descrição</label>
            <textarea name="description" rows="3" class="form-control"><?= e($old['description'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Público principal</label>
            <select name="category_id" class="form-select">
              <option value="">Selecione…</option>
              <?php foreach ($genders as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= (($old['category_id'] ?? '') == $g['id']) ? 'selected' : '' ?>>
                  <?= e($g['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">WhatsApp *</label>
            <input type="tel" name="whatsapp" required minlength="8" maxlength="25" pattern="\+?[0-9\s.\(\)\-]{8,25}" class="form-control" value="<?= e($old['whatsapp'] ?? '') ?>" placeholder="+55 11 99999-9999"><div class="helper">Inclua país e DDD. Cadastre um número com WhatsApp ativo.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Instagram</label>
            <input type="text" name="instagram" class="form-control" value="<?= e($old['instagram'] ?? '') ?>" placeholder="@minhaloja">
          </div>
          <div class="col-md-6">
            <label class="form-label">Site</label>
            <input type="text" name="website" class="form-control" value="<?= e($old['website'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Cidade</label>
            <input type="text" name="city" class="form-control" value="<?= e($old['city'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Estado</label>
            <input type="text" name="state" maxlength="2" class="form-control" value="<?= e($old['state'] ?? '') ?>" placeholder="SP">
          </div>
          <div class="col-12">
            <label class="form-label">Endereço (opcional)</label>
            <input type="text" name="address" class="form-control" value="<?= e($old['address'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Logo</label>
            <input type="file" name="logo" accept="image/*" class="form-control">
            <div class="helper">JPG, PNG ou WEBP, até <?= MAX_UPLOAD_MB ?>MB.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Banner / capa</label>
            <input type="file" name="banner" accept="image/*" class="form-control">
          </div>
        </div>
      </div>

      <button class="btn btn-brand w-100 py-3">
        <i class="fa-solid fa-paper-plane me-2"></i> Enviar solicitação
      </button>
      <p class="helper text-center mt-3 mb-0">
        Após o envio, sua loja ficará com status <strong>Pendente</strong> até a análise do administrador.
      </p>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
