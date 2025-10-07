<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /index.php'); exit; }
$email = trim($_POST['email'] ?? ''); $pass  = $_POST['password'] ?? '';
if ($email === '' || $pass === '') { header('Location: /index.php?err=' . urlencode('Informe e-mail e senha.')); exit; }
try {
  $st = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
  $st->execute([$email]); $u = $st->fetch();
  if (!$u || !password_verify($pass, $u['password_hash'])) { header('Location: /index.php?err=' . urlencode('Credenciais inválidas.')); exit; }
  $_SESSION['user_id'] = (int)$u['id']; header('Location: /dashboard.php'); exit;
} catch (Throwable $e) { http_response_code(500); echo 'Erro no login: ' . htmlspecialchars($e->getMessage()); }