<?php
require_once __DIR__ . '/auth.php';

$__user = current_user();
$pageTitle = $pageTitle ?? SITE_NAME;
$metaDescription = $metaDescription ?? SITE_TAGLINE;
$navCategories = get_categories('category');
$navGenders = get_categories('gender');
$flashes = get_flashes();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body data-base="<?= base_url() ?>">

<a href="#main" class="sr-only">Pular para o conteúdo</a>

<header class="site-header sticky-top">
  <nav class="navbar navbar-expand-lg" aria-label="Menu principal">
    <div class="container">

      <!-- Logo -->
      <a class="navbar-brand brand" href="<?= base_url('index.php') ?>">
        <span class="brand-mark"><i class="fa-solid fa-shirt"></i></span>
        <span class="brand-text"><?= e(SITE_NAME) ?></span>
      </a>

      <!-- Botão Início + Busca (fora do menu hamburguer) -->
      <div class="search-block">
        <a class="btn btn-outline-brand btn-home" href="<?= base_url('index.php') ?>" aria-label="Voltar para a página inicial">
          <i class="fa-solid fa-house"></i>
          <span class="btn-home-text">Início</span>
        </a>

        <form class="search-form" action="<?= base_url('search.php') ?>" method="get" role="search">
          <i class="fa-solid fa-magnifying-glass"></i>
          <label for="searchInput" class="sr-only">Buscar produtos, lojas ou marcas</label>
          <input type="search" id="searchInput" name="q" placeholder="Buscar produtos, lojas ou marcas…"
                 value="<?= e($_GET['q'] ?? '') ?>">
          <button type="submit" class="btn-search">Buscar</button>
        </form>
      </div>

      <!-- Botão hamburguer (mobile) -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Abrir menu">
        <i class="fa-solid fa-bars"></i>
      </button>

      <!-- Menu colapsável -->
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">Categorias</a>
            <ul class="dropdown-menu">
              <?php foreach ($navGenders as $g): ?>
                <li><a class="dropdown-item" href="<?= base_url('search.php?gender=' . urlencode($g['slug'])) ?>">
                  <?= e($g['name']) ?></a></li>
              <?php endforeach; ?>
              <li><hr class="dropdown-divider"></li>
              <?php foreach ($navCategories as $c): ?>
                <li><a class="dropdown-item" href="<?= base_url('search.php?category=' . urlencode($c['slug'])) ?>">
                  <?= e($c['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link" href="<?= base_url('stores.php') ?>">Lojas</a></li>

          <?php if ($__user): ?>
            <?php if ($__user['role'] === 'seller'): ?>
              <li class="nav-item"><a class="nav-link" href="<?= base_url('seller/dashboard.php') ?>">Meu painel</a></li>
            <?php elseif ($__user['role'] === 'admin'): ?>
              <li class="nav-item"><a class="nav-link" href="<?= base_url('admin/dashboard.php') ?>">Admin</a></li>
            <?php endif; ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                <i class="fa-regular fa-user"></i> <?= e(explode(' ', $__user['name'])[0]) ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($__user['role'] === 'seller'): ?>
                  <li><a class="dropdown-item" href="<?= base_url('seller/dashboard.php') ?>">Meu painel</a></li>
                <?php endif; ?>
                <?php if ($__user['role'] === 'admin'): ?>
                  <li><a class="dropdown-item" href="<?= base_url('admin/dashboard.php') ?>">Painel admin</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= base_url('logout.php') ?>">Sair</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="<?= base_url('login.php') ?>">Entrar</a></li>
            <li class="nav-item ms-lg-2">
              <a class="btn btn-brand btn-sm px-3" href="<?= base_url('register-seller.php') ?>">Criar minha loja</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
</header>

<?php if ($flashes): ?>
<div class="container mt-3">
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
      <?= e($f['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<main id="main">