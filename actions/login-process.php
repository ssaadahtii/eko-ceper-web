<?php
require_once __DIR__ . '/../config/app.php';
if (!is_post()) redirect('login.php');
verify_csrf();

$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
set_old(['email' => $email]);

$_SESSION['login_window'] = $_SESSION['login_window'] ?? ['start' => time(), 'attempts' => 0];
if (time() - $_SESSION['login_window']['start'] > 900) {
    $_SESSION['login_window'] = ['start' => time(), 'attempts' => 0];
}
if ($_SESSION['login_window']['attempts'] >= 10) {
    flash('danger', 'Terlalu banyak percobaan login. Coba lagi setelah 15 menit.');
    redirect('login.php');
}

$stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
    flash('danger', 'Akun dikunci sementara. Coba kembali beberapa menit lagi.');
    redirect('login.php');
}

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_window']['attempts']++;
    if ($user) {
        $attempts = (int) $user['login_attempts'] + 1;
        $locked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
        db()->prepare('UPDATE users SET login_attempts=?, locked_until=? WHERE id=?')->execute([$attempts >= 5 ? 0 : $attempts, $locked, $user['id']]);
    }
    flash('danger', 'Email atau password salah.');
    redirect('login.php');
}

db()->prepare('UPDATE users SET login_attempts=0, locked_until=NULL WHERE id=?')->execute([$user['id']]);
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['login_window'] = ['start' => time(), 'attempts' => 0];
clear_old();
$intended = $_SESSION['intended_url'] ?? null;
unset($_SESSION['intended_url']);
flash('success', 'Selamat datang, ' . $user['name'] . '!');
if ($user['role'] === 'admin') redirect('admin/index.php');
if ($intended && str_starts_with($intended, BASE_URL . '/')) {
    header('Location: ' . $intended); exit;
}
redirect('shop.php');
