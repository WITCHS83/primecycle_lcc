<?php
require_once __DIR__ . '/config.php'; require_login();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

/* CREATE */
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
  $title = trim($_POST['title'] ?? '');
  $nickname = trim($_POST['nickname'] ?? '');
  if ($title === '') {
    $err = 'Informe um título.';
  } else {
    try {
      $stmt = $pdo->prepare("INSERT INTO documents (title, nickname, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
      $stmt->execute([$title, $nickname !== '' ? $nickname : null]);
      $newId = (int)$pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO doc_users (doc_id, user_id, role) VALUES (?, ?, 'admin')
                             ON DUPLICATE KEY UPDATE role = VALUES(role)");
      $stmt->execute([$newId, current_user_id()]);

      header("Location: /canvas.php?id=" . $newId);
      exit;
    } catch (Throwable $e) {
      $err = 'Falha ao criar documento.';
    }
  }
}

/* LIST */
$stmt = $pdo->prepare("
  SELECT d.*, du.role
  FROM documents d
  JOIN doc_users du ON du.doc_id = d.id
  WHERE du.user_id = ?
  ORDER BY d.updated_at DESC, d.title
");
$stmt->execute([current_user_id()]);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Lifecycle Canvas — Painel</title>

  <!-- THEME BOOT -->
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

  <!-- CSS -->
  <link rel="stylesheet" href="/assets/style.css"/>
  <link rel="stylesheet" href="/assets/style.append.css"/>
  <link rel="stylesheet" href="/assets/lcboard.css"/>
  <link rel="stylesheet" href="/assets/modern.append.css"/>

  <style>
    /* ===== Topbar Dark Mode (cabeçalho global) ===== */
    /* Garanta que o HTML do topo tenha class="topbar" (ver opção 2 abaixo) */
    .topbar { background:#e5e7eb; border-bottom:1px solid rgba(0,0,0,.06); }
    .topbar a { color:#1d4ed8; }
    .topbar strong { color:#0f172a; }
    :root[data-theme="dark"] .topbar{
      background:#111827 !important;
      border-bottom:1px solid #1f2937 !important;
    }
    :root[data-theme="dark"] .topbar a{ color:#e5e7eb !important; }
    :root[data-theme="dark"] .topbar a:hover{ color:#facc15 !important; }
    :root[data-theme="dark"] .topbar strong{ color:#f3f4f6 !important; }

    /* ===== Dashboard ===== */
    .db-wrap{ max-width:1200px; margin:12px auto 24px; padding:0 10px; }
    .db-head.card{ padding:12px; }
    .db-head header{
      background:#765475; color:#fff; font-weight:700; font-size:13px;
      padding:6px 8px; border-radius:6px; margin-bottom:10px;
    }
    .db-actions{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .db-form{ display:grid; grid-template-columns: 1fr 1fr auto; gap:10px; align-items:end; }
    .db-form label{ display:block; font-size:12px; color:#3a3a3a; }
    .db-form input{
      width:100%; height:36px; border:1px solid rgba(0,0,0,.08);
      border-radius:8px; padding:0 10px; background:#fff;
    }
    .db-grid{ display:grid; gap:14px; grid-template-columns: repeat(2, 1fr); }
    @media (max-width:1024px){ .db-grid{ grid-template-columns: 1fr; } .db-form{ grid-template-columns: 1fr; } }

    .doc-card{ background:#fff; border-radius:14px; box-shadow:0 1px 0 rgba(0,0,0,.08); overflow:hidden; border:1px solid rgba(0,0,0,.06); }
    .doc-head{
      background:#765475; color:#fff; padding:14px 16px;
      display:flex; justify-content:space-between; align-items:center; gap:10px;
    }
    .doc-head .actions{ display:flex; gap:8px; align-items:center; }
    .doc-body{ padding:14px 16px; color:#333; }
    .doc-meta{ font-size:14px; color:#6b7280; }
    .btn.small{ padding:8px 14px; border-radius:8px; font-weight:700; }
    .btn.primary{ background:#765475; color:#fff; border:none; }
    .btn.primary:hover{ background:#5b3c5c; }
    .btn.ghost{ background:#fff; border:1px solid #ccc; color:#111827; }
    .btn.ghost:hover{ background:#f3f4f6; }

    /* ===== Dark ===== */
    :root[data-theme="dark"] body{ background:#0b1020; }
    :root[data-theme="dark"] .card,
    :root[data-theme="dark"] .doc-card{ background:#0f172a; border:1px solid #1f2937; box-shadow:0 6px 20px rgba(0,0,0,.45); }
    :root[data-theme="dark"] .db-form input{ background:#0f172a; color:#e5e7eb; border-color:#1f2937; }
    :root[data-theme="dark"] .doc-body{ color:#e5e7eb; }
    :root[data-theme="dark"] .doc-meta{ color:#94a3b8; }
    :root[data-theme="dark"] .doc-head{ background:#6f5675; }
    :root[data-theme="dark"] .btn.primary{ background:#6f5675; color:#fff; }
    :root[data-theme="dark"] .btn.primary:hover{ background:#5a445f; }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/topbar.inc.php'; ?>

<main class="db-wrap">

  <!-- Cabeçalho -->
  <section class="card db-head">
    <header>Painel</header>
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h2 style="margin:0">Meus Documentos</h2>
        <div class="muted">Organize, compartilhe e crie novos canvases</div>
      </div>
      <div class="db-actions">
  <button id="themeToggle" class="btn ghost" type="button">🌙 Dark</button>
  <a class="btn ghost" href="/admin_members.php">Admin de Membros</a>
  <?php if (is_site_admin()): ?>
    <a class="btn ghost" href="/settings.php">Configurações</a>
  <?php endif; ?>
</div>
    </div>
  </section>

  <!-- Criar novo -->
  <section class="card db-head" style="margin-top:10px">
    <header>Novo documento</header>
    <?php if ($err): ?><div class="doc-meta" style="color:#b91c1c;margin-bottom:8px"><?= h($err) ?></div><?php endif; ?>
    <form method="post" class="db-form">
      <input type="hidden" name="create" value="1"/>
      <label>Título
        <input name="title" required placeholder="Ex.: Projeto Atlas">
      </label>
      <label>Nickname
        <input name="nickname" placeholder="Ex.: atlas_v1">
      </label>
      <button class="btn primary" type="submit">Criar</button>
    </form>
  </section>

  <!-- Listagem -->
  <section style="margin-top:14px">
    <?php if (!$docs): ?>
      <p class="muted">Nenhum documento ainda.</p>
    <?php else: ?>
      <div class="db-grid">
        <?php foreach ($docs as $d): ?>
          <?php $rid = (int)$d['id']; $role = $d['role']; $canShare = in_array($role, ['admin','editor'], true); ?>
          <article class="doc-card">
            <div class="doc-head">
              <div style="font-weight:800; font-size:20px"><?= h($d['title']) ?></div>
              <div class="actions">
                <?php if ($canShare): ?>
                  <a class="btn primary small" href="/share.php?id=<?= $rid ?>">Compartilhar</a>
                <?php endif; ?>
                <a class="btn primary small" href="/canvas.php?id=<?= $rid ?>">Abrir →</a>
              </div>
            </div>
            <div class="doc-body">
              <div class="doc-meta">Nickname: <?= h($d['nickname'] ?: '—') ?></div>
              <div class="doc-meta">Papel: <?= h($role) ?> • Atualizado em <?= h($d['updated_at']) ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<!-- Toggle de tema -->
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
