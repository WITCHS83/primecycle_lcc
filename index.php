<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (is_logged_in()) { header('Location: /dashboard.php'); exit; }

$err = $_GET['err'] ?? '';
$ok  = $_GET['ok'] ?? '';
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Entrar — Lifecycle Canvas</title>

  <!-- BOOT DO TEMA (aplica dark/light antes dos CSS) -->
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

  <!-- CSS base + paleta/modern do restante do app -->
  <link rel="stylesheet" href="/assets/style.css"/>
  <link rel="stylesheet" href="/assets/style.append.css"/>
  <link rel="stylesheet" href="/assets/lcboard.css"/>
  <link rel="stylesheet" href="/assets/modern.append.css"/>

  <style>
    /* ===== Layout geral da tela de autenticação ===== */
    body.auth-body{
      min-height:100svh;
      display:grid;
      place-items:center;
      /* mesmo fundo “areia” do canvas no claro */
      background:#d9bc9e radial-gradient(1200px 600px at 50% 0, rgba(255,255,255,.35), transparent) no-repeat;
      margin:0;
    }
    .auth-wrap{
      width:min(760px, 92vw);
    }
    .auth-card{
      background:#fff;
      border:1px solid rgba(0,0,0,.06);
      border-radius:16px;
      box-shadow:0 20px 60px rgba(0,0,0,.15);
      padding:18px;
    }
    .auth-head{
      display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;
      margin-bottom:10px;
    }
    .auth-title{ margin:0; font-size:28px; line-height:1.2 }
    .auth-title strong{ font-weight:900 }
    .auth-title .brand{ color:#2563eb; font-weight:900 }

    .pillbar{ display:flex; gap:10px; }
    .btn.pill{ border-radius:999px; padding:10px 16px; font-weight:800; }
    .btn.primary{ background:#2563eb; color:#fff; border:none; }
    .btn.primary:hover{ filter:brightness(.95); }

    .auth-form{ margin-top:6px }
    .auth-form label{ display:block; font-size:14px; margin:10px 0 6px; color:#111827 }
    .auth-form input{
      width:100%; height:44px; border-radius:12px; border:1px solid rgba(0,0,0,.12);
      padding:0 12px; background:#fff; outline:none;
    }
    .auth-actions{ display:flex; justify-content:flex-end; margin-top:10px; }

    .auth-footer{
      display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-top:14px;
    }
    .btn.ghost{
      background:#fff; border:1px solid #e5e7eb; color:#1f2937; border-radius:999px; padding:12px 18px; display:block; text-align:center;
    }
    .btn.ghost:hover{ background:#f8fafc; }
    .linkish{ color:#7c3aed; font-weight:800; text-decoration:underline; }

    .alert{ border-radius:12px; padding:10px 12px; margin:10px 0; font-weight:600 }
    .alert.ok{ background:#ecfdf5; color:#065f46; border:1px solid #10b981 }
    .alert.err{ background:#fef2f2; color:#991b1b; border:1px solid #ef4444 }

    /* ===== Tema dark (alinhado ao dashboard/admin_members) ===== */
    :root[data-theme="dark"] body.auth-body{
      background:#0b1020 radial-gradient(1200px 600px at 50% -20%, rgba(88,28,135,.25), transparent) no-repeat;
    }
    :root[data-theme="dark"] .auth-card{
      background:#0f172a; color:#e5e7eb;
      border:1px solid #1f2937; box-shadow:0 20px 60px rgba(0,0,0,.45);
    }
    :root[data-theme="dark"] .auth-form label{ color:#e5e7eb }
    :root[data-theme="dark"] .auth-form input{
      background:#0f172a; color:#e5e7eb; border-color:#1f2937;
    }
    :root[data-theme="dark"] .btn.ghost{
      background:transparent; border-color:#334155; color:#e5e7eb;
    }
    :root[data-theme="dark"] .btn.ghost:hover{ background:rgba(255,255,255,.06); }
    :root[data-theme="dark"] .alert.ok{
      background:rgba(16,185,129,.12); color:#a7f3d0; border-color:#10b981;
    }
    :root[data-theme="dark"] .alert.err{
      background:rgba(239,68,68,.12); color:#fecaca; border-color:#ef4444;
    }
  </style>
</head>

<body class="auth-body">
  <div class="auth-wrap">
    <section class="auth-card">
      <div class="auth-head">
        <h1 class="auth-title">
          <strong>Lifecycle</strong> <span class="brand">Canvas</span>
        </h1>
        <div class="pillbar">
          <button id="themeToggle" class="btn ghost pill" type="button">🌙 Dark</button>
        </div>
      </div>
      <p class="muted" style="margin-top:-6px;margin-bottom:12px">Gerencie o ciclo de vida de desenvolvimento de software.</p>

      <?php if ($ok): ?><div class="alert ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <form class="auth-form" method="post" action="/login.php">
        <label>E-mail
          <input type="email" name="email" required placeholder="voce@empresa.com.br"/>
        </label>
        <label>Senha
          <input type="password" name="password" required placeholder="••••••••"/>
        </label>
        <div class="auth-actions">
          <button class="btn primary pill" type="submit">Entrar</button>
        </div>
      </form>

      <div class="auth-footer">
        <a class="btn ghost" href="/register.php"><span class="linkish">Criar conta</span></a>
        <a class="btn ghost" href="/reset.php"><span class="linkish">Esqueci minha senha</span></a>
      </div>
    </section>
  </div>

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
