<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';
$seller = require_seller();
$store = get_seller_store((int) $seller['id']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); exit('Use o botão Excluir na lista de produtos.'); }
csrf_verify();
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$store || !$id) { http_response_code(404); exit('Produto não encontrado.'); }
$pdo = db();
try {
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT * FROM products WHERE id = ? AND store_id = ? FOR UPDATE');
    $query->execute([$id, $store['id']]);
    $product = $query->fetch();
    if (!$product) { $pdo->rollBack(); http_response_code(404); exit('Produto não encontrado nesta loja.'); }
    $photos = product_photos($product, $pdo);
    $pdo->prepare('DELETE FROM products WHERE id = ? AND store_id = ?')->execute([$id, $store['id']]);
    $pdo->commit();
    foreach ($photos as $path) delete_upload($path);
    set_flash('success', 'Produto excluído.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Falha ao excluir produto: ' . $exception->getMessage());
    set_flash('danger', 'Não foi possível excluir o produto. Tente novamente.');
}
redirect('seller/products.php');
