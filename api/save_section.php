<?php
require_once __DIR__ . '/../config.php'; require_login();
$data = json_decode(file_get_contents('php://input'), true);
$docId = (int)($data['docId'] ?? 0);
$slug  = $data['slug'] ?? '';
$content = $data['content'] ?? '';

// permission: editor/admin only
$stmt = $pdo->prepare("SELECT role FROM doc_users WHERE doc_id=? AND user_id=?");
$stmt->execute([$docId, current_user_id()]);
$r = $stmt->fetch();
if (!$r || !in_array($r['role'], ['admin','editor'])) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }

$upd = $pdo->prepare("UPDATE sections SET content=? WHERE doc_id=? AND slug=?");
$upd->execute([$content, $docId, $slug]);

$pdo->prepare("UPDATE documents SET updated_at=NOW() WHERE id=?")->execute([$docId]);

header('Content-Type: application/json');
echo json_encode(['ok'=>true]);
