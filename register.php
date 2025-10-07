<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$err = $_GET['err'] ?? '';
$nameVal = '';
$emailVal = '';

/* ===== POST: criar conta ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password']  ?? '';
  $pass2 = $_POST['password2'] ?? '';

  $nameVal  = $name;
  $emailVal = $email;

  if ($name === '' || $email === '' || $pass === '' || $pass2 === '') {
    $err = 'Preencha todos os campos.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'E-mail inválido.';
  } elseif ($pass !== $pass2) {
    $err = 'Senhas não conferem.';
  } elseif (strlen($pass) < 6) {
    $err = 'Senha deve ter ao menos 6 caracteres.';
  } else {
    try {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      // se sua tabela tiver created_at, ajuste a query (ex.: ..., created_at) VALUES (..., NOW())
      $st = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
      $st->execute([$name, $email, $hash]);

      $_SESSION['user_id'] = (int)$pdo->lastInsertId();
      header('Location: /dashboard.php');
      exit;
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) { // chave única (email)
        $err = 'Este e-mail já está cadastrado.';
      } else {
        $err = 'Erro ao registrar. Tente novamente.';
      }
    } catch (Throwable $e) {
      $err = 'Erro inesperado. Tente novamente.';
    }
  }
}

/* ===== GET: exibir formulário ===== */
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <title>Criar conta — Lifecycle Canvas</title>

  <!-- Boot do tema (antes dos CSS) -->
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

  <!-- CSS base + paleta do app -->
  <link rel="stylesheet" href="/assets/style.css"/>
  <link rel="stylesheet" href="/assets/style.append.css"/>
  <link rel="stylesheet" href="/assets/lcboard.css"/>
  <link rel="stylesheet" href="/assets/modern.append.css"/>

  <style>
    /* Fundo suave como o login; escurece no dark */
    body{
      min-height:100dvh;
      display:grid;
      place-items:center;
      background: radial-gradient(1200px 600px at 70% 0, rgba(255,255,255,.65), transparent 60%), #d7b796;
    }
    :root[data-theme="dark"] body{
      background: radial-gradient(1200px 600px at 70% 0, rgba(255,255,255,.05), transparent 60%), #0b1020;
    }

    /* Card central */
    .auth-card{
      width:min(640px, 92vw);
      background:#fff;
      border-radius:18px;
      border:1px solid rgba(0,0,0,.06);
      box-shadow: 0 30px 60px rgba(0,0,0,.18), 0 8px 20px rgba(0,0,0,.10);
      padding:24px;
    }
    :root[data-theme="dark"] .auth-card{
      background:#0f172a;
      border:1px solid #1f2937;
      box-shadow: 0 30px 80px rgba(0,0,0,.55), 0 8px 24px rgba(0,0,0,.4);
    }

    .brand{ font-size:34px; font-weight:900; margin:0 0 4px; }
    .brand span{ color:#2563eb; }
    .sub{ margin:0 0 18px; color:#6b7280; }
    :root[data-theme="dark"] .sub{ color:#9aa4b2; }

    .field{ margin:10px 0 12px; }
    .field label{ display:block; font-weight:700; margin-bottom:6px; }
    .field input{
      width:100%; height:44px; border-radius:12px; padding:0 14px;
      border:1px solid rgba(0,0,0,.12); background:#fff;
    }
    :root[data-theme="dark"] .field input{
      background:#0f172a; color:#e5e7eb; border-color:#1f2937;
    }

    .row{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media (max-width:640px){ .row{ grid-template-columns:1fr; } }

    .actions{ display:flex; justify-content:flex-end; margin-top:8px; }
    .btn.pill{ border-radius:999px; font-weight:800; padding:10px 18px; }
    .btn.primary{ background:#2563eb; color:#fff; border:none; }
    .btn.primary:hover{ background:#1e49b9; }

    .links{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:18px; }
    .links a{
      display:block; text-align:center; padding:12px 16px; border-radius:999px;
      background:#f5f6f8; font-weight:800; text-decoration:none;
    }
    .links a:hover{ background:#eef0f3; }
    :root[data-theme="dark"] .links a{ background:#111827; color:#e5e7eb; border:1px solid #1f2937; }
    :root[data-theme="dark"] .links a:hover{ background:#0f172a; }

    .err{ margin-top:10px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:12px; }
    :root[data-theme="dark"] .err{ background:#7f1d1d; color:#fee2e2; border-color:#991b1b; }

    .topbar-mini{
      display:flex; justify-content:flex-end; gap:10px; margin-bottom:10px;
    }
    .btn.ghost{
      background:transparent; border:1px solid rgba(0,0,0,.12); color:#111827;
      border-radius:999px; font-weight:800; padding:8px 12px;
    }
    :root[data-theme="dark"] .btn.ghost{
      border-color:#1f2937; color:#e5e7eb;
    }
    .btn.ghost:hover{ background:rgba(0,0,0,.06); }
    :root[data-theme="dark"] .btn.ghost:hover{ background:rgba(255,255,255,.06); }
  </style>
</head>
<body>

  <div class="auth-card">
    <div class="topbar-mini">
      <button id="themeToggle" class="btn ghost" type="button">🌙 Dark</button>
      <a class="btn ghost" href="/index.php">← Voltar ao login</a>
    </div>

    <h1 class="brand">Lifecycle <span>Canvas</span></h1>
    <p class="sub">Crie sua conta para gerenciar o ciclo de vida de desenvolvimento de software.</p>

    <?php if ($err): ?>
      <div class="err"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="post" action="/register.php" novalidate>
      <div class="field">
        <label>Nome</label>
        <input name="name" autocomplete="name" required value="<?= h($nameVal) ?>">
      </div>
      <div class="field">
        <label>E-mail</label>
        <input type="email" name="email" autocomplete="email" required placeholder="voce@empresa.com.br" value="<?= h($emailVal) ?>">
      </div>

      <div class="row">
        <div class="field">
          <label>Senha</label>
          <input type="password" name="password" autocomplete="new-password" required>
        </div>
        <div class="field">
          <label>Confirmar senha</label>
          <input type="password" name="password2" autocomplete="new-password" required>
        </div>
      </div>

      <div class="actions">
        <button class="btn primary pill" type="submit">Criar conta</button>
      </div>
    </form>

    <div class="links">
      <a href="/index.php">Já tenho conta</a>
      <a href="/reset.php">Esqueci minha senha</a>
    </div>
  </div>

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
