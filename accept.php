<?php
require_once __DIR__ . '/config.php';

function accept_invite_row($pdo, $inv) {
  if (!is_logged_in()) {
    $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /index.php?ok=' . urlencode('Faça login para aceitar o convite.'));
    exit;
  }
  $pdo->prepare("INSERT IGNORE INTO doc_users (doc_id, user_id, role) VALUES (?, ?, ?)")
      ->execute([(int)$inv['doc_id'], (int)current_user_id(), $inv['role']]);
  $pdo->prepare("UPDATE invitations SET status='accepted' WHERE id=?")
      ->execute([(int)$inv['id']]);
  header('Location: /canvas.php?id='.(int)$inv['doc_id']);
  exit;
}

if (isset($_GET['token'])) {
  $token = $_GET['token'];
  $stmt = $pdo->prepare("SELECT * FROM invitations WHERE token=? AND status='pending'");
  $stmt->execute([$token]);
  $inv = $stmt->fetch();
  if (!$inv) { die('Convite não encontrado ou já aceito.'); }
  accept_invite_row($pdo, $inv);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_id'])) {
  $iid = (int)$_POST['invite_id'];
  $stmt = $pdo->prepare("SELECT * FROM invitations WHERE id=? AND status='pending'");
  $stmt->execute([$iid]);
  $inv = $stmt->fetch();
  if (!$inv) { die('Convite inválido.'); }
  accept_invite_row($pdo, $inv);
}

http_response_code(400);
echo "Requisição inválida.";
