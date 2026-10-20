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
    $assetOrigin = APP_URL ? ' ' . APP_URL : '';
    header("Content-Security-Policy: default-src 'self'; "
         . "img-src 'self' data: blob: https:; "
         . "style-src 'self'{$assetOrigin} 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
         . "font-src 'self'{$assetOrigin} https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
         . "script-src 'self'{$assetOrigin} https://cdn.jsdelivr.net; "
         . "connect-src 'self'{$assetOrigin}; "
         . "frame-ancestors 'self'");
}

function rate_limit(string $key, int $max = 5, int $windowS = 300): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dir = sys_get_temp_dir() . '/vitrine_rl';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        http_response_code(503); exit('Tente novamente mais tarde.');
    }
    $file = $dir . '/' . hash('sha256', APP_ROOT . $key . $ip) . '.json';
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if ($handle) fclose($handle);
        http_response_code(503); exit('Tente novamente mais tarde.');
    }
    $now = time();
    $old = json_decode(stream_get_contents($handle), true);
    $data = is_array($old) && $now - ($old['start'] ?? 0) < $windowS ? $old : ['start' => $now, 'count' => 0];
    $data['count'] = ($data['count'] ?? 0) + 1;
    rewind($handle); ftruncate($handle, 0);
    fwrite($handle, json_encode($data)); fflush($handle);
    flock($handle, LOCK_UN); fclose($handle);

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
    return (APP_URL ?: '') . BASE_URL . ltrim($path, '/');
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
    if (STORE_DOMAIN && (STORE_SUBDOMAINS === '*' || (is_array(STORE_SUBDOMAINS) && in_array($slug, STORE_SUBDOMAINS, true))) && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $slug)) {
        $scheme = parse_url(APP_URL, PHP_URL_SCHEME) ?: 'https';
        return $scheme . '://' . $slug . '.' . STORE_DOMAIN . BASE_URL;
    }
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        die('O envio excede o limite do servidor. Reduza as imagens ou ajuste post_max_size na hospedagem.');
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
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
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))
        throw new RuntimeException('Imagem acima do limite permitido pelo servidor. Limite da loja: ' . $maxMb . ' MB; PHP: ' . ini_get('upload_max_filesize') . '.');
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Upload incompleto ou indisponivel. Tente novamente.');
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new RuntimeException('Upload invalido.');
    $size = filesize($tmp);
    if (!$size || $size > $maxMb * 1024 * 1024) throw new RuntimeException("A imagem excede o limite de {$maxMb} MB.");
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $info = @getimagesize($tmp);
    if (!$info || !isset($allowed[$mime]) || $info['mime'] !== $mime)
        throw new RuntimeException('Envie uma imagem JPG, PNG, WEBP ou GIF valida.');
    if ($info[0] * $info[1] > 40000000) throw new RuntimeException('Imagem muito grande. Reduza sua resolucao.');
    if (!in_array($folder, ['products', 'stores'], true)) throw new RuntimeException('Pasta de upload invalida.');
    $dir = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Nao foi possivel criar a pasta de imagens.');
    if (!is_writable($dir)) throw new RuntimeException('A pasta de imagens precisa de permissao de escrita na hospedagem.');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) throw new RuntimeException('Nao foi possivel salvar a imagem.');
    return $folder . '/' . $name;
}

function delete_upload(?string $relativePath): void
{
    if (!$relativePath) return;
    $root = realpath(UPLOAD_PATH);
    $full = realpath(UPLOAD_PATH . '/' . $relativePath);
    if ($root && $full && str_starts_with(str_replace('\\', '/', $full), str_replace('\\', '/', $root) . '/') && is_file($full)) @unlink($full);
}

function store_request_slug(): ?string
{
    if (!STORE_DOMAIN) return null;
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    $suffix = '.' . STORE_DOMAIN;
    if (!str_ends_with($host, $suffix)) return null;
    $slug = substr($host, 0, -strlen($suffix));
    return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $slug) && $slug !== 'www' ? $slug : null;
}

/* =========================================================
 *  WhatsApp / Instagram
 * ========================================================= */

function wa_link(?string $phone, string $message = ''): string
{
    require_once __DIR__ . '/catalog.php';
    $digits = whatsapp_number($phone);
    return $digits ? 'https://wa.me/' . $digits . '?text=' . rawurlencode($message) : '';

}

function instagram_url(?string $handle): ?string
{
    $handle = trim((string) $handle);
    if ($handle === '') return null;
    if (preg_match('~^https?://~i', $handle)) {
        $host = strtolower(parse_url($handle, PHP_URL_HOST) ?: '');
        return in_array($host, ['instagram.com', 'www.instagram.com'], true) ? $handle : null;
    }
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
             JOIN users owner ON owner.id = se.user_id
             WHERE owner.status = 'active' AND p.status = 'active' AND s.active = 1 AND se.status = 'approved' ";
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
