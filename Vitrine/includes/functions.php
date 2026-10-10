<?php
require_once __DIR__ . '/../config/database.php';

/* =========================================================
 *  Segurança
 * ========================================================= */

function security_headers(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; "
         . "img-src 'self' data: https:; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
         . "script-src 'self' https://cdn.jsdelivr.net; "
         . "connect-src 'self'; "
         . "frame-ancestors 'self'");
}

function rate_limit(string $key, int $max = 5, int $windowS = 300): void
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dir = sys_get_temp_dir() . '/vitrine_rl';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);

    $file = $dir . '/' . preg_replace('~[^a-z0-9_]~i', '_', $key . '_' . $ip) . '.json';
    $now  = time();
    $data = ['start' => $now, 'count' => 0];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $old = $raw ? json_decode($raw, true) : null;
        if (is_array($old) && ($now - ($old['start'] ?? 0)) < $windowS) {
            $data = $old;
        }
    }

    $data['count'] = ($data['count'] ?? 0) + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $max) {
        http_response_code(429);
        die('Muitas tentativas. Aguarde alguns minutos e tente novamente.');
    }
}

function require_owner(?int $ownerId, ?int $currentId): void
{
    if (!$ownerId || !$currentId || $ownerId !== $currentId) {
        http_response_code(403);
        die('Acesso negado.');
    }
}

/* =========================================================
 *  Saída / URLs
 * ========================================================= */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function redirect(string $path): void
{
    if (preg_match('~^https?://~i', $path)) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . base_url($path));
    }
    exit;
}

function url_store(string $slug): string
{
    return PRETTY_URLS ? base_url('loja/' . $slug) : base_url('store.php?slug=' . urlencode($slug));
}

function url_product(string $slug): string
{
    return PRETTY_URLS ? base_url('produto/' . $slug) : base_url('product.php?slug=' . urlencode($slug));
}

/* =========================================================
 *  Flash messages
 * ========================================================= */

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* =========================================================
 *  CSRF
 * ========================================================= */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('Sessão expirada ou token inválido. Volte e tente novamente.');
    }
}

/* =========================================================
 *  Utilidades
 * ========================================================= */

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower(preg_replace('~[^a-zA-Z0-9]+~', '-', $text));
    return trim($text, '-') ?: 'item';
}

function unique_slug(string $table, string $slug, ?int $ignoreId = null): string
{
    $pdo = db();
    $original = $slug;
    $i = 1;
    while (true) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE slug = ?";
        $params = [$slug];
        if ($ignoreId) {
            $sql .= " AND id <> ?";
            $params[] = $ignoreId;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        if (!$st->fetchColumn()) {
            return $slug;
        }
        $slug = $original . '-' . (++$i);
    }
}

function money($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function excerpt(?string $text, int $limit = 110): string
{
    $text = trim(strip_tags((string) $text));
    return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
}

function img_url(?string $path, string $fallback = 'assets/images/placeholder.svg'): string
{
    return $path ? UPLOAD_URL . $path : base_url($fallback);
}

function current_query(array $changes = []): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return strtok($_SERVER['REQUEST_URI'], '?') . ($q ? '?' . http_build_query($q) : '');
}

function paginate_links(int $total, int $perPage, int $page): string
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) return '';

    $html = '<nav aria-label="Paginação"><ul class="pagination justify-content-center">';
    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $html .= '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="'
        . e(current_query(['page' => max(1, $page - 1)])) . '">&laquo;</a></li>';

    for ($i = 1; $i <= $pages; $i++) {
        if ($pages > 9 && $i > 3 && $i < $pages - 2 && abs($i - $page) > 1) {
            if ($i === 4) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            continue;
        }
        $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '">'
            . '<a class="page-link" href="' . e(current_query(['page' => $i])) . '">' . $i . '</a></li>';
    }

    $nextDisabled = $page >= $pages ? ' disabled' : '';
    $html .= '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="'
        . e(current_query(['page' => min($pages, $page + 1)])) . '">&raquo;</a></li>';

    return $html . '</ul></nav>';
}

/* =========================================================
 *  Upload
 * ========================================================= */

function upload_image(array $file, string $folder, int $maxMb = MAX_UPLOAD_MB): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no upload da imagem (código ' . $file['error'] . ').');
    if ($file['size'] > $maxMb * 1024 * 1024) throw new RuntimeException("A imagem excede o limite de {$maxMb}MB.");

    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $allowed)) throw new RuntimeException('Formato inválido. Envie JPG, PNG, WEBP ou GIF.');

    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info['mime'], $allowed, true)) throw new RuntimeException('O arquivo enviado não é uma imagem válida.');

    $folder = trim($folder, '/');
    $dir = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta de uploads.');

    $name = bin2hex(random_bytes(8)) . '-' . time() . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Não foi possível salvar a imagem.');

    return $folder . '/' . $name;
}

function delete_upload(?string $relativePath): void
{
    if (!$relativePath) return;
    $full = UPLOAD_PATH . '/' . ltrim($relativePath, '/');
    if (is_file($full)) @unlink($full);
}

/* =========================================================
 *  WhatsApp / Instagram
 * ========================================================= */

function wa_link(?string $phone, string $message = ''): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($digits) <= 11) $digits = '55' . $digits;
    return 'https://wa.me/' . $digits . '?text=' . urlencode($message);
}

function instagram_url(?string $handle): ?string
{
    $handle = trim((string) $handle);
    if ($handle === '') return null;
    if (preg_match('~^https?://~i', $handle)) return $handle;
    return 'https://instagram.com/' . ltrim($handle, '@');
}

/* =========================================================
 *  Consultas reutilizáveis
 * ========================================================= */

function products_public_from(): string
{
    return " FROM products p
             JOIN stores s  ON s.id = p.store_id
             JOIN sellers se ON se.id = s.seller_id
             WHERE p.status = 'active' AND s.active = 1 AND se.status = 'approved' ";
}

function get_categories(string $type = 'category'): array
{
    $st = db()->prepare("SELECT * FROM categories WHERE type = ? ORDER BY sort_order, name");
    $st->execute([$type]);
    return $st->fetchAll();
}

function is_favorited(int $productId, ?int $userId): bool
{
    if (!$userId) return false;
    $st = db()->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND product_id = ? LIMIT 1");
    $st->execute([$userId, $productId]);
    return (bool) $st->fetchColumn();
}

function is_store_favorited(int $storeId, ?int $userId): bool
{
    if (!$userId) return false;
    $st = db()->prepare("SELECT 1 FROM store_favorites WHERE user_id = ? AND store_id = ? LIMIT 1");
    $st->execute([$userId, $storeId]);
    return (bool) $st->fetchColumn();
}

function log_admin(string $action, ?string $targetType = null, ?int $targetId = null, string $details = ''): void
{
    $admin = current_user();
    $st = db()->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address)
                         VALUES (?,?,?,?,?,?)");
    $st->execute([
        $admin['id'] ?? null,
        $action,
        $targetType,
        $targetId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}