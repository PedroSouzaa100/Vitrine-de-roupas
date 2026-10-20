<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Falha na conexao MySQL: SQLSTATE=' . $e->getCode() . '; codigo=' . ($e->errorInfo[1] ?? 'indisponivel'));
            http_response_code(500);
            if (DEBUG) {
                die('Erro de conexão: ' . $e->getMessage());
            }
            die('Não foi possível conectar ao banco de dados. Verifique as credenciais em config/local.php');
        }
    }

    return $pdo;
}
