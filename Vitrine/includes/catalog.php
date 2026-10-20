<?php
require_once __DIR__ . '/functions.php';

const PRODUCT_PHOTO_LIMIT = 5;

function product_sizes(?string $sizes): array
{
    return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $sizes)), fn($size) => $size !== ''), SORT_STRING));
}

function input_text(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

// DECIMAL do MySQL e valores do formulário são convertidos sem aritmética de ponto flutuante.
function price_cents(string $value): ?int
{
    $value = str_replace(',', '.', trim($value));
    if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/D', $value, $match)) return null;
    return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
}

function decimal_price(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function whatsapp_number(?string $phone, bool $allowLegacyBrazil = true): ?string
{
    $phone = trim((string) $phone);
    if (!preg_match('/^\+?[0-9\s().-]+$/D', $phone)) return null;
    $digits = preg_replace('/\D/', '', $phone);
    if ($allowLegacyBrazil && $phone[0] !== '+' && in_array(strlen($digits), [10, 11], true)) $digits = '55' . $digits;
    if (!preg_match('/^[1-9]\d{7,14}$/D', $digits)) return null;
    if (str_starts_with($digits, '55') && !preg_match('/^55[1-9][0-9](?:[2-5][0-9]{7}|9[0-9]{8})$/D', $digits)) return null;
    return $digits;
}

function product_photos(array $product, PDO $pdo): array
{
    $paths = $product['main_image'] ? [$product['main_image']] : [];
    if (!empty($product['id'])) {
        $query = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $query->execute([$product['id']]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $path) {
            if (!in_array($path, $paths, true)) $paths[] = $path;
        }
    }
    return $paths;
}

function product_photo_slots(array $paths): array
{
    $slots = [];
    for ($i = 0; $i < max(PRODUCT_PHOTO_LIMIT, count($paths)); $i++) $slots['slot' . $i] = $paths[$i] ?? null;
    return $slots;
}

/** Valida o plano inteiro antes de mover qualquer arquivo. O mapa vem do banco, nunca do navegador. */
function plan_product_photos(array $slots, array $post, array $files): array
{
    $order = $post['photo_order'] ?? array_keys($slots);
    if (!is_array($order) || count($order) !== count($slots) || count(array_unique($order, SORT_REGULAR)) !== count($slots)
        || array_filter($order, fn($key) => !is_string($key) || !array_key_exists($key, $slots))) {
        throw new InvalidArgumentException('A ordem das fotos é inválida. Atualize a página.');
    }
    $removed = $post['remove_photos'] ?? [];
    if (!is_array($removed) || array_filter($removed, fn($key) => !is_string($key) || !array_key_exists($key, $slots)))
        throw new InvalidArgumentException('Seleção de fotos inválida.');
    $uploads = $files['photos'] ?? [];
    $errors = $uploads['error'] ?? [];
    if (!is_array($errors) || array_diff(array_keys($errors), array_keys($slots)))
        throw new InvalidArgumentException('Envie no máximo cinco fotos por produto.');
    // Rejeita também o formato antigo/manipulado de upload, em vez de ignorar arquivos excedentes.
    foreach ($files as $field => $file) {
        if ($field !== 'photos') throw new InvalidArgumentException('Use os campos de fotos deste formulário.');
    }
    $plan = [];
    foreach ($order as $key) {
        $error = $errors[$key] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error)) throw new InvalidArgumentException('Arquivo de imagem inválido.');
        if ($error !== UPLOAD_ERR_NO_FILE) {
            $file = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) $file[$field] = $uploads[$field][$key] ?? null;
            $plan[] = ['file' => $file];
        } elseif (!in_array($key, $removed, true) && $slots[$key]) {
            $plan[] = ['path' => $slots[$key]];
        }
    }
    if (count($plan) < 1 || count($plan) > PRODUCT_PHOTO_LIMIT)
        throw new InvalidArgumentException('Cada produto precisa ter de 1 a 5 fotos. Remova as fotos excedentes.');
    return $plan;
}

function validate_product(array $post, array $categories): array
{
    $product = [];
    $errors = [];
    foreach (['name'=>180, 'description'=>10000, 'brand'=>120, 'sku'=>60, 'sizes'=>180, 'colors'=>180] as $field => $max) {
        $product[$field] = input_text($post, $field);
        if (mb_strlen($product[$field]) > $max) $errors[] = 'O campo ' . $field . ' excede o tamanho permitido.';
    }
    if (mb_strlen($product['name']) < 3) $errors[] = 'Informe um nome com pelo menos 3 caracteres.';
    $price = price_cents(input_text($post, 'price'));
    $promoText = input_text($post, 'promo_price');
    $promo = $promoText === '' ? null : price_cents($promoText);
    if ($price === null || $price < 1) $errors[] = 'Informe um preço válido, com até duas casas decimais.';
    if ($promoText !== '' && ($promo === null || $promo < 1 || $price === null || $promo >= $price))
        $errors[] = 'O preço promocional deve ser positivo e menor que o preço normal.';
    $product['price'] = $price !== null ? decimal_price($price) : input_text($post, 'price');
    $product['promo_price'] = $promo !== null ? decimal_price($promo) : $promoText;
    $stock = filter_var($post['stock'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>0, 'max_range'=>2147483647]]);
    if ($stock === false) $errors[] = 'Informe um estoque inteiro, maior ou igual a zero.';
    $product['stock'] = $stock === false ? 0 : $stock;
    $categoryIds = array_map('intval', array_column($categories, 'id'));
    foreach (['category_id', 'subcategory_id'] as $field) {
        $value = $post[$field] ?? '';
        $product[$field] = null;
        if ($value === '' && $field === 'subcategory_id') continue;
        $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
        if ($id === false || !in_array($id, $categoryIds, true)) $errors[] = 'Selecione uma categoria válida.';
        else $product[$field] = $id;
    }
    $product['status'] = ($post['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    $product['featured'] = empty($post['featured']) ? 0 : 1;
    return [$product, $errors];
}

function catalog_revision(array $product, array $photos): string
{
    $values = [];
    foreach (['id', 'name', 'description', 'brand', 'price', 'promo_price', 'stock', 'sku', 'sizes', 'colors', 'category_id', 'subcategory_id', 'status', 'featured'] as $field)
        $values[$field] = $product[$field] ?? null;
    return hash('sha256', json_encode([$values, $photos]));
}

function save_catalog_product(PDO $pdo, int $storeId, array $original, array $values, array $post, array $files): int
{
    $uploaded = [];
    $oldPaths = [];
    $paths = [];
    try {
        $pdo->beginTransaction();
        $id = (int) $original['id'];
        if ($id) {
            $query = $pdo->prepare('SELECT * FROM products WHERE id = ? AND store_id = ? FOR UPDATE');
            $query->execute([$id, $storeId]);
            $original = $query->fetch();
            if (!$original) throw new InvalidArgumentException('Produto não encontrado nesta loja.');
        }
        $oldPaths = product_photos($original, $pdo);
        if ($id && !hash_equals(catalog_revision($original, $oldPaths), input_text($post, 'product_revision')))
            throw new InvalidArgumentException('Este produto foi alterado em outra página. Atualize a página antes de editar.');
        $plan = plan_product_photos(product_photo_slots($oldPaths), $post, $files);
        foreach ($plan as $photo) {
            if (isset($photo['file'])) {
                $path = upload_image($photo['file'], 'products');
                if (!$path) throw new InvalidArgumentException('Selecione uma imagem válida.');
                $uploaded[] = $path;
                $paths[] = $path;
            } else $paths[] = $photo['path'];
        }
        $columns = ['category_id', 'subcategory_id', 'name', 'description', 'brand', 'price', 'promo_price', 'stock', 'sku', 'sizes', 'colors', 'status', 'featured'];
        $data = [];
        foreach ($columns as $column) $data[] = $values[$column] === '' ? null : $values[$column];
        $data[] = $paths[0];
        if ($id) {
            $set = implode(', ', array_map(fn($column) => $column . ' = ?', $columns));
            $query = $pdo->prepare('UPDATE products SET ' . $set . ', main_image = ? WHERE id = ? AND store_id = ?');
            $query->execute([...$data, $id, $storeId]);
        } else {
            $slug = unique_slug('products', slugify($values['name']));
            $query = $pdo->prepare('INSERT INTO products (' . implode(', ', $columns) . ', main_image, store_id, slug) VALUES (' . implode(',', array_fill(0, count($data) + 2, '?')) . ')');
            $query->execute([...$data, $storeId, $slug]);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$id]);
        $query = $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)');
        foreach (array_slice($paths, 1) as $index => $path) $query->execute([$id, $path, $index]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($uploaded as $path) delete_upload($path);
        throw $exception;
    }
    foreach (array_diff($oldPaths, $paths) as $path) delete_upload($path);
    return $id;
}
