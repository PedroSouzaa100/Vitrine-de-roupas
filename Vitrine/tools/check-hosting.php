<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
$failed = false;
function check_hosting(string $label, bool $ok): void {
    global $failed; echo ($ok ? '[OK] ' : '[FALHA] ') . $label . PHP_EOL; if (!$ok) $failed = true;
}
check_hosting('PHP 8.2 ou superior', version_compare(PHP_VERSION, '8.2', '>='));
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'iconv', 'session'] as $extension)
    check_hosting('Extensao ' . $extension, extension_loaded($extension));
foreach (['uploads/products', 'uploads/stores', 'logs'] as $folder)
    check_hosting('Escrita em ' . $folder, is_dir(APP_ROOT . '/' . $folder) && is_writable(APP_ROOT . '/' . $folder));
check_hosting('Uploads habilitados', (bool) ini_get('file_uploads'));
function ini_bytes(string $value): int {
    $suffix = strtolower(substr(trim($value), -1));
    return (int) ((float) $value * (['k'=>1024, 'm'=>1048576, 'g'=>1073741824][$suffix] ?? 1));
}
check_hosting('upload_max_filesize >= 5 MB', ini_bytes(ini_get('upload_max_filesize')) >= 5 * 1048576);
check_hosting('post_max_size >= 64 MB', ini_bytes(ini_get('post_max_size')) >= 64 * 1048576);
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    check_hosting('Conexao com banco', true);
    foreach (['users', 'sellers', 'stores', 'categories', 'products', 'product_images', 'favorites', 'store_favorites', 'reports', 'admin_logs'] as $table) {
        $pdo->query("SELECT 1 FROM `$table` LIMIT 1"); check_hosting('Tabela ' . $table, true);
    }
} catch (PDOException $exception) {
    check_hosting('Banco e estrutura: confira credenciais/importacao (detalhes omitidos)', false);
}
echo 'CLI e servidor web podem carregar limites PHP diferentes; confira tambem o hPanel.' . PHP_EOL;
exit($failed ? 1 : 0);
