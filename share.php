<?php
require_once __DIR__ . '/config.php';
require_login();

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$docId = (int)($_GET['id'] ?? 0);

/* ===== Permissão: apenas admin do doc ===== */
$relStmt = $pdo->prepare("
  SELECT d.id, d.title, d.owner_id, du.role
  FROM doc_users du
  JOIN documents d ON d.id = du.doc_id
  WHERE du.doc_id = ? AND du.user_id = ?
  LIMIT 1
");
$relStmt->execute([$docId, current_user_id()]);
$rel = $relStmt->fetch(PDO::FETCH_ASSOC);
if (!$rel || $rel['role'] !== 'admin') {
  http_response_code(403);
  die('Apenas administradores podem compartilhar.');
}

/* ===== Helpers ===== */
function base_url(){
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

/* ===== Ações ===== */
$msgOk = ''; $msgErr = ''; $generatedLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'invite') {
      $email = trim($_POST['email'] ?? '');
      $role  = $_POST['role'] ?? 'editor';
      if (!in_array($role, ['viewer','editor','admin'], true)) $role = 'editor';

      if ($email === '') {
        $msgErr = 'Informe um e-mail válido.';
      } else {
        // Se usuário já existe, concede acesso direto (upsert)
        $u = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $u->execute([$email]);
        $user = $u->fetch(PDO::FETCH_ASSOC);

        if ($user) {
          $uid = (int)$user['id'];
          $up = $pdo->prepare("
            INSERT INTO doc_users (doc_id, user_id, role)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE role = VALUES(role)
          ");
          $up->execute([$docId, $uid, $role]);

          $msgOk = "Acesso concedido para <strong>".h($email)."</strong> como <strong>".h($role)."</strong>.";
        } else {
          // Usuário não existe: cria convite
          $token = bin2hex(random_bytes(16));
          $ins = $pdo->prepare("
            INSERT INTO invitations (doc_id, email, role, token, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
          ");
          $ins->execute([$docId, $email, $role, $token]);

          $generatedLink = base_url() . "/accept.php?token=" . $token;
          $msgOk = "Convite criado para <strong>".h($email)."</strong>. Copie o link abaixo e envie ao convidado.";
        }
      }
    }

    if ($action === 'revoke' && isset($_POST['invite_id'])) {
      $iid = (int)$_POST['invite_id'];
      $pdo->prepare("UPDATE invitations SET status='revoked' WHERE id=? AND doc_id=?")->execute([$iid,$docId]);
      $msgOk = "Convite revogado.";
    }

    if ($action === 'remove_member' && isset($_POST['user_id'])) {
      $uid = (int)$_POST['user_id'];

      if ($uid === (int)$rel['owner_id']) {
        $msgErr = "Não é possível remover o proprietário do documento.";
      } else {
        $roleStmt = $pdo->prepare("SELECT role FROM doc_users WHERE doc_id=? AND user_id=?");
        $roleStmt->execute([$docId,$uid]);
        $r = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$r) {
          $msgErr = "Membro não encontrado.";
        } else {
          $targetRole = $r['role'];
          $c = $pdo->prepare("SELECT COUNT(*) AS c FROM doc_users WHERE doc_id=? AND role='admin'");
          $c->execute([$docId]); $admins = (int)$c->fetch()['c'];

          if ($targetRole === 'admin' && $admins <= 1) {
            $msgErr = "Ação bloqueada: não é possível remover o último administrador.";
          } else {
            $pdo->prepare("DELETE FROM doc_users WHERE doc_id=? AND user_id=?")->execute([$docId,$uid]);
            $msgOk = "Membro removido.";
          }
        }
      }
    }

    if ($action === 'bulk_remove' && isset($_POST['sel']) && is_array($_POST['sel'])) {
      $sel = array_map('intval', $_POST['sel']);
      if ($sel) {
        $ownerId = (int)$rel['owner_id'];

        // Papéis dos selecionados
        $in  = implode(',', array_fill(0, count($sel), '?'));
        $q   = $pdo->prepare("SELECT user_id, role FROM doc_users WHERE doc_id=? AND user_id IN ($in)");
        $q->execute(array_merge([$docId], $sel));
        $toRemove = $q->fetchAll(PDO::FETCH_KEY_PAIR);

        // Contagem admins
        $c = $pdo->prepare("SELECT COUNT(*) AS c FROM doc_users WHERE doc_id=? AND role='admin'");
        $c->execute([$docId]); $adminsCount = (int)$c->fetch()['c'];

        $adminsToRemove = 0;
        foreach ($sel as $rid) {
          if ($rid === $ownerId) { unset($toRemove[$rid]); continue; }
          if (($toRemove[$rid] ?? null) === 'admin') $adminsToRemove++;
        }

        if ($adminsToRemove >= $adminsCount || $adminsCount - $adminsToRemove <= 0) {
          $msgErr = "Ação bloqueada: deve permanecer ao menos 1 administrador.";
        } else {
          $final = array_keys(array_filter($toRemove, fn($r)=>$r!==null));
          if ($final) {
            $in2 = implode(',', array_fill(0, count($final), '?'));
            $del = $pdo->prepare("DELETE FROM doc_users WHERE doc_id=? AND user_id IN ($in2)");
            $del->execute(array_merge([$docId], $final));
            $msgOk = "Removidos ".count($final)." membro(s).";
          } else {
            $msgErr = "Nenhum membro elegível para remoção.";
          }
        }
      }
    }
  } catch (Throwable $e) {
    $msgErr = 'Erro: ' . h($e->getMessage());
  }
}

/* ===== Listas ===== */
$inv = $pdo->prepare("SELECT * FROM invitations WHERE doc_id=? ORDER BY created_at DESC");
$inv->execute([$docId]);
$invites = $inv->fetchAll(PDO::FETCH_ASSOC);

$mem = $pdo->prepare("
  SELECT u.id, u.name, u.email, du.role
  FROM doc_users du
  JOIN users u ON u.id = du.user_id
  WHERE du.doc_id=? ORDER BY u.name
");
$mem->execute([$docId]);
$members = $mem->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Compartilhar — <?= h($rel['title']) ?></title>

  <!-- Tema antes dos CSS -->
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
    /* ===== Layout ===== */
    .wrap{ max-width:1200px; margin:16px auto 32px; padding:0 12px; }
    .top-strip{
      display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;
      gap:10px; margin-bottom:12px;
    }
    .pillbar{ display:flex; gap:10px; }
    .grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap:16px; align-items:start; }
    @media (max-width: 1000px){ .grid-2{ grid-template-columns: 1fr; } }

    .card header.bar{
      background:#765475; color:#fff; font-weight:800; font-size:18px;
      padding:10px 12px; border-radius:12px; margin-bottom:12px;
    }

    .form-row{ display:grid; grid-template-columns: 1fr 220px auto; gap:10px; align-items:end; }
    @media (max-width:720px){ .form-row{ grid-template-columns: 1fr; } }

    .btn.pill{ border-radius:999px; font-weight:800; padding:10px 16px; }
    .btn.primary{ background:#765475; color:#fff; border:none; }
    .btn.primary:hover{ background:#5b3c5c; }

    .member-head{ display:grid; grid-template-columns: auto 1fr auto; gap:12px; align-items:center; }
    .member-row{
      display:grid; grid-template-columns: auto 1fr auto; gap:12px; align-items:center;
      padding:12px 0; border-bottom:1px solid #eef0f3;
    }

    .invite-link{
      margin-top:10px; padding:10px; border-radius:10px; background:#f3f4f6; border:1px dashed #cbd5e1;
      word-break:break-all;
    }

    /* ===== Alerts no mesmo padrão ===== */
    .alert{ border-radius:12px; padding:12px 14px; margin:10px 0; font-weight:600; }
    .alert.ok{ background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .alert.err{ background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

    /* ===== Dark ===== */
    :root[data-theme="dark"] body{ background:#0b1020 !important; color:#e5e7eb !important; }
    :root[data-theme="dark"] .topbar{ background:#111827 !important; border-bottom:1px solid #1f2937 !important; }
    :root[data-theme="dark"] .topbar a{ color:#e5e7eb !important; }
    :root[data-theme="dark"] .topbar a:hover{ color:#facc15 !important; }
    :root[data-theme="dark"] .card{ background:#0f172a; border:1px solid #1f2937; box-shadow:0 6px 20px rgba(0,0,0,.45); }
    :root[data-theme="dark"] .card header.bar{ background:#6f5675; }
    :root[data-theme="dark"] input, :root[data-theme="dark"] select, :root[data-theme="dark"] textarea{
      background:#0f172a; color:#e5e7eb; border-color:#1f2937;
    }
    :root[data-theme="dark"] .invite-link{ background:#0b1020; border-color:#374151; color:#e5e7eb; }
    :root[data-theme="dark"] .member-row{ border-bottom:1px solid #1f2937; }
    :root[data-theme="dark"] .alert.ok{ background:#064e3b; color:#d1fae5; border-color:#065f46; }
    :root[data-theme="dark"] .alert.err{ background:#7f1d1d; color:#fee2e2; border-color:#991b1b; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/topbar.inc.php'; ?>

<main class="wrap">
  <!-- Cabeçalho da página -->
  <section class="card" style="padding:14px;">
    <div class="top-strip">
      <h1 style="margin:0">Compartilhar — <?= h($rel['title']) ?></h1>
      <div class="pillbar">
        <button id="themeToggle" class="btn ghost pill" type="button">🌙 Dark</button>
        <a class="btn ghost pill" href="/dashboard.php">← Voltar</a>
      </div>
    </div>

    <?php if ($msgOk): ?><div class="alert ok"><?= $msgOk ?></div><?php endif; ?>
    <?php if ($msgErr): ?><div class="alert err"><?= $msgErr ?></div><?php endif; ?>
  </section>

  <section class="grid-2">
    <!-- Convidar -->
    <div class="card" style="padding:14px;">
      <header class="bar">Convidar por e-mail</header>

      <form class="form-row" method="post" action="/share.php?id=<?= (int)$docId ?>">
        <input type="hidden" name="action" value="invite"/>
        <label>E-mail
          <input type="email" name="email" required placeholder="ex.: pessoa@empresa.com">
        </label>
        <label>Papel
          <select name="role">
            <option value="editor">Editor</option>
            <option value="viewer">Leitor</option>
            <option value="admin">Administrador</option>
          </select>
        </label>
        <button class="btn primary pill">Gerar link</button>
      </form>

      <?php if ($generatedLink): ?>
        <div class="invite-link">
          <strong>Link do convite:</strong><br>
          <?= h($generatedLink) ?>
        </div>
      <?php endif; ?>

      <p class="muted" style="margin-top:10px">
        Se o e-mail já tiver conta no sistema, o acesso é concedido imediatamente.
        Caso contrário, o link acima permite que a pessoa aceite o convite.
      </p>
    </div>

    <!-- Membros -->
    <div class="card" style="padding:14px;">
      <header class="bar">Membros</header>

      <?php if (!$members): ?>
        <p class="muted">Sem membros.</p>
      <?php else: ?>

        <!-- Form de remoção em massa -->
        <form method="post" action="/share.php?id=<?= (int)$docId ?>">
          <input type="hidden" name="action" value="bulk_remove"/>

          <div class="member-head" style="margin-bottom:8px">
            <div></div>
            <div class="muted"></div>
            <label style="display:flex;align-items:center;gap:8px">
              <input type="checkbox" onclick="document.querySelectorAll('.mcheck').forEach(cb=>cb.checked=this.checked)">
              <span>Selecionar todos</span>
            </label>
          </div>

          <?php foreach ($members as $m): ?>
            <div class="member-row">
              <label style="display:flex;align-items:center;gap:10px">
                <input class="mcheck" type="checkbox" name="sel[]" value="<?= (int)$m['id'] ?>">
                <div>
                  <div style="font-weight:800"><?= h($m['name'] ?: $m['email']) ?></div>
                  <div class="muted"><?= h($m['email']) ?> • <?= h($m['role']) ?></div>
                </div>
              </label>

              <div></div>

              <!-- Remover individual (sem form aninhado) -->
              <button type="button" class="danger pill"
                      onclick="removeMember(<?= (int)$m['id'] ?>)">Remover</button>
            </div>
          <?php endforeach; ?>

          <div class="right" style="margin-top:12px">
            <button class="danger pill"
                    onclick="return confirm('Remover membros selecionados?')">Remover selecionados</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>

  <!-- Convites -->
  <section class="card" style="padding:14px; margin-top:16px;">
    <header class="bar">Convites</header>

    <?php if (!$invites): ?>
      <p class="muted">Sem convites.</p>
    <?php else: ?>
      <ul class="doc-list">
        <?php foreach ($invites as $i): ?>
          <li>
            <div class="doc">
              <div class="doc-title" style="font-weight:800"><?= h($i['email']) ?></div>
              <div class="doc-meta"><?= h($i['role']) ?> • <?= h($i['status']) ?><?= isset($i['created_at']) ? ' • Criado em '.h($i['created_at']) : '' ?></div>

              <?php if ($i['status'] === 'pending'): ?>
                <div class="invite-link" style="margin-top:8px">
                  <?= h(base_url().'/accept.php?token='.$i['token']) ?>
                </div>
                <form method="post" action="/share.php?id=<?= (int)$docId ?>" style="margin-top:8px"
                      onsubmit="return confirm('Revogar este convite?');">
                  <input type="hidden" name="action" value="revoke"/>
                  <input type="hidden" name="invite_id" value="<?= (int)$i['id'] ?>"/>
                  <button class="danger pill">Revogar</button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>

<script>
/* Toggle tema (mesmo padrão) */
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

/* Remover individual: cria e envia form dinâmico (evita forms aninhados) */
function removeMember(userId){
  if(!confirm('Remover este membro?')) return;
  const f=document.createElement('form');
  f.method='POST';
  f.action=location.pathname + location.search;
  f.innerHTML =
    '<input type="hidden" name="action" value="remove_member">' +
    '<input type="hidden" name="user_id" value="'+String(userId)+'">';
  document.body.appendChild(f);
  f.submit();
}
</script>
</body>
</html>
