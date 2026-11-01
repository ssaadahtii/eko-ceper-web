<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function rupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function set_old(array $data): void
{
    unset($data['password'], $data['password_confirmation'], $data['_token']);
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = db()->prepare('SELECT setting_value FROM store_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $cache[$key] = ($value !== false ? (string) $value : $default);
    } catch (Throwable) {
        return $default;
    }
}

function store_name(): string
{
    return setting('store_name', APP_NAME) ?: APP_NAME;
}

function absolute_url(string $path = ''): string
{
    $configured = rtrim(setting('app_url', ''), '/');
    if ($configured !== '') {
        return $configured . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

function whatsapp_url(string $message = ''): ?string
{
    $number = preg_replace('/\D+/', '', setting('whatsapp_number', '')) ?? '';
    if ($number === '') {
        return null;
    }
    if (str_starts_with($number, '0')) {
        $number = '62' . substr($number, 1);
    }
    $text = $message !== '' ? $message : 'Halo ' . store_name() . ', saya ingin bertanya tentang produk.';
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
}

function valid_coordinate(float $latitude, float $longitude): bool
{
    return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
}

function distance_km(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
{
    $earthRadius = 6371.0088;
    $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
    $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
    $a = sin($latitudeDelta / 2) ** 2
        + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function slugify(string $text): string
{
    $text = trim(strtolower($text));
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function unique_slug(string $table, string $name, ?int $ignoreId = null): string
{
    if (!in_array($table, ['products', 'categories'], true)) {
        throw new InvalidArgumentException('Tabel slug tidak valid.');
    }
    $base = slugify($name);
    $slug = $base;
    $counter = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?" . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($ignoreId ? [$slug, $ignoreId] : [$slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $counter++;
    }
}

function cart_count(): int
{
    if (!is_customer()) {
        return 0;
    }
    $stmt = db()->prepare('SELECT COALESCE(SUM(ci.quantity), 0) FROM carts c LEFT JOIN cart_items ci ON ci.cart_id = c.id WHERE c.user_id = ?');
    $stmt->execute([current_user()['id']]);
    return (int) $stmt->fetchColumn();
}

function get_or_create_cart(int $userId): int
{
    $stmt = db()->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    db()->prepare('INSERT INTO carts (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())')->execute([$userId]);
    return (int) db()->lastInsertId();
}

function upload_image(array $file, string $relativeDirectory, string $prefix = 'img', int $maxBytes = 3145728): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File gambar belum dipilih atau gagal diunggah.');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Ukuran gambar maksimal 3 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WebP.');
    }
    $directory = APP_ROOT . '/' . trim($relativeDirectory, '/');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori upload tidak dapat dibuat.');
    }
    $filename = $prefix . '-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Gagal menyimpan gambar.');
    }
    return trim($relativeDirectory, '/') . '/' . $filename;
}

function remove_upload(?string $path): void
{
    if (!$path || str_contains($path, '..')) {
        return;
    }
    $fullPath = APP_ROOT . '/' . ltrim($path, '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function product_image(?string $path): string
{
    return $path ? url($path) : asset('images/product-placeholder.svg');
}

function order_status_label(string $status): string
{
    return [
        'pending' => 'Pending', 'processing' => 'Diproses', 'packed' => 'Dikemas',
        'shipped' => 'Dikirim', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan',
    ][$status] ?? ucfirst($status);
}

function payment_status_label(string $status): string
{
    return [
        'unpaid' => 'Belum Lunas', 'waiting_verification' => 'Menunggu Verifikasi',
        'paid' => 'Lunas', 'rejected' => 'Ditolak', 'refunded' => 'Dikembalikan',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function fulfillment_label(string $method): string
{
    return $method === 'pickup' ? 'Ambil di Toko' : 'Diantar ke Alamat';
}

function status_class(string $status): string
{
    return match ($status) {
        'paid', 'completed' => 'success',
        'waiting_verification', 'processing', 'packed' => 'warning',
        'shipped' => 'info',
        'rejected', 'cancelled' => 'danger',
        default => 'secondary',
    };
}

function valid_order_transition(string $from, string $to, string $fulfillmentMethod = 'delivery'): bool
{
    $allowed = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['packed', 'cancelled'],
        'packed' => $fulfillmentMethod === 'pickup' ? ['completed', 'cancelled'] : ['shipped', 'cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];
    return in_array($to, $allowed[$from] ?? [], true);
}

function order_number(PDO $pdo): string
{
    $date = date('Ymd');
    $counterDate = date('Y-m-d');
    $pdo->prepare('INSERT INTO daily_counters (counter_date,order_seq,invoice_seq) VALUES (?,1,0) ON DUPLICATE KEY UPDATE order_seq=order_seq+1')->execute([$counterDate]);
    $stmt = $pdo->prepare('SELECT order_seq FROM daily_counters WHERE counter_date=? FOR UPDATE');
    $stmt->execute([$counterDate]);
    $sequence = (int) $stmt->fetchColumn();
    return sprintf('EKC-%s-%05d', $date, $sequence);
}

function invoice_number(PDO $pdo): string
{
    $date = date('Ymd');
    $counterDate = date('Y-m-d');
    $pdo->prepare('INSERT INTO daily_counters (counter_date,order_seq,invoice_seq) VALUES (?,0,1) ON DUPLICATE KEY UPDATE invoice_seq=invoice_seq+1')->execute([$counterDate]);
    $stmt = $pdo->prepare('SELECT invoice_seq FROM daily_counters WHERE counter_date=? FOR UPDATE');
    $stmt->execute([$counterDate]);
    $sequence = (int) $stmt->fetchColumn();
    return sprintf('INV-%s-%05d', $date, $sequence);
}

function query_string(array $overrides = []): string
{
    return http_build_query(array_merge($_GET, $overrides));
}
