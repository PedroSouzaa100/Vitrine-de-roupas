<?php
$hasPromo = !empty($p['promo_price']) && $p['promo_price'] > 0 && $p['promo_price'] < $p['price'];
$link = url_product($p['slug']);
?>
<article class="product-card">
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
  </div>
</article>