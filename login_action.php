<?php
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /index.php'); exit; }

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT id, pass_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$u = $stmt->fetch();

if (!$u || !password_verify($pass, $u['pass_hash'])) {
  header('Location: /index.php?err=' . urlencode('Credenciais inválidas.'));
  exit;
}
$_SESSION['user_id'] = $u['id'];
header('Location: /dashboard.php');
