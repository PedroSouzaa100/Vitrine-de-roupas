<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
if (APP_ENV !== 'development' || !in_array(DB_HOST, ['localhost', '127.0.0.1'], true)) exit('Exportacao permitida somente do banco local.');
$pdo = db();
$dir = APP_ROOT . '/dist';
if (!is_dir($dir)) mkdir($dir, 0700, true);
$path = $dir . '/vitrine-banco-atual.sql';
$handle = fopen($path . '.tmp', 'wb');
if (!$handle) exit('Nao foi possivel criar o arquivo SQL.');
$identifier = fn(string $value) => '`' . str_replace('`', '``', $value) . '`';
try {
    $pdo->beginTransaction();
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $priority = ['users','sellers','categories','stores','products','product_images','favorites','store_favorites','product_views','reports','admin_logs','password_resets'];
    $tables = [...array_values(array_intersect($priority, $tables)), ...array_values(array_diff($tables, $priority))];
    fwrite($handle, "-- Vitrine: exportacao completa do banco local.\n-- Importar SOMENTE em banco vazio, selecionado no phpMyAdmin.\n-- Contem dados privados e hashes de senha; nao publicar na pasta publica.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n");
    $count = 0;
    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE ' . $identifier($table))->fetch(PDO::FETCH_NUM)[1];
        fwrite($handle, $create . ";\n");
        $query = $pdo->query('SELECT * FROM ' . $identifier($table));
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $columns = implode(',', array_map($identifier, array_keys($row)));
            $values = implode(',', array_map(fn($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row)));
            fwrite($handle, 'INSERT INTO ' . $identifier($table) . ' (' . $columns . ') VALUES (' . $values . ");\n");
            $count++;
        }
    }
    fwrite($handle, "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n");
    $pdo->commit();
    fflush($handle); fclose($handle); $handle = null;
    if (!rename($path . '.tmp', $path)) throw new RuntimeException('Nao foi possivel finalizar o SQL.');
    echo 'SQL exportado: ' . count($tables) . ' tabelas, ' . $count . ' registros. Credenciais do servidor nao foram exportadas.' . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (is_resource($handle)) fclose($handle);
    echo 'Exportacao nao concluida; confira o banco local.'; exit(1);
}
