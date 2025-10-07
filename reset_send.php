<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function redirect_msg($ok='', $err=''){
  $q = $ok ? 'ok='.urlencode($ok) : ($err ? 'err='.urlencode($err) : '');
  header('Location: /reset.php'.($q?'?'.$q:'')); exit;
}

try{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_msg('', 'Requisição inválida.');
  $email = trim($_POST['email'] ?? '');
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) redirect_msg('', 'Informe um e-mail válido.');

  // Encontrar usuário
  $u = $pdo->prepare("SELECT id, email, name FROM users WHERE email=? LIMIT 1");
  $u->execute([$email]);
  $user = $u->fetch(PDO::FETCH_ASSOC);

  // Mensagem genérica por segurança, mesmo se usuário não existir
  $generic = 'Se o e-mail existir, enviaremos um link válido por 1 hora. Verifique sua caixa de entrada.';

  if (!$user) redirect_msg($generic,'');

  // Rate limit simples: apaga tokens expirados e deixa seguir (poderia limitar por IP se quiser)
  $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used_at IS NOT NULL")->execute();

  // Cria token
  $token = bin2hex(random_bytes(32));                 // 64 chars
  $hash  = hash('sha256', $token);
  $exp   = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
  $ip    = $_SERVER['REMOTE_ADDR'] ?? null;

  $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, ip) VALUES (?, ?, ?, ?)");
  $ins->execute([(int)$user['id'], $hash, $exp, $ip]);

  $base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')?'https':'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
  $link = $base . '/reset_confirm.php?token=' . urlencode($token);

  // Envio de e-mail (usa mail() se disponível)
  $subject = 'Redefinição de senha — Lifecycle Canvas';
  $body = "Olá".($user['name']?' '.$user['name']:'').",\n\n".
          "Recebemos uma solicitação para redefinir sua senha.\n".
          "Use o link abaixo (válido por 1 hora):\n\n$link\n\n".
          "Se não foi você, ignore este e-mail.\n";
  $headers = "Content-Type: text/plain; charset=UTF-8\r\n";

  // Tente enviar; se falhar, mostramos o link na próxima tela (ambiente dev)
  // ...
$sent = send_system_mail($user['email'], $subject, $body);

if ($sent) {
  redirect_msg('Enviamos instruções para o seu e-mail.');
} else {
  redirect_msg('Servidor de e-mail indisponível. Link direto: '.$link);
}

