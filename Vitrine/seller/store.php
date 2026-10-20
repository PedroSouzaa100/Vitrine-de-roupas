<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';
$seller = require_seller();
$store = get_seller_store((int) $seller['id']);
if (!$store) { http_response_code(404); exit('Loja não encontrada.'); }
$pdo = db();
$errors = [];
$genders = get_categories('gender');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name        = input_text($_POST, 'name');
    $displayName = input_text($_POST, 'display_name');
    $description = input_text($_POST, 'description');
    $categoryId  = null;
    $categoryInput = input_text($_POST, 'category_id');
    if ($categoryInput !== '') {
        $categoryId = filter_var($categoryInput, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($categoryId === false || !in_array($categoryId, array_map('intval', array_column($genders, 'id')), true)) $errors[] = 'Selecione uma categoria de loja válida.';
    }
    $instagram   = input_text($_POST, 'instagram');
    $whatsapp    = input_text($_POST, 'whatsapp');
    $website     = input_text($_POST, 'website');
    $city        = input_text($_POST, 'city');
    $state       = strtoupper(substr(input_text($_POST, 'state'), 0, 2));
    $address     = input_text($_POST, 'address');

    if (mb_strlen($name) < 3) $errors[] = 'Nome da loja muito curto.';
    foreach (['name'=>[$name,140], 'display_name'=>[$displayName,140], 'description'=>[$description,10000], 'instagram'=>[$instagram,120], 'website'=>[$website,180], 'city'=>[$city,90], 'address'=>[$address,200]] as $field=>[$value,$limit]) {
        if (mb_strlen($value) > $limit) $errors[] = 'O campo ' . $field . ' excede o tamanho permitido.';
    }
    $phone = whatsapp_number($whatsapp, false);
    if (!$phone) $errors[] = 'Informe o WhatsApp com código do país e DDD, por exemplo +55 11 99999-9999.';
    else $whatsapp = $phone;

    if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($website, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true))) $errors[] = 'Informe um site com http:// ou https://.';

    $logoPath = $store['logo'];
    $bannerPath = $store['banner'];

    if (!$errors) {
        try {
            if (!empty($_FILES['logo']['name'])) {
                $new = upload_image($_FILES['logo'], 'stores');
                if ($new) { $logoPath = $new; }
            }
            if (!empty($_FILES['banner']['name'])) {
                $new = upload_image($_FILES['banner'], 'stores');
                if ($new) { $bannerPath = $new; }
            }
        } catch (RuntimeException $ex) {
            if ($logoPath !== $store['logo']) delete_upload($logoPath);
            if ($bannerPath !== $store['banner']) delete_upload($bannerPath);
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        try {
        $slug = $store['slug'];

        $st = $pdo->prepare("
            UPDATE stores SET category_id=?, name=?, display_name=?, slug=?, description=?,
                logo=?, banner=?, instagram=?, whatsapp=?, website=?, city=?, state=?, address=?
            WHERE id=? AND seller_id=?
        ");
        $st->execute([
            $categoryId, $name, $displayName ?: $name, $slug, $description ?: null,
            $logoPath, $bannerPath, $instagram ?: null, $whatsapp ?: null, $website ?: null,
            $city ?: null, $state ?: null, $address ?: null,
            (int) $store['id'], (int) $seller['id']
        ]);
        if ($logoPath !== $store['logo']) delete_upload($store['logo']);
        if ($bannerPath !== $store['banner']) delete_upload($store['banner']);
        set_flash('success', 'Loja atualizada com sucesso.');
        redirect('seller/store.php');
        } catch (PDOException $exception) {
            if ($logoPath !== $store['logo']) delete_upload($logoPath);
            if ($bannerPath !== $store['banner']) delete_upload($bannerPath);
            error_log('Falha ao atualizar loja: ' . $exception->getMessage());
            $errors[] = 'Não foi possível atualizar a loja. Tente novamente.';
        }
    }
}

// Recarrega dados atualizados
$st = $pdo->prepare("SELECT * FROM stores WHERE id=?");
$st->execute([(int) $store['id']]);
$store = $st->fetch();
require __DIR__ . '/includes/header.php';
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
          <input type="tel" name="whatsapp" class="form-control" required minlength="8" maxlength="25" pattern="\+?[0-9\s.\(\)\-]{8,25}" value="<?= e($store['whatsapp']) ?>" placeholder="+55 11 99999-9999"><div class="helper">Inclua código do país e DDD. Use um número com WhatsApp ativo; não verificamos a ativação.</div>
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
