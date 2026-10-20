<?php
require_once __DIR__ . '/../../includes/auth.php';
if (ob_get_level() === 0) ob_start();

$seller = require_seller();
$store  = get_seller_store((int) $seller['id']);
$user   = current_user();

$pageTitle = $pageTitle ?? 'Painel do vendedor';
$current   = basename($_SERVER['PHP_SELF']);
$flashes   = get_flashes();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
</head>
<body data-base="<?= base_url() ?>">

<a href="#main" class="sr-only">Pular para o conteúdo</a>
<div class="dash-wrap">
  <aside class="dash-side" id="dashSide" aria-label="Navegação do painel" tabindex="-1">
    <a class="brand brand-light" href="<?= base_url('index.php') ?>">
      <span class="brand-mark"><i class="fa-solid fa-shirt"></i></span>
      <span class="brand-text"><?= e(SITE_NAME) ?></span>
    </a>

    <div class="mb-3 px-2">
      <div class="small text-muted-soft">Vendedor</div>
      <div class="fw-bold text-white"><?= e($user['name']) ?></div>
      <?php if ($store): ?>
        <div class="small text-muted-soft mt-1"><i class="fa-solid fa-store"></i> <?= e($store['name']) ?></div>
      <?php endif; ?>
    </div>

    <ul class="dash-nav">
      <li><a href="<?= base_url('seller/dashboard.php') ?>" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="<?= base_url('seller/store.php') ?>" class="<?= $current === 'store.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-store"></i> Minha loja</a></li>
      <li><a href="<?= base_url('seller/products.php') ?>" class="<?= in_array($current, ['products.php','product-form.php']) ? 'active' : '' ?>">
        <i class="fa-solid fa-shirt"></i> Produtos</a></li>
      <li><a href="<?= base_url('seller/product-form.php') ?>">
        <i class="fa-solid fa-plus"></i> Adicionar produto</a></li>
      <li><a href="<?= base_url('seller/settings.php') ?>" class="<?= $current === 'settings.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-gear"></i> Configurações</a></li>
      <li><a href="<?= base_url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
    </ul>
  </aside>

  <main class="dash-main" id="main">
    <div class="dash-top">
      <div>
        <h1 class="h4 fw-bold mb-0"><?= e($pageTitle) ?></h1>
        <?php if ($store): ?>
          <small class="text-muted">
            Status:
            <?php
              $map = [
                'pending'   => ['Pendente',  'status-pending'],
                'approved'  => ['Aprovada',  'status-approved'],
                'rejected'  => ['Rejeitada', 'status-rejected'],
                'suspended' => ['Suspensa',  'status-suspended'],
              ];
              [$label, $cls] = $map[$seller['status']] ?? ['—', ''];
            ?>
            <span class="status <?= $cls ?>"><?= $label ?></span>
          </small>
        <?php endif; ?>
      </div>
      <?php if ($store): ?>
        <a href="<?= e(url_store($store['slug'])) ?>" target="_blank" class="btn btn-outline-brand">
          <i class="fa-solid fa-eye"></i> Ver loja pública
        </a>
      <?php endif; ?>
    </div>

    <?php if ($seller['status'] === 'pending'): ?>
      <div class="alert alert-warning">
        <i class="fa-solid fa-clock"></i>
        Sua loja está <strong>aguardando aprovação</strong>. Você pode cadastrar produtos, mas eles só ficarão visíveis após a aprovação.
      </div>
    <?php elseif ($seller['status'] === 'rejected'): ?>
      <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Sua loja foi <strong>rejeitada</strong>.
        <?php if (!empty($seller['rejection_reason'])): ?>
          <br><strong>Motivo:</strong> <?= e($seller['rejection_reason']) ?>
        <?php endif; ?>
      </div>
    <?php elseif ($seller['status'] === 'suspended'): ?>
      <div class="alert alert-secondary">
        <i class="fa-solid fa-ban"></i> Sua loja está <strong>suspensa</strong>.
      </div>
    <?php endif; ?>

    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['msg']) ?>
        <button class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
      </div>
    <?php endforeach; ?>
