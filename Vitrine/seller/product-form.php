<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$storeId = (int) $store['id'];
$id = (int) ($_GET['id'] ?? 0);

$categories = get_categories('category');
$errors = [];

$product = [
    'id' => 0, 'name' => '', 'description' => '', 'brand' => '',
    'price' => '', 'promo_price' => '', 'stock' => 0, 'sku' => '',
    'sizes' => '', 'colors' => '', 'category_id' => '', 'subcategory_id' => '',
    'main_image' => null, 'status' => 'active', 'featured' => 0
];

if ($id) {
    $st = $pdo->prepare("SELECT * FROM products WHERE id=? AND store_id=?");
    $st->execute([$id, $storeId]);
    $found = $st->fetch();
    if (!$found) { set_flash('danger', 'Produto não encontrado.'); redirect('seller/products.php'); }
    $product = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $product['name']           = trim($_POST['name'] ?? '');
    $product['description']    = trim($_POST['description'] ?? '');
    $product['brand']          = trim($_POST['brand'] ?? '');
    $product['price']          = str_replace(',', '.', $_POST['price'] ?? '0');
    $product['promo_price']    = str_replace(',', '.', $_POST['promo_price'] ?? '');
    $product['stock']          = (int) ($_POST['stock'] ?? 0);
    $product['sku']            = trim($_POST['sku'] ?? '');
    $product['sizes']          = trim($_POST['sizes'] ?? '');
    $product['colors']         = trim($_POST['colors'] ?? '');
    $product['category_id']    = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $product['subcategory_id'] = $_POST['subcategory_id'] !== '' ? (int) $_POST['subcategory_id'] : null;
    $product['status']         = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
    $product['featured']       = !empty($_POST['featured']) ? 1 : 0;

    if (mb_strlen($product['name']) < 3) $errors[] = 'Informe um nome com pelo menos 3 caracteres.';
    if (!is_numeric($product['price']) || $product['price'] <= 0) $errors[] = 'Informe um preço válido.';
    if ($product['promo_price'] !== '' && (!is_numeric($product['promo_price']) || $product['promo_price'] <= 0))
        $errors[] = 'Preço promocional inválido.';
    if ($product['promo_price'] !== '' && $product['promo_price'] >= $product['price'])
        $errors[] = 'O preço promocional deve ser menor que o preço normal.';

    // Upload de imagens
    $mainImagePath = $product['main_image'];
    $newGallery = [];

    if (!$errors) {
        try {
            if (!empty($_FILES['main_image']['name'])) {
                $new = upload_image($_FILES['main_image'], 'products');
                if ($new) {
                    if ($product['main_image']) delete_upload($product['main_image']);
                    $mainImagePath = $new;
                }
            }
            if (!empty($_FILES['gallery']['name'][0])) {
                foreach ($_FILES['gallery']['name'] as $i => $n) {
                    if (!$n) continue;
                    $file = [
                        'name'     => $n,
                        'type'     => $_FILES['gallery']['type'][$i],
                        'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                        'error'    => $_FILES['gallery']['error'][$i],
                        'size'     => $_FILES['gallery']['size'][$i],
                    ];
                    $path = upload_image($file, 'products');
                    if ($path) $newGallery[] = $path;
                }
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $slugBase = slugify($product['name']);
        $slug = unique_slug('products', $slugBase, $id ?: null);

        if ($id) {
            $st = $pdo->prepare("
                UPDATE products SET name=?, slug=?, description=?, brand=?, price=?, promo_price=?,
                    stock=?, sku=?, sizes=?, colors=?, category_id=?, subcategory_id=?,
                    main_image=?, status=?, featured=?
                WHERE id=? AND store_id=?
            ");
            $st->execute([
                $product['name'], $slug, $product['description'] ?: null, $product['brand'] ?: null,
                $product['price'], $product['promo_price'] !== '' ? $product['promo_price'] : null,
                $product['stock'], $product['sku'] ?: null, $product['sizes'] ?: null, $product['colors'] ?: null,
                $product['category_id'], $product['subcategory_id'],
                $mainImagePath, $product['status'], $product['featured'],
                $id, $storeId
            ]);
            $productId = $id;
            set_flash('success', 'Produto atualizado com sucesso.');
        } else {
            $st = $pdo->prepare("
                INSERT INTO products
                  (store_id, category_id, subcategory_id, name, slug, description, brand,
                   price, promo_price, stock, sku, sizes, colors, main_image, status, featured)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $st->execute([
                $storeId, $product['category_id'], $product['subcategory_id'],
                $product['name'], $slug, $product['description'] ?: null, $product['brand'] ?: null,
                $product['price'], $product['promo_price'] !== '' ? $product['promo_price'] : null,
                $product['stock'], $product['sku'] ?: null, $product['sizes'] ?: null, $product['colors'] ?: null,
                $mainImagePath, $product['status'], $product['featured']
            ]);
            $productId = (int) $pdo->lastInsertId();
            set_flash('success', 'Produto cadastrado com sucesso.');
        }

        if ($newGallery) {
            $st = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?,?)");
            foreach ($newGallery as $path) $st->execute([$productId, $path]);
        }

        redirect('seller/products.php');
    }
}

// Imagens atuais
$galleryImages = [];
if ($id) {
    $st = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order, id");
    $st->execute([$id]);
    $galleryImages = $st->fetchAll();
}

$allSizes = ['PP','P','M','G','GG','XG','Único'];
$currentSizes = array_map('trim', explode(',', (string) $product['sizes']));
?>
<h1 class="h4 fw-bold mb-4"><?= $id ? 'Editar produto' : 'Novo produto' ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-4">
  <?= csrf_field() ?>

  <div class="col-lg-8">
    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Informações básicas</h2>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Nome do produto *</label>
          <input type="text" name="name" class="form-control" value="<?= e($product['name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Marca</label>
          <input type="text" name="brand" class="form-control" value="<?= e($product['brand']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">SKU / código</label>
          <input type="text" name="sku" class="form-control" value="<?= e($product['sku']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Descrição</label>
          <textarea name="description" rows="5" class="form-control"><?= e($product['description']) ?></textarea>
        </div>
      </div>
    </div>

    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Preço e estoque</h2>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Preço *</label>
          <input type="text" name="price" class="form-control" value="<?= e($product['price']) ?>" placeholder="0.00" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Preço promocional</label>
          <input type="text" name="promo_price" class="form-control" value="<?= e($product['promo_price']) ?>" placeholder="0.00">
        </div>
        <div class="col-md-4">
          <label class="form-label">Estoque</label>
          <input type="number" name="stock" class="form-control" value="<?= (int) $product['stock'] ?>" min="0">
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
        <input type="text" id="sizesInput" name="sizes" class="form-control"
               value="<?= e($product['sizes']) ?>" placeholder="P,M,G ou clique nos chips acima">
      </div>

      <div class="mb-0">
        <label class="form-label">Cores</label>
        <input type="text" name="colors" class="form-control"
               value="<?= e($product['colors']) ?>" placeholder="Preto, Branco, Azul">
        <div class="helper">Separe por vírgula.</div>
      </div>
    </div>

    <div class="form-card">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Imagens</h2>

      <div class="row g-3 align-items-start">
        <div class="col-md-4">
          <label class="form-label">Imagem principal</label>
          <img id="mainPreview" src="<?= e(img_url($product['main_image'])) ?>" alt=""
               class="thumb-sm mb-2" style="width:100%;height:auto;aspect-ratio:3/4;object-fit:cover">
          <input type="file" name="main_image" accept="image/*" class="form-control" data-preview="#mainPreview">
        </div>

        <div class="col-md-8">
          <label class="form-label">Galeria adicional</label>
          <input type="file" name="gallery[]" accept="image/*" class="form-control" multiple>
          <div class="helper mb-3">Você pode enviar várias imagens de uma vez.</div>

          <?php if ($galleryImages): ?>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($galleryImages as $img): ?>
                <img src="<?= e(img_url($img['image_path'])) ?>" alt="" class="thumb-sm">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="form-card mb-4">
      <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Organização</h2>

      <div class="mb-3">
        <label class="form-label">Categoria</label>
        <select name="category_id" class="form-select">
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
          <option value="active"   <?= $product['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
          <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
        </select>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="featured" value="1" id="feat"
               <?= $product['featured'] ? 'checked' : '' ?>>
        <label class="form-check-label small" for="feat">Destacar produto</label>
      </div>
    </div>

    <div class="d-grid gap-2">
      <button class="btn btn-brand py-2"><?= $id ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
      <a href="<?= base_url('seller/products.php') ?>" class="btn btn-outline-brand">Cancelar</a>
    </div>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>