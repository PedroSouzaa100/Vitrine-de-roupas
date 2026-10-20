<?php
/** Configuracao compartilhada: ambiente > config/local.php > padroes locais. */
define('APP_ROOT', dirname(__DIR__));
$localConfig = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
if (!is_array($localConfig)) throw new RuntimeException('Configuracao local invalida.');
$isLocal = PHP_SAPI === 'cli' || (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)
    && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/D', $_SERVER['HTTP_HOST'] ?? ''));
// Configuração opcional exclusiva do XAMPP. O domínio público usa local.php.
if ($isLocal && is_file(__DIR__ . '/development.php')) {
    $developmentConfig = require __DIR__ . '/development.php';
    if (!is_array($developmentConfig)) throw new RuntimeException('Configuracao de desenvolvimento invalida.');
    $localConfig = array_replace($localConfig, $developmentConfig);
}
function app_config(string $key, $default = null) {
    global $localConfig;
    $value = getenv($key);
    return $value !== false ? $value : ($localConfig[$key] ?? $default);
}
define('APP_ENV', app_config('APP_ENV', $isLocal ? 'development' : 'production'));
define('DEBUG', APP_ENV === 'development' && $isLocal);
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(APP_ROOT . '/logs')) ini_set('error_log', APP_ROOT . '/logs/php-errors.log');
date_default_timezone_set('America/Sao_Paulo');
define('DB_HOST', app_config('DB_HOST', 'localhost'));
define('DB_NAME', app_config('DB_NAME', DEBUG ? 'vitrine' : ''));
define('DB_USER', app_config('DB_USER', DEBUG ? 'root' : ''));
define('DB_PASS', app_config('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');
define('APP_URL', rtrim(app_config('APP_URL', ''), '/'));
if (APP_URL && (!filter_var(APP_URL, FILTER_VALIDATE_URL) || !in_array(parse_url(APP_URL, PHP_URL_SCHEME), ['http', 'https'], true)
    || parse_url(APP_URL, PHP_URL_USER) || parse_url(APP_URL, PHP_URL_QUERY) || parse_url(APP_URL, PHP_URL_FRAGMENT)
    || !in_array(parse_url(APP_URL, PHP_URL_PATH), [null, '', '/'], true))) {
    http_response_code(503); exit('Configure APP_URL com a origem do site, sem subpasta.');
}
if (APP_ENV === 'production') {
    $missingConfig = [];
    if (!APP_URL || parse_url(APP_URL, PHP_URL_SCHEME) !== 'https') $missingConfig[] = 'APP_URL (domínio com https://)';
    foreach (['DB_NAME'=>DB_NAME, 'DB_USER'=>DB_USER, 'DB_PASS'=>DB_PASS] as $key=>$value) {
        if ($value === '' || $value === false || preg_match('/^PREENCHA_/i', (string) $value)) $missingConfig[] = $key;
    }
    if (DB_USER === 'root') $missingConfig[] = 'DB_USER (use o usuário MySQL da hospedagem)';
    if ($missingConfig) {
        http_response_code(503);
        if (!is_file(__DIR__ . '/local.php')) {
            exit('O arquivo config/local.php não foi encontrado. Envie sua configuração de produção para public_html/config/local.php.');
        }
        exit('Complete config/local.php. Configurações pendentes: ' . implode(', ', $missingConfig) . '.');
    }
}
$docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
$appRoot = str_replace('\\', '/', APP_ROOT);
$detectedBase = $docRoot && str_starts_with($appRoot, $docRoot . '/') ? substr($appRoot, strlen($docRoot)) : '';
$base = app_config('APP_BASE_PATH', $detectedBase);
define('BASE_URL', '/' . (trim($base, '/') !== '' ? trim($base, '/') . '/' : ''));
define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('UPLOAD_URL', BASE_URL . 'uploads/');
define('PRETTY_URLS', false);
define('STORE_DOMAIN', strtolower(app_config('STORE_DOMAIN', '')));
define('STORE_SUBDOMAINS', app_config('STORE_SUBDOMAINS', []));
define('INSTALL_TOKEN', (string) app_config('INSTALL_TOKEN', ''));
define('SITE_NAME', 'Vitrine');
define('SITE_TAGLINE', 'Descubra lojas e produtos de moda perto de voce');
define('MAX_UPLOAD_MB', 5);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
if (APP_ENV === 'production' && PHP_SAPI !== 'cli') {
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    $mainHost = strtolower(parse_url(APP_URL, PHP_URL_HOST) ?: '');
    $isStoreHost = STORE_DOMAIN && str_ends_with($host, '.' . STORE_DOMAIN)
        && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', substr($host, 0, -strlen('.' . STORE_DOMAIN)));
    if ($host !== $mainHost && $host !== 'www.' . $mainHost && !$isStoreHost) {
        http_response_code(400); exit('Dominio invalido.');
    }
    if (!$secure) {
        $targetHost = $isStoreHost ? $host : $mainHost;
        $uri = $_SERVER['REQUEST_URI'] ?? BASE_URL;
        if (!str_starts_with($uri, '/') || preg_match('/[\r\n]/', $uri)) $uri = BASE_URL;
        header('Location: https://' . $targetHost . $uri, true, 308); exit;
    }
    header('Strict-Transport-Security: max-age=31536000');
}
if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => BASE_URL, 'domain' => '',
        'secure' => $secure || APP_ENV === 'production', 'httponly' => true, 'samesite' => 'Lax']);
}
