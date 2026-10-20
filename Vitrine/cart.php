<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
header('Cache-Control: no-store');
$pdo = db();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (input_text($_POST, 'action') === 'checkout') {
            $checkout = cart_whatsapp_checkout($pdo, input_text($_POST, 'revision'));
            redirect($checkout['url']);
        }
        cart_change($pdo, input_text($_POST, 'action'), $_POST);
        redirect('cart.php');
    } catch (CartException $exception) {
        $error = $exception->getMessage();
    }
}
$cart = cart_snapshot($pdo);
$pageTitle = 'Meu carrinho';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4" data-cart-page>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h3 fw-bold mb-1">Meu carrinho</h1><p class="text-muted mb-0">Combine o pedido diretamente com a loja pelo WhatsApp.</p></div>
    <a class="btn btn-outline-brand" data-cart-continue href="<?= e($cart['store']['url'] ?? base_url('stores.php')) ?>"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i> Continuar comprando</a>
  </div>
  <div data-cart-message role="status" aria-live="polite">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php foreach ($cart['notices'] as $notice): ?><div class="alert alert-warning"><?= e($notice) ?></div><?php endforeach; ?>
  </div>
  <div class="form-card text-center py-5" data-cart-empty <?= $cart['items'] ? 'hidden' : '' ?>>
    <i class="fa-solid fa-cart-shopping fa-2x mb-3" aria-hidden="true"></i>
    <h2 class="h5">Seu carrinho está vazio</h2>
    <p class="text-muted">Escolha uma loja e adicione os produtos que deseja pedir.</p>
    <a class="btn btn-brand" href="<?= e(base_url('stores.php')) ?>">Explorar lojas</a>
  </div>
  <div class="row g-4" data-cart-content <?= !$cart['items'] ? 'hidden' : '' ?>>
    <div class="col-lg-8">
      <div class="form-card">
        <h2 class="h5 mb-3">Produtos de <span data-cart-store><?= e($cart['store']['name'] ?? '') ?></span></h2>
        <div data-cart-items>
          <?php foreach ($cart['items'] as $item): ?>
            <article class="cart-item" data-cart-item="<?= e($item['key']) ?>">
              <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="cart-item-image">
              <div class="cart-item-info">
                <a class="fw-bold" href="<?= e($item['url']) ?>"><?= e($item['name']) ?></a>
                <div class="small text-muted">Preço unitário: <?= money($item['unit_cents'] / 100) ?></div>
                <form method="post" class="cart-quantity" data-cart-form>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update"><input type="hidden" name="product_id" value="<?= $item['id'] ?>"><input type="hidden" name="line_id" value="<?= e($item['key']) ?>">
                  <?php if ($item['available_sizes'] || $item['size'] !== ''): ?>
                    <div class="w-100">
                      <label class="small fw-semibold" for="size-<?= e($item['key']) ?>">Tamanho</label>
                      <select id="size-<?= e($item['key']) ?>" class="form-select form-select-sm" name="size" data-cart-line-size <?= $item['available_sizes'] ? 'required' : '' ?>>
                        <option value=""><?= $item['available_sizes'] ? 'Selecione' : 'Sem tamanho' ?></option>
                        <?php if ($item['size'] !== '' && !in_array($item['size'], $item['available_sizes'], true)): ?>
                          <option value="<?= e($item['size']) ?>" selected disabled><?= e($item['size']) ?> (indisponível)</option>
                        <?php endif; ?>
                        <?php foreach ($item['available_sizes'] as $size): ?><option value="<?= e($size) ?>" <?= $size === $item['size'] ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?>
                      </select>
                    </div>
                  <?php else: ?><input type="hidden" name="size" value=""><?php endif; ?>
                  <button type="button" class="btn btn-outline-brand btn-sm" data-cart-step="-1" aria-label="Diminuir quantidade de <?= e($item['name']) ?>, tamanho <?= e($item['size']) ?>" <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>−</button>
                  <label class="sr-only" for="quantity-<?= e($item['key']) ?>">Quantidade de <?= e($item['name']) ?>, tamanho <?= e($item['size']) ?></label>
                  <input id="quantity-<?= e($item['key']) ?>" type="number" class="form-control" name="quantity" min="1" max="<?= $item['max_quantity'] ?>" value="<?= $item['quantity'] ?>" required>
                  <button type="button" class="btn btn-outline-brand btn-sm" data-cart-step="1" aria-label="Aumentar quantidade de <?= e($item['name']) ?>, tamanho <?= e($item['size']) ?>" <?= $item['quantity'] >= $item['max_quantity'] ? 'disabled' : '' ?>>+</button>
                  <button class="btn btn-outline-brand btn-sm">Atualizar</button>
                </form>
                <form method="post" class="mt-2" data-cart-form>
                  <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= $item['id'] ?>"><input type="hidden" name="line_id" value="<?= e($item['key']) ?>">
                  <button class="btn btn-sm btn-outline-brand" aria-label="Remover <?= e($item['name']) ?>, tamanho <?= e($item['size']) ?>">Remover</button>
                </form>
              </div>
              <strong class="cart-item-subtotal"><?= money($item['subtotal_cents'] / 100) ?></strong>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="form-card cart-summary">
        <h2 class="h5">Resumo do pedido</h2>
        <p class="text-muted small"><span data-cart-total-count><?= $cart['count'] ?></span> item(ns)</p>
        <div class="d-flex justify-content-between align-items-center mb-3"><strong>Total</strong><strong class="h4 mb-0" data-cart-total><?= money($cart['total_cents'] / 100) ?></strong></div>
        <p class="small text-muted">Frete, pagamento e disponibilidade final serão combinados com o vendedor.</p>
        <form method="post" data-cart-form>
          <?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="revision" value="<?= e($cart['revision']) ?>">
          <button class="btn btn-whatsapp w-100 py-3" data-cart-checkout <?= !$cart['can_checkout'] ? 'disabled' : '' ?>><i class="fa-brands fa-whatsapp me-2" aria-hidden="true"></i> Finalizar pedido pelo WhatsApp</button>
        </form>
        <p class="small text-muted mt-2">Você precisa enviar a mensagem no WhatsApp. Abrir a conversa não confirma a compra. Seu carrinho será mantido.</p>
        <p class="small text-danger" data-cart-phone-warning <?= $cart['has_valid_phone'] ? 'hidden' : '' ?>>A loja precisa cadastrar um WhatsApp válido para receber pedidos.</p>
        <p class="small text-danger" data-cart-size-warning <?= !$cart['needs_size'] ? 'hidden' : '' ?>>Escolha um tamanho disponível para cada item antes de finalizar.</p>
        <form method="post" data-cart-form data-cart-clear>
          <?= csrf_field() ?><input type="hidden" name="action" value="clear">
          <button class="btn btn-outline-brand w-100 mt-2">Limpar carrinho</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
