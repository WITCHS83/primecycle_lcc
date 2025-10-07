<?php
/* ===== DB ===== */
$DB_HOST = 'sql313.infinityfree.com';
$DB_PORT = 3306;
$DB_NAME = 'if0_40078277_lifecyclecanvas';
$DB_USER = 'if0_40078277';
$DB_PASS = 'nlun81OuNoLMQUU';
$DB_CHARSET = 'utf8mb4';

try {
  $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=$DB_CHARSET";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo "Erro ao conectar ao banco de dados: " . htmlspecialchars($e->getMessage());
  exit;
}

/* ===== Sessão & Auth ===== */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool { return isset($_SESSION['user_id']); }
}
if (!function_exists('require_login')) {
  function require_login(){ if (!is_logged_in()){ header('Location: /index.php'); exit; } }
}
if (!function_exists('current_user_id')) {
  function current_user_id(){ return $_SESSION['user_id'] ?? null; }
}

/* ===== Site admin (ajuste a regra) ===== */
if (!function_exists('is_site_admin')) {
  // Troque a regra se você possuir coluna is_admin na tabela users
  function is_site_admin(): bool {
    return (int)current_user_id() === 1;
  }
}

/* ===== Key/Value settings (app_settings) ===== */
if (!function_exists('app_get')) {
  function app_get($key, $default=''){
    global $pdo;
    $st = $pdo->prepare("SELECT value FROM app_settings WHERE `key`=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v===false) ? $default : $v;
  }
}
if (!function_exists('app_set')) {
  function app_set($key, $value){
    global $pdo;
    $st = $pdo->prepare("INSERT INTO app_settings (`key`,`value`) VALUES (?,?)
                         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    $st->execute([$key, $value]);
  }
}

/* ===== Mailer simples (SMTP AUTH com TLS/SSL) ===== */
/* ===== Mailer unificado: mail() | SMTP | SendGrid API =====
   Chave de seleção: app_get('mail_method','smtp') -> smtp | mail | sendgrid
*/
function send_system_mail(string $to, string $subject, string $body): bool {
  $method = strtolower(app_get('mail_method','smtp')); // smtp | mail | sendgrid

  $from  = app_get('smtp_from_email','no-reply@localhost');
  $fromN = app_get('smtp_from_name','Lifecycle Canvas');

  // Cabeçalhos comuns / fallback mail()
  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . (mb_encode_mimeheader($fromN, 'UTF-8') . " <{$from}>"),
    'Reply-To: ' . $from,
  ];
  $hdr = implode("\r\n", $headers);

  /* -------- Método: SendGrid API (recomendado em hospedagens que bloqueiam SMTP) -------- */
  if ($method === 'sendgrid') {
    $apiKey = app_get('sendgrid_api_key','');
    if (!$apiKey) { $GLOBALS['smtp_debug'] = "Faltando sendgrid_api_key."; return false; }

    $payload = [
      "personalizations" => [[ "to" => [[ "email" => $to ]] ]],
      "from"  => [ "email" => $from, "name" => $fromN ],
      "subject" => $subject,
      "content" => [[ "type" => "text/plain", "value" => $body ]]
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
      ],
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) { return true; }
    $GLOBALS['smtp_debug'] = "SendGrid HTTP {$code}. Resp: ".substr((string)$resp,0,500)." Err: ".$err;
    return false;
  }

  /* -------- Método: mail() (só funciona se a hospedagem permitir) -------- */
  if ($method === 'mail') {
    return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $hdr);
  }

  /* -------- Método: SMTP (se a hospedagem permitir saída) -------- */
  $enabled = app_get('smtp_enabled','0') === '1';
  if (!$enabled) {
    // Se SMTP desabilitado, cai no mail() como último recurso
    return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $hdr);
  }

  $host   = app_get('smtp_host','');
  $port   = (int)app_get('smtp_port','587');
  $secure = strtolower(app_get('smtp_secure','tls')); // tls|ssl|none
  $user   = app_get('smtp_user','');
  $pass   = app_get('smtp_pass','');

  $dbg=[]; $log=function($s) use(&$dbg){ $dbg[]=$s; }; $fail=function() use(&$dbg){ $GLOBALS['smtp_debug']=implode("\n",$dbg); return false; };

  if (!$host || !$port || !$user) { $log('Config SMTP incompleta'); return $fail(); }

  $remote = ($secure==='ssl' ? "ssl://{$host}" : $host);
  $log("Conectando em {$remote}:{$port}");
  $fp = @stream_socket_client($remote.':'.$port, $errno, $errstr, 15);
  if (!$fp) { $log("Falha conectar: $errno $errstr"); return $fail(); }
  stream_set_timeout($fp, 15);

  $read=function() use($fp,&$dbg){ $l=fgets($fp,2048); $dbg[]="S: ".rtrim((string)$l); return $l; };
  $cmd=function($l) use($fp,&$dbg){ $dbg[]="C: ".$l; fwrite($fp,$l."\r\n"); };

  $banner=$read(); if (!$banner || strpos($banner,'2')!==0){ $log('Sem banner 220'); return $fail(); }

  $cmd('EHLO localhost'); $read();
  if ($secure==='tls'){
    $cmd('STARTTLS'); $l=$read();
    if (strpos($l,'220')!==0){ $log('STARTTLS negado'); return $fail(); }
    if (!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){ $log('Falha no TLS'); return $fail(); }
    $cmd('EHLO localhost'); $read();
  }

  $cmd('AUTH LOGIN'); if (strpos($read(),'334')!==0){ $log('AUTH não aceito'); return $fail(); }
  $cmd(base64_encode($user));  if (strpos($read(),'334')!==0){ $log('USER rejeitado'); return $fail(); }
  $cmd(base64_encode($pass));  if (strpos($read(),'235')!==0){ $log('SENHA rejeitada'); return $fail(); }

  $cmd("MAIL FROM:<{$from}>"); if (strpos($read(),'250')!==0){ $log('MAIL FROM rejeitado'); return $fail(); }
  $cmd("RCPT TO:<{$to}>");     $r=$read(); if (strpos($r,'250')!==0 && strpos($r,'251')!==0){ $log('RCPT TO rejeitado'); return $fail(); }

  $cmd('DATA'); if (strpos($read(),'354')!==0){ $log('DATA rejeitado'); return $fail(); }

  $msg  = "From: ".mb_encode_mimeheader($fromN,'UTF-8')." <{$from}>\r\n";
  $msg .= "To: <{$to}>\r\n";
  $msg .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
  $msg .= "MIME-Version: 1.0\r\n";
  $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
  $msg .= $body . "\r\n";

  fwrite($fp, $msg . "\r\n.\r\n");
  if (strpos($read(),'250')!==0){ $log('Mensagem não aceita'); return $fail(); }

  $cmd('QUIT'); $read(); fclose($fp);
  return true;
}

