<?php
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /index.php'); exit; }

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

if (!$name || !$email || strlen($pass) < 6) {
  header('Location: /index.php?err=' . urlencode('Preencha os campos corretamente.'));
  exit;
}

try {
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $pdo->prepare("INSERT INTO users (name, email, pass_hash) VALUES (?, ?, ?)")->execute([$name, $email, $hash]);
  header('Location: /index.php?ok=' . urlencode('Conta criada. Faça login.'));
} catch (Exception $e) {
  header('Location: /index.php?err=' . urlencode('Não foi possível criar sua conta. Talvez o e-mail já exista.'));
}
