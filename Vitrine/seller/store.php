<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$errors = [];
$genders = get_categories('gender');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name        = trim($_POST['name'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId  = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $instagram   = trim($_POST['instagram'] ?? '');
    $whatsapp    = trim($_POST['whatsapp'] ?? '');
    $website     = trim($_POST['website'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $state       = strtoupper(substr(trim($_POST['state'] ?? ''), 0, 2));
    $address     = trim($_POST['address'] ?? '');

    if (mb_strlen($name) < 3) $errors[] = 'Nome da loja muito curto.';
    if ($whatsapp === '') $errors[] = 'Informe o WhatsApp.';

    $logoPath = $store['logo'];
    $bannerPath = $store['banner'];

    if (!$errors) {
        try {
            if (!empty($_FILES['logo']['name'])) {
                $new = upload_image($_FILES['logo'], 'stores');
                if ($new) { if ($store['logo']) delete_upload($store['logo']); $logoPath = $new; }
            }
            if (!empty($_FILES['banner']['name'])) {
                $new = upload_image($_FILES['banner'], 'stores');
                if ($new) { if ($store['banner']) delete_upload($store['banner']); $bannerPath = $new; }
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $slug = ($name !== $store['name'])
            ? unique_slug('stores', slugify($name), (int) $store['id'])
            : $store['slug'];

        $st = $pdo->prepare("
            UPDATE stores SET category_id=?, name=?, display_name=?, slug=?, description=?,
                logo=?, banner=?, instagram=?, whatsapp=?, website=?, city=?, state=?, address=?
            WHERE id=?
        ");
        $st->execute([
            $categoryId, $name, $displayName ?: $name, $slug, $description ?: null,
            $logoPath, $bannerPath, $instagram ?: null, $whatsapp ?: null, $website ?: null,
            $city ?: null, $state ?: null, $address ?: null,
            (int) $store['id']
        ]);
        set_flash('success', 'Loja atualizada com sucesso.');
        redirect('seller/store.php');
    }
}

// Recarrega dados atualizados
$st = $pdo->prepare("SELECT * FROM stores WHERE id=?");
$st->execute([(int) $store['id']]);
$store = $st->fetch();
?>
<h1 class="h4 fw-bold mb-4">Minha loja</h1>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-4">
  <?= csrf_field() ?>

  <div class="col-lg-8">
    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Dados da loja</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nome da loja *</label>
          <input type="text" name="name" class="form-control" value="<?= e($store['name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Nome fantasia</label>
          <input type="text" name="display_name" class="form-control" value="<?= e($store['display_name']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Descrição</label>
          <textarea name="description" rows="4" class="form-control"><?= e($store['description']) ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label">Público principal</label>
          <select name="category_id" class="form-select">
            <option value="">—</option>
            <?php foreach ($genders as $g): ?>
              <option value="<?= (int) $g['id'] ?>" <?= $store['category_id'] == $g['id'] ? 'selected' : '' ?>>
                <?= e($g['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">WhatsApp *</label>
          <input type="text" name="whatsapp" class="form-control" value="<?= e($store['whatsapp']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Instagram</label>
          <input type="text" name="instagram" class="form-control" value="<?= e($store['instagram']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Site</label>
          <input type="text" name="website" class="form-control" value="<?= e($store['website']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Cidade</label>
          <input type="text" name="city" class="form-control" value="<?= e($store['city']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <input type="text" name="state" maxlength="2" class="form-control" value="<?= e($store['state']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Endereço</label>
          <input type="text" name="address" class="form-control" value="<?= e($store['address']) ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Imagens da loja</h2>

      <div class="mb-3">
        <label class="form-label">Logo</label>
        <img src="<?= e(img_url($store['logo'])) ?>" alt="" id="logoPreview"
             style="width:110px;height:110px;border-radius:18px;object-fit:cover" class="mb-2">
        <input type="file" name="logo" accept="image/*" class="form-control" data-preview="#logoPreview">
      </div>

      <div class="mb-0">
        <label class="form-label">Banner</label>
        <img src="<?= e(img_url($store['banner'])) ?>" alt="" id="bannerPreview"
             style="width:100%;border-radius:12px;aspect-ratio:16/6;object-fit:cover" class="mb-2">
        <input type="file" name="banner" accept="image/*" class="form-control" data-preview="#bannerPreview">
      </div>
    </div>

    <button class="btn btn-brand w-100 py-2">Salvar alterações</button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>