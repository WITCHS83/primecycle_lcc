<?php
// smtp_probe.php
$host   = $_GET['host']   ?? 'smtp.gmail.com';
$port   = (int)($_GET['port'] ?? 587);
$secure = $_GET['secure'] ?? 'tls'; // tls | ssl | none

echo "Testando {$host}:{$port} ({$secure})<br><pre>";

$transport = ($secure==='ssl') ? "ssl://{$host}" : $host;
$fp = @stream_socket_client("{$transport}:{$port}", $errno, $errstr, 20);
if (!$fp) { echo "Falha conectar: $errno $errstr\n"; exit; }
stream_set_timeout($fp, 20);

$read = function() use($fp){
  $out = '';
  // lê várias linhas, pois banner/ehlo podem vir multi-line
  for ($i=0; $i<10; $i++) {
    $line = fgets($fp, 2048);
    if ($line===false) break;
    $out .= "S: ".rtrim($line)."\n";
    // se a linha começa com código + espaço (ex.: "220 "), geralmente é a final
    if (preg_match('/^\d{3}\s/', $line)) break;
    // se não tiver mais bytes no buffer, sai
    if (!stream_get_meta_data($fp)['unread_bytes']) break;
  }
  return $out;
};

$cmd = function($line) use($fp){
  fwrite($fp, $line."\r\n");
  echo "C: {$line}\n";
};

echo $read();

// STARTTLS se for TLS
$cmd('EHLO localhost'); echo $read();

if ($secure==='tls') {
  $cmd('STARTTLS'); echo $read();
  if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    echo "Falha no TLS\n"; exit;
  }
  $cmd('EHLO localhost'); echo $read();
}

$cmd('QUIT'); echo $read();
fclose($fp);
echo "</pre>";
