<?php
declare(strict_types=1);

final class SmtpMailer
{
    private $socket;

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private string $username,
        private string $password,
        private int $timeout = 20
    ) {
    }

    public function send(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $html): void
    {
        $transport = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $errorNumber = 0;
        $errorMessage = '';
        $this->socket = @stream_socket_client(
            $transport . '://' . $this->host . ':' . $this->port,
            $errorNumber,
            $errorMessage,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Tidak dapat terhubung ke server SMTP: ' . $errorMessage);
        }
        stream_set_timeout($this->socket, $this->timeout);

        try {
            $this->expect([220]);
            $hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $this->command('EHLO ' . $hostname, [250]);

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Koneksi TLS SMTP gagal dibuat.');
                }
                $this->command('EHLO ' . $hostname, [250]);
            }

            if ($this->username !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->username), [334]);
                $this->command(base64_encode($this->password), [235]);
            }

            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', [354]);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $this->address($fromEmail, $fromName),
                'To: ' . $this->address($toEmail, $toName),
                'Subject: ' . $this->encoded($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $hostname . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $body = chunk_split(base64_encode($html), 76, "\r\n");
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $payload = preg_replace('/(?m)^\./', '..', $payload) ?? $payload;
            fwrite($this->socket, $payload . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
        }
    }

    private function command(string $command, array $expectedCodes): string
    {
        fwrite($this->socket, $command . "\r\n");
        return $this->expect($expectedCodes);
    }

    private function expect(array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Server SMTP menolak permintaan: ' . trim($response));
        }
        return $response;
    }

    private function address(string $email, string $name): string
    {
        return $name !== '' ? $this->encoded($name) . ' <' . $email . '>' : '<' . $email . '>';
    }

    private function encoded(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}

final class MailtrapApiMailer
{
    private const ENDPOINT = 'https://send.api.mailtrap.io/api/send';

    public function __construct(private string $token, private int $timeout = 20)
    {
    }

    public function send(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $html): void
    {
        if ($this->token === '') {
            throw new RuntimeException('API Token Mailtrap belum diisi.');
        }

        $payload = json_encode([
            'from' => ['email' => $fromEmail, 'name' => $fromName],
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => $subject,
            'html' => $html,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (function_exists('curl_init')) {
            $lastStatus = 0;
            $lastResponse = '';
            foreach (['Api-Token: ' . $this->token, 'Authorization: Bearer ' . $this->token] as $authHeader) {
                $curl = curl_init(self::ENDPOINT);
                curl_setopt_array($curl, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_USERAGENT => 'EKO-CEPER/1.0',
                    CURLOPT_HTTPHEADER => [
                        $authHeader,
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                    CURLOPT_POSTFIELDS => $payload,
                ]);
                $response = curl_exec($curl);
                $curlError = curl_error($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($response === false) {
                    throw new RuntimeException('Tidak dapat terhubung ke Mailtrap API: ' . $curlError);
                }
                if ($status >= 200 && $status < 300) {
                    return;
                }
                $lastStatus = $status;
                $lastResponse = (string) $response;
                if ($status !== 401) {
                    break;
                }
            }
            $this->validateResponse($lastStatus, $lastResponse);
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Api-Token: {$this->token}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $response = @file_get_contents(self::ENDPOINT, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        if ($response === false) {
            throw new RuntimeException('Tidak dapat terhubung ke Mailtrap API melalui HTTPS. Pastikan OpenSSL atau cURL PHP aktif.');
        }
        $this->validateResponse($status, $response);
    }

    private function validateResponse(int $status, string $response): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }
        if ($status === 401) {
            $detail = $this->responseMessage($response);
            throw new RuntimeException('Mailtrap menolak autentikasi (HTTP 401)' . ($detail !== '' ? ': ' . $detail : '.') . ' Token telah dicoba menggunakan Api-Token dan Bearer.');
        }
        if ($status === 403) {
            throw new RuntimeException('Mailtrap menolak alamat pengirim. Pastikan domain pengirim sudah terverifikasi dan dapat diakses oleh API Token.');
        }
        $message = $this->responseMessage($response);
        throw new RuntimeException('Mailtrap API menolak pengiriman (HTTP ' . $status . ')' . ($message !== '' ? ': ' . $message : '.'));
    }

    private function responseMessage(string $response): string
    {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $value = $decoded['message'] ?? $decoded['error'] ?? $decoded['errors'] ?? '';
            if (is_array($value)) {
                return implode('; ', array_map(static fn ($item): string => is_scalar($item) ? (string) $item : (json_encode($item) ?: ''), $value));
            }
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }
        return mb_substr(trim(strip_tags($response)), 0, 300);
    }
}

function send_store_email(string $toEmail, string $toName, string $subject, string $content, bool $force = false): bool
{
    if (!$force && setting('email_enabled', '0') !== '1') {
        return false;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Email tujuan tidak valid.');
    }

    $token = trim(setting('smtp_password', ''));
    $fromEmail = trim(setting('smtp_from_email', ''));
    $fromName = trim(setting('smtp_from_name', store_name()));

    if ($token === '') {
        throw new RuntimeException('API Token Mailtrap belum diisi.');
    }
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Email pengirim Mailtrap belum diisi dengan benar.');
    }

    $safeStoreName = e(store_name());
    $html = '<!doctype html><html lang="id"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937">'
        . '<div style="max-width:620px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb">'
        . '<div style="padding:22px 28px;background:#075985;color:#fff;font-size:20px;font-weight:700">' . $safeStoreName . '</div>'
        . '<div style="padding:28px;line-height:1.65">' . $content . '</div>'
        . '<div style="padding:18px 28px;background:#f8fafc;color:#64748b;font-size:12px">Email ini dikirim otomatis oleh ' . $safeStoreName . '.</div>'
        . '</div></body></html>';

    $mailer = new MailtrapApiMailer($token);
    $mailer->send($fromEmail, $fromName, $toEmail, $toName, $subject, $html);
    return true;
}

function notification_admin_email(): string
{
    $email = trim(setting('store_email', ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    try {
        return (string) db()->query("SELECT email FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    } catch (Throwable) {
        return '';
    }
}

function email_action_button(string $label, string $url): string
{
    return '<p style="margin:24px 0"><a href="' . e($url) . '" style="display:inline-block;background:#047857;color:#fff;text-decoration:none;padding:12px 18px;border-radius:9px;font-weight:700">' . e($label) . '</a></p>';
}

function order_email_data(int $orderId): ?array
{
    $stmt = db()->prepare('SELECT o.*,u.name customer_name,u.email customer_email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    return $order;
}

function send_notification_safely(callable $notification): void
{
    try {
        $notification();
    } catch (Throwable $exception) {
        error_log('Email notification: ' . $exception->getMessage());
    }
}

function notify_order_created(int $orderId): void
{
    if (setting('email_enabled', '0') !== '1') {
        return;
    }
    $order = order_email_data($orderId);
    if (!$order) {
        return;
    }

    send_notification_safely(function () use ($order): void {
        $content = '<h2 style="margin-top:0">Pesanan berhasil dibuat</h2>'
            . '<p>Halo ' . e($order['customer_name']) . ', pesanan <strong>' . e($order['order_number']) . '</strong> sudah kami terima.</p>'
            . '<p>Total pesanan: <strong>' . e(rupiah($order['total_amount'])) . '</strong><br>Layanan: <strong>' . e(fulfillment_label($order['fulfillment_method'])) . '</strong><br>Metode pembayaran: <strong>' . e($order['fulfillment_method'] === 'pickup' ? 'COD di Toko' : strtoupper($order['payment_method']) . ' Antar') . '</strong></p>'
            . email_action_button('Lihat pesanan dan nota', absolute_url('order-detail.php?id=' . $order['id']));
        send_store_email($order['customer_email'], $order['customer_name'], 'Pesanan ' . $order['order_number'] . ' berhasil dibuat', $content);
    });

    $adminEmail = notification_admin_email();
    if ($adminEmail !== '') {
        send_notification_safely(function () use ($order, $adminEmail): void {
            $content = '<h2 style="margin-top:0">Ada pesanan baru</h2>'
                . '<p><strong>' . e($order['customer_name']) . '</strong> membuat pesanan <strong>' . e($order['order_number']) . '</strong>.</p>'
                . '<p>Total: <strong>' . e(rupiah($order['total_amount'])) . '</strong><br>Layanan: <strong>' . e(fulfillment_label($order['fulfillment_method'])) . '</strong><br>Jarak pembeli: <strong>' . e(number_format((float) $order['delivery_distance_km'], 2, ',', '.')) . ' km</strong></p>'
                . email_action_button('Buka pesanan', absolute_url('admin/order-detail.php?id=' . $order['id']));
            send_store_email($adminEmail, 'Pemilik Toko', 'Pesanan baru ' . $order['order_number'], $content);
        });
    }
}

function notify_order_status_changed(int $orderId): void
{
    $order = order_email_data($orderId);
    if (!$order) {
        return;
    }
    send_notification_safely(function () use ($order): void {
        $content = '<h2 style="margin-top:0">Status pesanan diperbarui</h2>'
            . '<p>Pesanan <strong>' . e($order['order_number']) . '</strong> sekarang berstatus <strong>' . e(order_status_label($order['order_status'])) . '</strong>.</p>'
            . email_action_button('Lihat detail pesanan', absolute_url('order-detail.php?id=' . $order['id']));
        send_store_email($order['customer_email'], $order['customer_name'], 'Status pesanan ' . $order['order_number'], $content);
    });
}

function notify_payment_status_changed(int $orderId): void
{
    $order = order_email_data($orderId);
    if (!$order) {
        return;
    }
    send_notification_safely(function () use ($order): void {
        $content = '<h2 style="margin-top:0">Status pembayaran diperbarui</h2>'
            . '<p>Pembayaran pesanan <strong>' . e($order['order_number']) . '</strong> sekarang berstatus <strong>' . e(payment_status_label($order['payment_status'])) . '</strong>.</p>'
            . email_action_button('Lihat pembayaran', absolute_url('order-detail.php?id=' . $order['id']));
        send_store_email($order['customer_email'], $order['customer_name'], 'Pembayaran ' . $order['order_number'], $content);
    });
}

function notify_payment_proof_uploaded(int $orderId): void
{
    $order = order_email_data($orderId);
    $adminEmail = notification_admin_email();
    if (!$order || $adminEmail === '') {
        return;
    }
    send_notification_safely(function () use ($order, $adminEmail): void {
        $content = '<h2 style="margin-top:0">Bukti transfer baru</h2>'
            . '<p><strong>' . e($order['customer_name']) . '</strong> mengunggah bukti untuk pesanan <strong>' . e($order['order_number']) . '</strong>.</p>'
            . email_action_button('Periksa pembayaran', absolute_url('admin/order-detail.php?id=' . $order['id']));
        send_store_email($adminEmail, 'Pemilik Toko', 'Verifikasi pembayaran ' . $order['order_number'], $content);
    });
}

function notify_customer_registered(int $userId): void
{
    if (setting('email_enabled', '0') !== '1') {
        return;
    }
    $stmt = db()->prepare("SELECT name,email FROM users WHERE id=? AND role='customer'");
    $stmt->execute([$userId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        return;
    }
    send_notification_safely(function () use ($customer): void {
        $content = '<h2 style="margin-top:0">Akun Anda sudah aktif</h2>'
            . '<p>Halo ' . e($customer['name']) . ', akun Anda di ' . e(store_name()) . ' sudah dapat digunakan untuk berbelanja, melihat status pesanan, dan menerima nota digital.</p>'
            . email_action_button('Mulai belanja', absolute_url('shop.php'));
        send_store_email($customer['email'], $customer['name'], 'Selamat datang di ' . store_name(), $content);
    });
}
