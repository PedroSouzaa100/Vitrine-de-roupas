<?php
require_once __DIR__ . '/catalog.php';
$hasPromo = !empty($p['promo_price']) && $p['promo_price'] > 0 && $p['promo_price'] < $p['price'];
$link = url_product($p['slug']);
$cardSizes = product_sizes($p['sizes'] ?? null);
?>
<article class="product-card" data-cart-product>
  <a href="<?= e($link) ?>" class="product-thumb" aria-label="Ver <?= e($p['name']) ?>">
    <img src="<?= e(img_url($p['main_image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php if ($hasPromo): ?>
      <span class="badge-promo">-<?= round(100 - ($p['promo_price'] / $p['price'] * 100)) ?>%</span>
    <?php endif; ?>
  </a>
  <div class="product-body">
    <a class="product-store" href="<?= e(url_store($p['store_slug'])) ?>">
      <i class="fa-solid fa-store"></i> <?= e($p['store_name']) ?>
    </a>
    <h3 class="product-title"><a href="<?= e($link) ?>"><?= e($p['name']) ?></a></h3>
    <?php if (!empty($showStoreCatalog) && !empty($p['description'])): ?>
      <p class="product-card-description text-muted small"><?= e(excerpt($p['description'], 90)) ?></p>
    <?php endif; ?>
    <div class="product-price">
      <?php if ($hasPromo): ?>
        <span class="price-old"><?= money($p['price']) ?></span>
        <span class="price-now"><?= money($p['promo_price']) ?></span>
      <?php else: ?>
        <span class="price-now"><?= money($p['price']) ?></span>
      <?php endif; ?>
    </div>
    <?php if (!empty($p['city'])): ?>
      <div class="product-loc"><i class="fa-solid fa-location-dot"></i>
        <?= e($p['city']) ?><?= !empty($p['state']) ? '/' . e($p['state']) : '' ?></div>
    <?php endif; ?>
    <?php if (!empty($showStoreCatalog)): ?>
      <a class="small d-inline-block mb-2" href="<?= e($link) ?>">Ver fotos e detalhes</a>
      <?php if ($p['status'] === 'active' && (int) $p['stock'] > 0): ?>
        <?php if ($cardSizes): ?>
          <label for="card-size-<?= (int) $p['id'] ?>" class="small fw-semibold mb-1">Tamanho</label>
          <select id="card-size-<?= (int) $p['id'] ?>" class="form-select form-select-sm mb-2" data-cart-size required>
            <?php if (count($cardSizes) > 1): ?><option value="">Selecione</option><?php endif; ?>
            <?php foreach ($cardSizes as $size): ?><option value="<?= e($size) ?>"><?= e($size) ?></option><?php endforeach; ?>
          </select>
        <?php endif; ?>
        <button type="button" class="btn btn-brand w-100 btn-sm" data-cart-add="<?= (int) $p['id'] ?>" aria-label="Adicionar <?= e($p['name']) ?> ao carrinho" disabled><i class="fa-solid fa-cart-plus me-1" aria-hidden="true"></i> Adicionar ao carrinho</button>
      <?php else: ?>
        <span class="status status-suspended w-100 text-center">Indisponível</span>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</article>
