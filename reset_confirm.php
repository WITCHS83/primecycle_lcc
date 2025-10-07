<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$token = $_GET['token'] ?? '';
$err = ''; $ok = '';

function find_token($pdo, $token){
  if ($token === '') return null;
  $hash = hash('sha256', $token);
  $st = $pdo->prepare("SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE pr.token_hash=? LIMIT 1");
  $st->execute([$hash]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return null;
  if ($row['used_at'] !== null) return null;
  if (strtotime($row['expires_at']) < time()) return null;
  return $row;
}
$row = find_token($pdo, $token);
if (!$row) { $err='Link inválido ou expirado.'; }
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Definir nova senha — Lifecycle Canvas</title>

<script>
(function(){try{
  var KEY='lifecycle.theme', s=localStorage.getItem(KEY);
  if(!s){ s=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light'; localStorage.setItem(KEY,s); }
  if(s==='dark') document.documentElement.setAttribute('data-theme','dark');
}catch(e){}})();
</script>

<link rel="stylesheet" href="/assets/style.css"/>
<link rel="stylesheet" href="/assets/style.append.css"/>
<link rel="stylesheet" href="/assets/lcboard.css"/>
<link rel="stylesheet" href="/assets/modern.append.css"/>

<style>
  body.auth-body{min-height:100svh;display:grid;place-items:center;background:#d9bc9e radial-gradient(1200px 600px at 50% 0, rgba(255,255,255,.35), transparent) no-repeat;margin:0;}
  .auth-wrap{width:min(760px,92vw);}
  .auth-card{background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.15);padding:18px;}
  .auth-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
  .auth-title{margin:0;font-size:28px;line-height:1.2}
  .auth-title strong{font-weight:900}
  .brand{color:#2563eb;font-weight:900}
  .pillbar{display:flex;gap:10px}
  .btn.pill{border-radius:999px;padding:10px 16px;font-weight:800}
  .btn.primary{background:#2563eb;color:#fff;border:none}
  .btn.primary:hover{filter:brightness(.95)}
  .btn.ghost{background:#fff;border:1px solid #e5e7eb;color:#1f2937;border-radius:999px;padding:12px 18px;display:inline-flex;gap:8px}
  .btn.ghost:hover{background:#f8fafc}
  :root[data-theme="dark"] body.auth-body{background:#0b1020 radial-gradient(1200px 600px at 50% -20%, rgba(88,28,135,.25), transparent) no-repeat;}
  :root[data-theme="dark"] .auth-card{background:#0f172a;color:#e5e7eb;border:1px solid #1f2937;box-shadow:0 20px 60px rgba(0,0,0,.45)}
  :root[data-theme="dark"] .btn.ghost{background:transparent;border-color:#334155;color:#e5e7eb}
  :root[data-theme="dark"] .btn.ghost:hover{background:rgba(255,255,255,.06)}
  :root[data-theme="dark"] .muted{color:#9ca3af}
</style>
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <section class="auth-card">
      <div class="auth-head">
        <h1 class="auth-title"><strong>Definir</strong> <span class="brand">nova senha</span></h1>
        <div class="pillbar">
          <button id="themeToggle" class="btn ghost pill" type="button">🌙 Dark</button>
        </div>
      </div>

      <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <?php if (!$err): ?>
        <p class="muted" style="margin-top:-4px">E-mail: <strong><?= htmlspecialchars($row['email']) ?></strong></p>
        <form class="form" method="post" action="/reset_update.php">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <label>Nova senha
            <input type="password" name="password" required minlength="6" placeholder="••••••••">
          </label>
          <label>Confirmar nova senha
            <input type="password" name="password2" required minlength="6" placeholder="••••••••">
          </label>
          <div class="right">
            <button class="btn primary pill">Salvar senha</button>
          </div>
        </form>
        <hr style="border:0;border-top:1px solid #eee;margin:16px 0">
        <div class="grid-2">
          <a class="btn ghost" href="/index.php">← Voltar</a>
          <a class="btn ghost" href="/reset.php">Solicitar outro link</a>
        </div>
      <?php endif; ?>
    </section>
  </div>

<script>
(function(){
  const KEY='lifecycle.theme', root=document.documentElement, b=document.getElementById('themeToggle');
  function paint(){ if(b) b.textContent=root.getAttribute('data-theme')==='dark'?'☀️ Light':'🌙 Dark'; }
  paint(); if(b){ b.addEventListener('click',()=>{const d=root.getAttribute('data-theme')==='dark';
    if(d){root.removeAttribute('data-theme');localStorage.setItem(KEY,'light');}
    else{root.setAttribute('data-theme','dark');localStorage.setItem(KEY,'dark');}
    paint();
  });}
})();
</script>
</body>
</html>
