<?php
// /api/document.php
declare(strict_types=1);
require_once __DIR__ . '/../config.php'; require_login();
header('Content-Type: application/json; charset=utf-8');

$docId = (int)($_POST['doc_id'] ?? 0);
$st = $pdo->prepare("SELECT du.role FROM doc_users du WHERE du.doc_id = ? AND du.user_id = ? LIMIT 1");
$st->execute([$docId, current_user_id()]);
$role = $st->fetchColumn();
if (!$role || !in_array($role, ['admin','editor'], true)) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }

$cols = ['igp','fase','data','local','versao','gerente','patrocinador','cliente'];
$set = []; $vals = [];
foreach ($cols as $c) {
  if (array_key_exists($c, $_POST)) { $set[] = "$c = ?"; $vals[] = $_POST[$c]; }
}
if (!$set) { echo json_encode(['ok'=>true]); exit; }
$vals[] = $docId;

$pdo->prepare("UPDATE documents SET ".implode(',', $set).", updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute($vals);
echo json_encode(['ok'=>true]);
