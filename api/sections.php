<?php
// /api/sections.php
declare(strict_types=1);
require_once __DIR__ . '/../config.php'; require_login();

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$docId  = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : (isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0);
$slug   = $_GET['slug'] ?? ($_POST['slug'] ?? '');
$slug   = preg_replace('/[^a-z0-9_-]/i', '', (string)$slug);

/* 1) Checar acesso do usuário ao documento */
$st = $pdo->prepare("SELECT du.role FROM doc_users du WHERE du.doc_id = ? AND du.user_id = ? LIMIT 1");
$st->execute([$docId, current_user_id()]);
$role = $st->fetchColumn();
if (!$role) {
  http_response_code(403);
  echo json_encode(['ok'=>false, 'error'=>'sem_acesso']); exit;
}

if ($method === 'GET') {
  if (!$docId || !$slug) { echo json_encode(['ok'=>false, 'error'=>'params']); exit; }
  $s = $pdo->prepare("SELECT title, content FROM sections WHERE doc_id = ? AND slug = ? LIMIT 1");
  $s->execute([$docId, $slug]);
  $row = $s->fetch(PDO::FETCH_ASSOC);
  echo json_encode($row ?: ['title'=>'', 'content'=>'']); exit;
}

if ($method === 'POST') {
  if (!in_array($role, ['admin','editor'], true)) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'somente_leitura']); exit;
  }
  $title = trim((string)($_POST['title'] ?? ''));
  $content = (string)($_POST['content'] ?? '');
  if (!$docId || !$slug) { echo json_encode(['ok'=>false, 'error'=>'params']); exit; }

  // UPSERT (único por doc_id + slug) – conforme índice uniq_section
  $pdo->beginTransaction();
  try {
    $sel = $pdo->prepare("SELECT id FROM sections WHERE doc_id = ? AND slug = ? LIMIT 1");
    $sel->execute([$docId, $slug]);
    $id = (int)($sel->fetchColumn() ?: 0);

    if ($id) {
      $up = $pdo->prepare("UPDATE sections SET title = ?, content = ? WHERE id = ?");
      $up->execute([$title ?: $slug, $content, $id]);
    } else {
      $ins = $pdo->prepare("INSERT INTO sections (doc_id, slug, title, content, position) VALUES (?,?,?,?,0)");
      $ins->execute([$docId, $slug, $title ?: $slug, $content]);
    }
    // toque no updated_at do documento
    $pdo->prepare("UPDATE documents SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$docId]);
    $pdo->commit();
    echo json_encode(['ok'=>true]);
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'db', 'detail'=>$e->getMessage()]);
  }
  exit;
}

http_response_code(405);
echo json_encode(['ok'=>false, 'error'=>'method_not_allowed']);
