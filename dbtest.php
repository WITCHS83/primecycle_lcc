<?php
error_reporting(E_ALL); ini_set('display_errors', 1);

$DB_HOST='sql313.infinityfree.com';  // do seu painel
$DB_PORT=3306;
$DB_NAME='if0_40078277_lifecyclecanvas';
$DB_USER='if0_40078277';
$DB_PASS='nlun81OuNoLMQUU';
$DB_CHARSET='utf8mb4';

try {
  $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=$DB_CHARSET";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
  echo "OK conectado<br>";
  foreach ($pdo->query("SHOW TABLES") as $r) { var_dump($r); }
} catch (Throwable $e) {
  echo "ERRO: ".$e->getMessage();
}
