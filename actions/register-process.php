<?php
require_once __DIR__ . '/../config/app.php';
if (!is_post()) redirect('register.php');
verify_csrf();

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$confirmation = (string) ($_POST['password_confirmation'] ?? '');
set_old(compact('name','email','phone'));
$errors = [];
if (mb_strlen($name) < 3 || mb_strlen($name) > 120) $errors[] = 'Nama harus 3–120 karakter.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
if (strlen($phone) < 9 || strlen($phone) > 20) $errors[] = 'Nomor HP tidak valid.';
if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
if ($password !== $confirmation) $errors[] = 'Konfirmasi password tidak sama.';
$stmt = db()->prepare('SELECT id FROM users WHERE email=?'); $stmt->execute([$email]);
if ($stmt->fetch()) $errors[] = 'Email sudah terdaftar.';
if ($errors) {
    $_SESSION['form_errors'] = $errors;
    redirect('register.php');
}
$stmt = db()->prepare("INSERT INTO users (name,email,phone,password,role) VALUES (?,?,?,?, 'customer')");
$stmt->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
$userId = (int) db()->lastInsertId();
clear_old();
notify_customer_registered($userId);
flash('success','Pendaftaran berhasil. Silakan masuk menggunakan akun Anda.');
redirect('login.php');
