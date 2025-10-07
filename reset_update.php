<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function back_err($msg, $token){
  header('Location: /reset_confirm.php?token='.urlencode($token).'&err='.urlencode($msg)); exit;
}

try{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Método inválido'; exit; }

  $token = $_POST['token'] ?? '';
  $p1    = $_POST['password']  ?? '';
  $p2    = $_POST['password2'] ?? '';

  if ($token === '') { http_response_code(400); echo 'Token ausente'; exit; }
  if ($p1 === '' || $p2 === '') back_err('Preencha a senha em ambos os campos.', $token);
  if ($p1 !== $p2) back_err('As senhas não conferem.', $token);
  if (strlen($p1) < 6) back_err('A senha deve ter ao menos 6 caracteres.', $token);

  $hashToken = hash('sha256', $token);
  $st = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash=? LIMIT 1");
  $st->execute([$hashToken]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
    http_response_code(400); echo 'Token inválido ou expirado'; exit;
  }

  $newHash = password_hash($p1, PASSWORD_DEFAULT);

  $pdo->beginTransaction();
  // Atualiza a senha
  $up = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
  $up->execute([$newHash, (int)$row['user_id']]);

  // Marca token como usado
  $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")->execute([(int)$row['id']]);

  // (opcional) invalida outros tokens ativos do mesmo usuário
  $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL AND token_hash<>?")->execute([(int)$row['user_id'], $hashToken]);

  $pdo->commit();

  header('Location: /index.php?ok='.urlencode('Senha redefinida com sucesso. Faça login.')); exit;

} catch(Throwable $e){
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500); echo 'Erro ao redefinir: '.htmlspecialchars($e->getMessage());
}
