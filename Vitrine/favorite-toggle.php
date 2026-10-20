<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function favorite_response(array $data, int $status = 200): void {
    http_response_code($status); echo json_encode($data); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); favorite_response(['error' => 'Metodo invalido.'], 405);
}
$user = current_user();
if (!$user) favorite_response(['error' => 'Entre na sua conta para favoritar.'], 401);
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token))
    favorite_response(['error' => 'Sessao expirada. Atualize a pagina.'], 419);
$type = $_POST['type'] ?? '';
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id || !in_array($type, ['product', 'store'], true)) favorite_response(['error' => 'Favorito invalido.'], 422);
$pdo = db();
if ($type === 'product') {
    $query = $pdo->prepare('SELECT p.id ' . products_public_from() . " AND p.id = ?");
    $table = 'favorites'; $column = 'product_id';
} else {
    $query = $pdo->prepare("SELECT s.id FROM stores s JOIN sellers se ON se.id = s.seller_id WHERE s.id = ? AND s.active = 1 AND se.status = 'approved'");
    $table = 'store_favorites'; $column = 'store_id';
}
$query->execute([$id]);
if (!$query->fetchColumn()) favorite_response(['error' => 'Item indisponivel.'], 404);
$delete = $pdo->prepare("DELETE FROM $table WHERE user_id = ? AND $column = ?");
$delete->execute([$user['id'], $id]);
$active = $delete->rowCount() === 0;
if ($active) {
    $insert = $pdo->prepare("INSERT IGNORE INTO $table (user_id, $column) VALUES (?, ?)");
    $insert->execute([$user['id'], $id]);
}
favorite_response(['active' => $active]);
