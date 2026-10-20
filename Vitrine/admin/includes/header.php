<?php
require_once __DIR__ . '/../../includes/auth.php';

require_admin();

$user = current_user();
$pageTitle = $pageTitle ?? 'Admin';
$current = basename($_SERVER['PHP_SELF']);
$flashes = get_flashes();

$pdo = db();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();
$openReports  = (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status='open'")->fetchColumn();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · Admin <?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body data-base="<?= base_url() ?>">

<a href="#main" class="sr-only">Pular para o conteúdo</a>
<div class="dash-wrap">
  <aside class="dash-side" id="dashSide" aria-label="Navegação do painel" tabindex="-1">
    <a class="brand brand-light" href="<?= base_url('admin/dashboard.php') ?>">
      <span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span>
      <span class="brand-text"><?= e(SITE_NAME) ?> · Admin</span>
    </a>

    <div class="mb-3 px-2">
      <div class="small text-muted-soft">Logado como</div>
      <div class="fw-bold text-white"><?= e($user['name']) ?></div>
    </div>

    <ul class="dash-nav">
      <li><a href="<?= base_url('admin/dashboard.php') ?>" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="<?= base_url('admin/sellers.php') ?>" class="<?= $current === 'sellers.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-user-tie"></i> Vendedores</a></li>
      <li><a href="<?= base_url('admin/sellers.php?status=pending') ?>" class="<?= $current === 'seller-view.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-hourglass"></i> Pendentes
        <?php if ($pendingCount): ?><span class="badge text-bg-warning ms-auto"><?= $pendingCount ?></span><?php endif; ?>
      </a></li>
      <li><a href="<?= base_url('admin/products.php') ?>" class="<?= $current === 'products.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-shirt"></i> Produtos</a></li>
      <li><a href="<?= base_url('admin/customers.php') ?>" class="<?= $current === 'customers.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i> Clientes</a></li>
      <li><a href="<?= base_url('admin/categories.php') ?>" class="<?= $current === 'categories.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-tags"></i> Categorias</a></li>
      <li><a href="<?= base_url('admin/reports.php') ?>" class="<?= $current === 'reports.php' ? 'active' : '' ?>">
        <i class="fa-regular fa-flag"></i> Denúncias
        <?php if ($openReports): ?><span class="badge text-bg-danger ms-auto"><?= $openReports ?></span><?php endif; ?>
      </a></li>
      <li><a href="<?= base_url('admin/logs.php') ?>" class="<?= $current === 'logs.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-clock-rotate-left"></i> Logs</a></li>
      <li><a href="<?= base_url('admin/logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
    </ul>
  </aside>

  <main class="dash-main" id="main">
    <div class="dash-top">
      <h1 class="h4 fw-bold mb-0"><?= e($pageTitle) ?></h1>
    </div>

    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['msg']) ?>
        <button class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
      </div>
    <?php endforeach; ?>
