<?php
require_once __DIR__ . '/catalog.php';

class CartException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422) { parent::__construct($message); }
}

function cart_public_store(PDO $pdo, int $id): ?array
{
    $query = $pdo->prepare("SELECT s.id, s.name, s.display_name, s.slug, s.whatsapp FROM stores s
        JOIN sellers se ON se.id = s.seller_id JOIN users u ON u.id = se.user_id
        WHERE s.id = ? AND s.active = 1 AND se.status = 'approved' AND u.status = 'active'");
    $query->execute([$id]);
    return $query->fetch() ?: null;
}

function cart_public_product(PDO $pdo, int $id): ?array
{
    $query = $pdo->prepare("SELECT p.* FROM products p JOIN stores s ON s.id = p.store_id
        JOIN sellers se ON se.id = s.seller_id JOIN users u ON u.id = se.user_id
        WHERE p.id = ? AND p.status = 'active' AND p.stock > 0 AND s.active = 1 AND se.status = 'approved' AND u.status = 'active'");
    $query->execute([$id]);
    return $query->fetch() ?: null;
}

function cart_unit_cents(array $product): int
{
    $price = price_cents((string) $product['price']) ?? 0;
    $promo = price_cents((string) ($product['promo_price'] ?? ''));
    return $promo !== null && $promo > 0 && $promo < $price ? $promo : $price;
}

function cart_empty(): array
{
    return ['version'=>2, 'store_id'=>0, 'items'=>[]];
}

function cart_line_key(int $id, string $size): string
{
    return $id . '-' . substr(hash('sha256', $size), 0, 24);
}

function cart_selected_size(array $product, $input): string
{
    if (!is_string($input)) throw new CartException('Selecione um tamanho válido.');
    $size = trim($input);
    $sizes = product_sizes($product['sizes']);
    if ($size === '' && count($sizes) === 1) return $sizes[0];
    if (!$sizes && $size === '') return '';
    if ($size === '' || !in_array($size, $sizes, true)) throw new CartException('Selecione um dos tamanhos disponíveis para este produto.');
    return $size;
}

/** Atualiza sessões anteriores sem inventar um tamanho para o cliente. */
function cart_session(PDO $pdo): array
{
    $saved = $_SESSION['shopping_cart'] ?? cart_empty();
    if (($saved['version'] ?? 0) === 2) return $saved;
    $converted = cart_empty();
    $converted['store_id'] = (int) ($saved['store_id'] ?? 0);
    foreach ($saved['items'] ?? [] as $id=>$quantity) {
        $product = cart_public_product($pdo, (int) $id);
        $sizes = $product ? product_sizes($product['sizes']) : [];
        $size = count($sizes) === 1 ? $sizes[0] : '';
        $converted['items'][cart_line_key((int) $id, $size)] = [
            'product_id'=>(int) $id, 'size'=>$size, 'quantity'=>(int) $quantity,
            'unit_cents'=>$saved['prices'][$id] ?? null,
        ];
    }
    return $converted;
}

/** Revalida tamanho, preço e estoque total do produto, somando todas as variantes. */
function cart_snapshot(PDO $pdo): array
{
    $saved = cart_session($pdo);
    $notices = [];
    $store = $saved['items'] ? cart_public_store($pdo, (int) $saved['store_id']) : null;
    if (!$store && $saved['items']) {
        $notices[] = 'A loja ficou indisponível. O carrinho foi esvaziado.';
        $saved = cart_empty();
    }
    $items = [];
    $total = 0;
    $count = 0;
    $used = [];
    $products = [];
    $needsSize = false;
    foreach ($saved['items'] as $key=>$line) {
        $id = (int) $line['product_id'];
        if (!array_key_exists($id, $products)) $products[$id] = cart_public_product($pdo, $id);
        $product = $products[$id];
        if (!$product || (int) $product['store_id'] !== (int) $saved['store_id'] || cart_unit_cents($product) < 1) {
            unset($saved['items'][$key]);
            $notices[] = 'Um produto removido ou indisponível foi retirado do carrinho.';
            continue;
        }
        $remaining = min(999, (int) $product['stock']) - ($used[$id] ?? 0);
        if ($remaining < 1) {
            unset($saved['items'][$key]);
            $notices[] = 'Uma variação de ' . $product['name'] . ' foi retirada porque o estoque total diminuiu.';
            continue;
        }
        $quantity = min($remaining, max(1, (int) $line['quantity']));
        if ($quantity !== $line['quantity']) $notices[] = 'A quantidade de ' . $product['name'] . ' foi ajustada ao estoque disponível.';
        $size = $line['size'];
        $sizes = product_sizes($product['sizes']);
        $validSize = $sizes ? in_array($size, $sizes, true) : $size === '';
        if (!$validSize) {
            $needsSize = true;
            $notices[] = 'Escolha um tamanho disponível para ' . $product['name'] . ' no carrinho.';
        }
        $cents = cart_unit_cents($product);
        if ($line['unit_cents'] !== null && $line['unit_cents'] !== $cents) $notices[] = 'O preço de ' . $product['name'] . ' foi atualizado. Confira o total.';
        $saved['items'][$key]['quantity'] = $quantity;
        $saved['items'][$key]['unit_cents'] = $cents;
        $used[$id] = ($used[$id] ?? 0) + $quantity;
        $subtotal = $cents * $quantity;
        $items[] = ['key'=>$key, 'id'=>$id, 'name'=>$product['name'], 'size'=>$size,
            'available_sizes'=>$sizes, 'needs_size'=>!$validSize, 'image'=>img_url($product['main_image']),
            'url'=>url_product($product['slug']), 'quantity'=>$quantity,
            'unit_cents'=>$cents, 'subtotal_cents'=>$subtotal];
        $total += $subtotal;
        $count += $quantity;
    }
    foreach ($items as &$item) {
        $item['max_quantity'] = min(999, (int) $products[$item['id']]['stock']) - ($used[$item['id']] - $item['quantity']);
    }
    unset($item);
    if (!$items) { $saved = cart_empty(); $store = null; }
    $_SESSION['shopping_cart'] = $saved;
    $publicStore = $store ? ['id'=>(int) $store['id'], 'name'=>$store['display_name'] ?: $store['name'],
        'url'=>url_store($store['slug'])] : null;
    $phone = $store ? whatsapp_number($store['whatsapp']) : null;
    $revision = hash('sha256', json_encode([$publicStore, $items, $phone], JSON_UNESCAPED_UNICODE));
    return ['store'=>$publicStore, 'items'=>$items, 'count'=>$count, 'total_cents'=>$total,
        'has_valid_phone'=>!!$phone, 'needs_size'=>$needsSize,
        'can_checkout'=>!!$phone && !!$items && !$needsSize, 'revision'=>$revision,
        'notices'=>array_values(array_unique($notices))];
}

function cart_existing_line(array $cart, array $input): string
{
    if (isset($input['line_id'])) {
        if (!is_string($input['line_id']) || !isset($cart['items'][$input['line_id']])) throw new CartException('Este item não está mais no carrinho.', 409);
        return $input['line_id'];
    }
    // Compatibilidade com formulários antigos, somente se o produto tiver uma única linha.
    $id = filter_var($input['product_id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $matches = array_keys(array_filter($cart['items'], fn($line) => $line['product_id'] === $id));
    if (count($matches) !== 1) throw new CartException('Selecione o item e tamanho no carrinho.', 409);
    return $matches[0];
}

function cart_change(PDO $pdo, string $action, array $input): array
{
    $before = cart_snapshot($pdo);
    if ($action === 'clear') {
        unset($_SESSION['shopping_cart']);
    } else {
        $cart = &$_SESSION['shopping_cart'];
        if (!in_array($action, ['add', 'update', 'remove'], true)) throw new CartException('Ação inválida.');
        $oldKey = $action !== 'add' ? cart_existing_line($cart, $input) : null;
        if ($action === 'remove') {
            unset($cart['items'][$oldKey]);
        } else {
            $id = $oldKey !== null ? $cart['items'][$oldKey]['product_id'] : filter_var($input['product_id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if (!$id) throw new CartException('Selecione um produto válido.');
            $quantity = filter_var($input['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>999]]);
            if ($quantity === false) throw new CartException('A quantidade deve ser um número inteiro entre 1 e 999.');
            $product = cart_public_product($pdo, $id);
            if (!$product) throw new CartException('Este produto está indisponível.', 409);
            if ($cart['items'] && (int) $cart['store_id'] !== (int) $product['store_id'])
                throw new CartException('Seu carrinho pertence a outra loja. Finalize o pedido ou limpe o carrinho antes de trocar de loja.', 409);
            $size = cart_selected_size($product, $input['size'] ?? ($oldKey !== null ? $cart['items'][$oldKey]['size'] : ''));
            $key = cart_line_key($id, $size);
            if ($action === 'add' || $key !== $oldKey) $quantity += $cart['items'][$key]['quantity'] ?? 0;
            $otherQuantity = 0;
            foreach ($cart['items'] as $existingKey=>$line) {
                if ($line['product_id'] === $id && $existingKey !== $oldKey && $existingKey !== $key) $otherQuantity += $line['quantity'];
            }
            if ($quantity + $otherQuantity > min(999, (int) $product['stock'])) throw new CartException('Quantidade maior que o estoque total disponível deste produto.', 409);
            $lineCount = count($cart['items']) - ($oldKey !== null ? 1 : 0);
            if (!isset($cart['items'][$key]) && $lineCount >= 50) throw new CartException('O carrinho aceita até 50 itens diferentes.');
            if ($oldKey !== null && $oldKey !== $key) unset($cart['items'][$oldKey]);
            $cart['store_id'] = (int) $product['store_id'];
            $cart['items'][$key] = ['product_id'=>$id, 'size'=>$size, 'quantity'=>$quantity, 'unit_cents'=>cart_unit_cents($product)];
        }
    }
    $after = cart_snapshot($pdo);
    $after['notices'] = array_values(array_unique(array_merge($before['notices'], $after['notices'])));
    return $after;
}

function cart_whatsapp_checkout(PDO $pdo, string $revision): array
{
    $snapshot = cart_snapshot($pdo);
    if (!$snapshot['items']) throw new CartException('Seu carrinho está vazio.', 409);
    if ($snapshot['needs_size']) throw new CartException('Escolha o tamanho de cada produto antes de finalizar.', 409);
    if (!$revision || !hash_equals($snapshot['revision'], $revision))
        throw new CartException('O carrinho mudou. Confira os produtos, as quantidades e os preços antes de finalizar novamente.', 409);
    $store = cart_public_store($pdo, $snapshot['store']['id']);
    $phone = $store ? whatsapp_number($store['whatsapp']) : null;
    if (!$phone) throw new CartException('A loja precisa cadastrar um WhatsApp válido antes de receber pedidos.', 409);
    $message = 'Olá, ' . $snapshot['store']['name'] . "! Meu pedido:\n\n";
    foreach ($snapshot['items'] as $item) {
        $size = $item['size'] !== '' ? ' | Tam. ' . $item['size'] : '';
        $message .= $item['quantity'] . 'x ' . $item['name'] . $size . ' | '
            . money($item['unit_cents'] / 100) . '/un = ' . money($item['subtotal_cents'] / 100) . "\n";
    }
    $message .= "\n*Total (" . $snapshot['count'] . ($snapshot['count'] === 1 ? ' item): ' : ' itens): ') . money($snapshot['total_cents'] / 100)
        . "*\nconfirma?";
    return ['url'=>'https://wa.me/' . $phone . '?text=' . rawurlencode($message), 'cart'=>$snapshot];
}
