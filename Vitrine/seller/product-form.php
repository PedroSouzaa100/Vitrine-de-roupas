<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';
$seller = require_seller();
$store = get_seller_store((int) $seller['id']);
if (!$store) { http_response_code(403); exit('Cadastre sua loja antes de adicionar produtos.'); }
$pdo = db();
$storeId = (int) $store['id'];
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
if ($id === false) { http_response_code(400); exit('Identificador de produto inválido.'); }
$categories = get_categories('category');
$errors = [];
$product = ['id'=>0, 'name'=>'', 'description'=>'', 'brand'=>'', 'price'=>'', 'promo_price'=>'', 'stock'=>1,
    'sku'=>'', 'sizes'=>'', 'colors'=>'', 'category_id'=>null, 'subcategory_id'=>null, 'main_image'=>null, 'status'=>'active', 'featured'=>0];
if ($id) {
    $query = $pdo->prepare('SELECT * FROM products WHERE id = ? AND store_id = ?');
    $query->execute([$id, $storeId]);
    $product = $query->fetch();
    if (!$product) { http_response_code(404); exit('Produto não encontrado nesta loja.'); }
}
$photos = product_photos($product, $pdo);
$productRevision = catalog_revision($product, $photos);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    [$values, $errors] = validate_product($_POST, $categories);
    if (!$errors) {
        try {
            save_catalog_product($pdo, $storeId, $product, $values, $_POST, $_FILES);
            set_flash('success', $id ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.');
            redirect('seller/products.php');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            // Erros PDO não devem revelar consultas nem dados internos ao vendedor.
            if ($exception instanceof PDOException) {
                error_log('Falha ao salvar produto: ' . $exception->getMessage());
                $errors[] = 'Não foi possível salvar o produto. Tente novamente.';
            } else $errors[] = $exception->getMessage();
        }
    }
    $product = array_merge($product, $values);
    if ($_FILES) $errors[] = 'Selecione novamente as novas fotos antes de reenviar. As fotos salvas foram preservadas.';
}
$photoSlots = product_photo_slots($photos);
$pageTitle = $id ? 'Editar produto' : 'Novo produto';
require __DIR__ . '/includes/header.php';
$allSizes = ['PP','P','M','G','GG','XG','Único'];
$currentSizes = array_map('trim', explode(',', (string) $product['sizes']));
?>
<h1 class="h4 fw-bold mb-4"><?= $id ? 'Editar produto' : 'Novo produto' ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-4" data-product-form>
  <?= csrf_field() ?>
  <input type="hidden" name="product_revision" value="<?= e($productRevision) ?>">

  <div class="col-lg-8">
    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Informações básicas</h2>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Nome do produto *</label>
          <input type="text" name="name" minlength="3" maxlength="180" class="form-control" value="<?= e($product['name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Marca</label>
          <input type="text" name="brand" maxlength="120" class="form-control" value="<?= e($product['brand']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">SKU / código</label>
          <input type="text" name="sku" maxlength="60" class="form-control" value="<?= e($product['sku']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Descrição</label>
          <textarea name="description" maxlength="10000" rows="5" class="form-control"><?= e($product['description']) ?></textarea>
        </div>
      </div>
    </div>

    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Preço e estoque</h2>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Preço *</label>
          <input type="text" inputmode="decimal" pattern="[0-9]{1,8}([.,][0-9]{1,2})?" name="price" class="form-control" value="<?= e($product['price']) ?>" placeholder="0.00" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Preço promocional</label>
          <input type="text" inputmode="decimal" pattern="[0-9]{1,8}([.,][0-9]{1,2})?" name="promo_price" class="form-control" value="<?= e($product['promo_price']) ?>" placeholder="0.00">
        </div>
        <div class="col-md-4">
          <label class="form-label">Estoque</label><div class="helper">Estoque zero impede adicionar ao carrinho.</div>
          <input type="number" name="stock" max="2147483647" required class="form-control" value="<?= (int) $product['stock'] ?>" min="0">
        </div>
      </div>
    </div>

    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Variações</h2>

      <div class="mb-3">
        <label class="form-label">Tamanhos</label>
        <div class="d-flex flex-wrap gap-2 mb-2">
          <?php foreach ($allSizes as $sz): ?>
            <button type="button" class="pill size-chip <?= in_array($sz, $currentSizes, true) ? 'active' : '' ?>"
                    data-size="<?= e($sz) ?>"><?= e($sz) ?></button>
          <?php endforeach; ?>
        </div>
        <input type="text" id="sizesInput" name="sizes" maxlength="180" class="form-control"
               value="<?= e($product['sizes']) ?>" placeholder="P,M,G ou clique nos chips acima">
      </div>

      <div class="mb-0">
        <label class="form-label">Cores</label>
        <input type="text" name="colors" maxlength="180" class="form-control"
               value="<?= e($product['colors']) ?>" placeholder="Preto, Branco, Azul">
        <div class="helper">Separe por vírgula.</div>
      </div>
    </div>

    <div class="form-card" data-photo-editor>
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Fotos do produto</h2>
      <p class="helper">De 1 a 5 fotos, até 5 MB cada. Formatos: JPG, PNG, WEBP ou GIF. A primeira foto preenchida é a principal. Use as setas para ordenar.</p>
      <p class="small fw-semibold" data-photo-count role="status" aria-live="polite"></p>
      <div class="photo-editor-grid" data-photo-list>
        <?php foreach ($photoSlots as $key => $path): ?>
          <div class="photo-editor-item" data-photo-item data-existing="<?= $path ? '1' : '0' ?>">
            <input type="hidden" name="photo_order[]" value="<?= e($key) ?>">
            <label class="form-label" for="photo-<?= e($key) ?>" data-photo-label>Foto</label>
            <span class="badge text-bg-dark mb-2" data-photo-main hidden>Principal</span>
            <img src="<?= e(img_url($path)) ?>" alt="Prévia da foto" class="photo-editor-preview" data-photo-preview data-original="<?= e(img_url($path)) ?>">
            <input id="photo-<?= e($key) ?>" type="file" name="photos[<?= e($key) ?>]" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control mt-2" data-photo-file>
            <label class="small mt-2"><input type="checkbox" name="remove_photos[]" value="<?= e($key) ?>" data-photo-remove> Remover foto atual</label>
            <div class="d-flex flex-wrap gap-1 mt-2">
              <button type="button" class="btn btn-outline-brand btn-sm" data-photo-first>Tornar principal</button>
              <button type="button" class="btn btn-outline-brand btn-sm" data-photo-up aria-label="Mover foto para antes"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
              <button type="button" class="btn btn-outline-brand btn-sm" data-photo-down aria-label="Mover foto para depois"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <noscript><p class="helper mt-2">As fotos serão salvas na ordem exibida. Ative JavaScript para pré-visualizar e reorganizar.</p></noscript>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Organização</h2>

      <div class="mb-3">
        <label class="form-label">Categoria</label>
        <select name="category_id" class="form-select" required>
          <option value="">Selecione…</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $product['category_id'] == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $product['status'] === 'active' ? 'selected' : '' ?>>Disponível (com estoque)</option>
          <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Indisponível</option>
        </select>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="featured" value="1" id="feat"
               <?= $product['featured'] ? 'checked' : '' ?>>
        <label class="form-check-label small" for="feat">Destacar produto</label>
      </div>
    </div>

    <div class="d-grid gap-2">
      <button class="btn btn-brand py-2" data-save-product><?= $id ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
      <a href="<?= base_url('seller/products.php') ?>" class="btn btn-outline-brand">Cancelar</a>
    </div>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
