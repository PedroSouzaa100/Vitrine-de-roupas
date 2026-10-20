<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$mode = $argv[1] ?? '';
$name = getenv('DB_NAME');
if (!preg_match('/^vitrine_test_catalog_[a-f0-9]{12}$/D', $name)) exit(1);
$root = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ($mode === 'setup') {
    $root->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $root->exec("USE `$name`");
    $root->exec(file_get_contents(__DIR__ . '/../database.hostinger.sql'));
    $password = bin2hex(random_bytes(12));
    $data = ['password'=>$password, 'stores'=>[], 'sellers'=>[]];
    $root->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin')")->execute(['Admin de teste','admin@catalog.test',password_hash($password,PASSWORD_DEFAULT)]);
    foreach (['alpha', 'beta'] as $key) {
        $q = $root->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'seller')");
        $q->execute(['Teste ' . $key, $key . '@catalog.test', password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) $root->lastInsertId();
        $root->prepare("INSERT INTO sellers (user_id,status) VALUES (?,'approved')")->execute([$userId]);
        $sellerId = (int) $root->lastInsertId();
        $root->prepare('INSERT INTO stores (seller_id,name,slug,whatsapp) VALUES (?,?,?,?)')->execute([$sellerId,'Loja ' . $key,$key,'5511999999999']);
        $data['stores'][$key] = (int) $root->lastInsertId(); $data['sellers'][$key]=$userId;
    }
    echo json_encode($data);
} elseif ($mode === 'query') {
    $root->exec("USE `$name`");
    $input = json_decode(stream_get_contents(STDIN), true);
    $q = $root->prepare($input['sql']); $q->execute($input['params'] ?? []);
    echo json_encode($q->columnCount() ? $q->fetchAll(PDO::FETCH_ASSOC) : ['affected'=>$q->rowCount()]);
} elseif ($mode === 'cleanup') {
    $root->exec("USE `$name`");
    $paths = $root->query("SELECT main_image FROM products WHERE main_image IS NOT NULL UNION SELECT image_path FROM product_images")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($paths as $path) {
        $file = realpath(__DIR__ . '/../uploads/' . $path);
        $base = realpath(__DIR__ . '/../uploads');
        if ($file && str_starts_with(str_replace('\\','/',$file), str_replace('\\','/',$base) . '/')) unlink($file);
    }
    $root->exec("DROP DATABASE `$name`");
    echo 'Banco temporario removido.';
}
