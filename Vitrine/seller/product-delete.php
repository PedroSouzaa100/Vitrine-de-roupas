<?php
require_once __DIR__ . '/../../includes/auth.php';
$seller = require_seller();
$store  = get_seller_store((int) $seller['id']);

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare("SELECT * FROM products WHERE id=? AND store_id=?");
$st->execute([$id, (int) $store['id']]);
$product = $st->fetch();

if (!$product) { set_flash('danger', 'Produto não encontrado.'); redirect('seller/products.php'); }

// Apagar imagens
delete_upload($product['main_image']);
$st = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id=?");
$st->execute([$id]);
foreach ($st->fetchAll() as $img) delete_upload($img['image_path']);

$pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);

set_flash('success', 'Produto excluído.');
redirect('seller/products.php');