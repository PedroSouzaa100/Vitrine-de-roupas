<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Vary: Origin');
// O carrinho fica no domínio principal, inclusive para vitrines em subdomínios.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $origin === '' || $origin === APP_URL;
if ($origin && !$allowed && APP_URL && STORE_DOMAIN) {
    $host = strtolower(parse_url($origin, PHP_URL_HOST) ?: '');
    $suffix = '.' . STORE_DOMAIN;
    $slug = str_ends_with($host, $suffix) ? substr($host, 0, -strlen($suffix)) : '';
    $allowed = parse_url($origin, PHP_URL_SCHEME) === parse_url(APP_URL, PHP_URL_SCHEME)
        && $origin === parse_url(APP_URL, PHP_URL_SCHEME) . '://' . $host
        && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $slug)
        && (STORE_SUBDOMAINS === '*' || (is_array(STORE_SUBDOMAINS) && in_array($slug, STORE_SUBDOMAINS, true)));
}
if (!$allowed && !APP_URL) {
    $localOrigin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $allowed = $origin === $localOrigin;
}
if (!$allowed) { http_response_code(403); echo json_encode(['error'=>'Origem não autorizada.']); exit; }
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204); exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST, OPTIONS'); http_response_code(405); echo json_encode(['error'=>'Método inválido.']); exit;
}
try {
    $pdo = db();
    $result = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) throw new CartException('Sessão expirada. Atualize a página.', 419);
        $action = input_text($_POST, 'action');
        $result = $action === 'checkout' ? cart_whatsapp_checkout($pdo, input_text($_POST, 'revision')) : ['cart'=>cart_change($pdo, $action, $_POST)];
    } else $result = ['cart'=>cart_snapshot($pdo)];
    $result['csrf_token'] = csrf_token();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (CartException $exception) {
    http_response_code($exception->status);
    echo json_encode(['error'=>$exception->getMessage(), 'cart'=>cart_snapshot($pdo), 'csrf_token'=>csrf_token()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    error_log('Falha no carrinho: ' . $exception->getMessage());
    http_response_code(500); echo json_encode(['error'=>'Não foi possível atualizar o carrinho. Tente novamente.']);
}
