<?php
/**
 * VITRINE - Configuração principal.
 * XAMPP local: credenciais abaixo funcionam.
 * Hostinger: troque DB_NAME, DB_USER, DB_PASS.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'vitrine');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
        || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:');
define('DEBUG', $isLocal);

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log');
}

define('APP_ROOT', dirname(__DIR__));

$docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$appRoot = str_replace('\\', '/', APP_ROOT);
$base    = ($docRoot && strpos($appRoot, $docRoot) === 0)
    ? substr($appRoot, strlen($docRoot))
    : '';

define('BASE_URL', rtrim($base, '/') . '/');

define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('UPLOAD_URL',  BASE_URL . 'uploads/');

define('PRETTY_URLS', false);

define('SITE_NAME',    'Vitrine');
define('SITE_TAGLINE', 'Descubra lojas e produtos de moda perto de você');

define('MAX_UPLOAD_MB', 5);

if (!headers_sent()) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}