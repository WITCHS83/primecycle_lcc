<?php
require_once __DIR__ . '/../config.php'; require_login();
header('Content-Type: application/json; charset=utf-8');

try {
  $docId = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
  $field = $_POST['field'] ?? '';
  $value = $_POST['value'] ?? '';

  if ($docId <= 0 || $field === '') { echo json_encode(['ok'=>false,'error'=>'Parâmetros inválidos.']); exit; }

  // Verifica permissão (admin/editor)
  $stmt = $pdo->prepare("SELECT du.role FROM doc_users du WHERE du.doc_id=? AND du.user_id=? LIMIT 1");
  $stmt->execute([$docId, current_user_id()]);
  $r = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$r || !in_array($r['role'], ['admin','editor'], true)) {
    echo json_encode(['ok'=>false,'error'=>'Sem permissão para editar.']); exit;
  }

  // Campos permitidos
  $allowed = [
    'title','nickname','fase','artefato','data','local',
    'versao','gerente','patrocinador','cliente'
  ];
  if (!in_array($field, $allowed, true)) {
    echo json_encode(['ok'=>false,'error'=>'Campo não permitido.']); exit;
  }

  // Normalizações simples
  if ($field === 'data') {
    // aceita dd/mm/yyyy também
    if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $value, $m)) {
      $value = $m[3].'-'.$m[2].'-'.$m[1];
    }
    if ($value !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $value)) {
      echo json_encode(['ok'=>false,'error'=>'Data inválida.']); exit;
    }
  }
  if ($field === 'fase' && !in_array($value, ['IN','PL','MO','EX','EN',''], true)) {
    echo json_encode(['ok'=>false,'error'=>'Fase inválida.']); exit;
  }
  if ($field === 'artefato' && !in_array($value, ['TAP','PGP','REP','TEP',''], true)) {
    echo json_encode(['ok'=>false,'error'=>'Artefato inválido.']); exit;
  }

  // Monta SQL
  $sql = "UPDATE documents SET `$field` = :v, updated_at = NOW() WHERE id = :id LIMIT 1";
  $upd = $pdo->prepare($sql);
  $upd->bindValue(':v', $value === '' ? null : $value, $value === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
  $upd->bindValue(':id', $docId, PDO::PARAM_INT);
  $upd->execute();

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'Erro interno']); // logue $e se quiser
}
