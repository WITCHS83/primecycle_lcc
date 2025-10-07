<?php
require_once __DIR__ . '/config.php'; require_login();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$uid = (int)current_user_id();
$messages = []; $errors = [];

/* === CARREGA DOCS ONDE SOU ADMIN ======================================= */
try {
  $docsStmt = $pdo->prepare("
    SELECT d.id, d.title
    FROM documents d
    JOIN doc_users du ON du.doc_id = d.id
    WHERE du.user_id = ? AND du.role = 'admin'
    ORDER BY d.updated_at DESC, d.title
  ");
  $docsStmt->execute([$uid]);
  $docs = $docsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  http_response_code(500); die('Erro ao carregar documentos: ' . h($e->getMessage()));
}

/* === REMOÇÃO EM MASSA =================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sel']) && is_array($_POST['sel'])) {
  foreach ($_POST['sel'] as $docIdStr => $userIds) {
    $docId = (int)$docIdStr;
    if (!is_array($userIds) || !$userIds) continue;
    $userIds = array_map('intval', $userIds);

    $rel = $pdo->prepare("SELECT d.owner_id, du.role, d.title
                          FROM doc_users du JOIN documents d ON d.id=du.doc_id
                          WHERE du.doc_id=? AND du.user_id=?");
    $rel->execute([$docId, $uid]);
    $me = $rel->fetch(PDO::FETCH_ASSOC);
    if (!$me || $me['role'] !== 'admin') { $errors[] = "Documento #$docId: permissão negada."; continue; }
    $docTitle = $me['title'] ?? ("#$docId");
    $ownerId  = (int)$me['owner_id'];

    // Papéis dos selecionados
    $ph = implode(',', array_fill(0, count($userIds), '?'));
    $rolesStmt = $pdo->prepare("SELECT user_id, role FROM doc_users WHERE doc_id=? AND user_id IN ($ph)");
    $rolesStmt->execute(array_merge([$docId], $userIds));
    $toRemove = [];
    foreach ($rolesStmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $toRemove[(int)$r['user_id']] = $r['role']; }

    // Contagem de admins atuais
    $countAdminsStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM doc_users WHERE doc_id=? AND role='admin'");
    $countAdminsStmt->execute([$docId]); $adminsCount = (int)$countAdminsStmt->fetch()['c'];

    // Filtra owner e contabiliza admins selecionados
    $adminsToRemove = 0;
    foreach ($userIds as $rid) {
      if ($rid === $ownerId) { unset($toRemove[$rid]); continue; }
      if (($toRemove[$rid] ?? null) === 'admin') $adminsToRemove++;
    }

    if ($adminsToRemove >= $adminsCount) { $errors[] = "Documento \"".h($docTitle)."\": não remova todos os administradores."; continue; }
    if ($adminsCount - $adminsToRemove <= 0) { $errors[] = "Documento \"".h($docTitle)."\": deve permanecer ao menos 1 admin."; continue; }

    $finalIds = array_keys(array_filter($toRemove, fn($r)=>$r!==null));
    if ($finalIds) {
      $ph2 = implode(',', array_fill(0, count($finalIds), '?'));
      $del = $pdo->prepare("DELETE FROM doc_users WHERE doc_id=? AND user_id IN ($ph2)");
      $del->execute(array_merge([$docId], $finalIds));
      $messages[] = "Documento \"".h($docTitle)."\": removidos ".count($finalIds)." membro(s).";
    } else {
      $messages[] = "Documento \"".h($docTitle)."\": nenhum membro elegível.";
    }
  }
}

/* === LISTA MEMBROS POR DOC ============================================= */
$membersByDoc = [];
foreach ($docs as $d) {
  $mid = (int)$d['id'];
  $mem = $pdo->prepare("
    SELECT u.id, u.name, u.email, du.role,
           (u.id = (SELECT owner_id FROM documents WHERE id=?)) AS is_owner
    FROM doc_users du
    JOIN users u ON u.id = du.user_id
    WHERE du.doc_id = ?
    ORDER BY u.name
  ");
  $mem->execute([$mid, $mid]);
  $membersByDoc[$mid] = $mem->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin de Membros</title>

  <!-- BOOT DO TEMA (antes dos CSS) -->
  <script>
    (function(){
      try{
        var KEY='lifecycle.theme';
        var saved=localStorage.getItem(KEY);
        if(!saved){
          saved=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';
          localStorage.setItem(KEY,saved);
        }
        if(saved==='dark'){ document.documentElement.setAttribute('data-theme','dark'); }
      }catch(e){}
    })();
  </script>

  <!-- CSS base + paleta -->
  <link rel="stylesheet" href="/assets/style.css"/>
  <link rel="stylesheet" href="/assets/style.append.css"/>
  <link rel="stylesheet" href="/assets/lcboard.css"/>
  <link rel="stylesheet" href="/assets/modern.append.css"/>

  <style>
    /* ===== Layout para ficar idêntico ao dashboard ===== */
    .db-wrap{ max-width:1200px; margin:12px auto 24px; padding:0 10px; }

    .db-head.card{ padding:12px; }
    .db-head header{
      background:#765475; color:#fff; font-weight:700; font-size:13px;
      padding:6px 8px; border-radius:6px; margin-bottom:10px;
    }

    .db-actions{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

    .doc-grid{ display:grid; gap:14px; grid-template-columns: 1fr; }
    @media (min-width: 1024px){ .doc-grid{ grid-template-columns: 1fr 1fr; } }

    .doc-card{ border-radius:14px; overflow:hidden; border:1px solid rgba(0,0,0,.06); background:#fff; box-shadow:0 1px 0 rgba(0,0,0,.08); }
    .doc-head{
      background:#765475; color:#fff; padding:14px 16px;
      display:flex; justify-content:space-between; align-items:center; gap:10px;
    }
    .doc-body{ padding:12px 16px; }

    .mini-actions{ display:flex; align-items:center; gap:8px; }
    .btn.small{ padding:8px 14px; border-radius:8px; font-weight:700; }
    .btn.primary{ background:#765475; color:#fff; border:none; }
    .btn.primary:hover{ background:#5b3c5c; }
    .btn.ghost{ background:#fff; border:1px solid #ccc; color:#111827; }
    .btn.ghost:hover{ background:#f3f4f6; }
    .danger{ background:#b91c1c; color:#fff; border:0; border-radius:10px; padding:10px 14px; cursor:pointer; }

    /* Lista de membros */
    .member-row{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px solid #eef0f3 }
    .member-row:last-child{ border-bottom:0; }
    .member-row .info{ min-width:0; display:flex; gap:10px; align-items:center }
    .member-row .name{ font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
    .member-row .meta{ color:#6b7280; font-size:.92rem }

    /* ===== Topbar harmonizada com tema (igual dashboard) ===== */
    :root{ --topbar-bg:#ffffffcc; --topbar-text:#111827; --topbar-border:#e5e7eb; }
    :root[data-theme="dark"]{ --topbar-bg:#0f172acc; --topbar-text:#e5e7eb; --topbar-border:#1f2937; }
    .topbar{
      background:var(--topbar-bg) !important;
      color:var(--topbar-text) !important;
      border-bottom:1px solid var(--topbar-border) !important;
      backdrop-filter:saturate(140%) blur(6px);
    }
    .topbar a{ color:var(--topbar-text) !important; }
    .topbar .btn.ghost{
      background:transparent !important;
      color:var(--topbar-text) !important;
      border:1px solid var(--topbar-border) !important;
    }
    .topbar .btn.ghost:hover{ background:rgba(0,0,0,.05) !important; }
    :root[data-theme="dark"] .topbar .btn.ghost:hover{ background:rgba(255,255,255,.06) !important; }

    /* ===== Dark mode (mesma linguagem do dashboard) ===== */
    :root[data-theme="dark"] body{ background:#0b1020; }
    :root[data-theme="dark"] .card,
    :root[data-theme="dark"] .doc-card{ background:#0f172a; border:1px solid #1f2937; box-shadow:0 6px 20px rgba(0,0,0,.45); }
    :root[data-theme="dark"] .doc-head{ background:#6f5675; }
    :root[data-theme="dark"] .member-row{ border-bottom:1px solid #1f2937; }
    :root[data-theme="dark"] .member-row .meta{ color:#94a3b8; }
    :root[data-theme="dark"] .btn.primary{ background:#6f5675; color:#fff; }
    :root[data-theme="dark"] .btn.primary:hover{ background:#5a445f; }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/topbar.inc.php'; ?>

<main class="db-wrap">

  <!-- Cabeçalho / ações -->
  <section class="card db-head">
    <header>Administração</header>
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h2 style="margin:0">Remover usuários em massa</h2>
        <div class="muted">Gerencie membros com a mesma experiência do painel</div>
      </div>
      <div class="db-actions">
        <button id="themeToggle" class="btn ghost" type="button">🌙 Dark</button>
        <a class="btn ghost" href="/dashboard.php">Voltar ao Painel</a>
      </div>
    </div>
  </section>

  <?php foreach ($messages as $m): ?><div class="alert ok"><?= $m ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert err"><?= $e ?></div><?php endforeach; ?>

  <!-- Cards dos documentos (estilo idêntico ao dashboard) -->
  <section style="margin-top:14px">
    <?php if (!$docs): ?>
      <p class="muted">Você não é administrador de nenhum documento.</p>
    <?php else: ?>
      <form method="post">
        <div class="doc-grid">
          <?php foreach ($docs as $d): ?>
            <?php
              $did=(int)$d['id'];
              $members=$membersByDoc[$did] ?? [];
            ?>
            <article class="doc-card">
              <div class="doc-head">
                <div style="font-weight:800; font-size:18px"><?= h($d['title']) ?> <span class="muted" style="font-weight:400">#<?= $did ?></span></div>
                <div class="mini-actions">
                  <label style="display:flex;align-items:center;gap:8px;color:#fff">
                    <input type="checkbox" onclick="document.querySelectorAll('[data-doc=doc<?= $did ?>]').forEach(cb=>cb.checked=this.checked)">
                    Selecionar todos
                  </label>
                </div>
              </div>

              <div class="doc-body">
                <?php if(!$members): ?>
                  <p class="muted">Sem membros.</p>
                <?php else: ?>
                  <?php foreach ($members as $m): ?>
                    <div class="member-row">
                      <label class="info">
                        <input type="checkbox" name="sel[<?= $did ?>][]" value="<?= (int)$m['id'] ?>" data-doc="doc<?= $did ?>" <?php if ($m['is_owner']) echo 'disabled'; ?>>
                        <div style="min-width:0">
                          <div class="name"><?= h($m['name'] ?: $m['email']) ?></div>
                          <div class="meta"><?= h($m['email']) ?> • <?= h($m['role']) ?><?php if ($m['is_owner']) echo ' • Proprietário'; ?></div>
                        </div>
                      </label>
                    </div>
                  <?php endforeach; ?>

                  <div style="display:flex;justify-content:flex-end;margin-top:12px">
                    <button class="danger">Remover selecionados</button>
                  </div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </form>
    <?php endif; ?>
  </section>
</main>

<!-- Toggle de tema (mesmo do dashboard) -->
<script>
(function(){
  const KEY='lifecycle.theme';
  const root=document.documentElement;
  const btn=document.getElementById('themeToggle');
  function paint(){ if(btn) btn.textContent = root.getAttribute('data-theme')==='dark' ? '☀️ Light' : '🌙 Dark'; }
  paint();
  if(btn){
    btn.addEventListener('click',()=>{
      const dark = root.getAttribute('data-theme')==='dark';
      if(dark){ root.removeAttribute('data-theme'); localStorage.setItem(KEY,'light'); }
      else { root.setAttribute('data-theme','dark'); localStorage.setItem(KEY,'dark'); }
      paint();
    });
  }
})();
</script>
</body>
</html>
