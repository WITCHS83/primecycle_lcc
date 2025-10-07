<?php
require_once __DIR__ . '/config.php';
require_login();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('is_site_admin')) {
  function is_site_admin(): bool { return (int)current_user_id() === 1; } // ajuste a regra se precisar
}
if (!function_exists('app_get')) {
  function app_get($key,$default=''){ global $pdo; $st=$pdo->prepare("SELECT value FROM app_settings WHERE `key`=? LIMIT 1"); $st->execute([$key]); $v=$st->fetchColumn(); return ($v===false)?$default:$v; }
}
if (!function_exists('app_set')) {
  function app_set($key,$value){ global $pdo; $st=$pdo->prepare("INSERT INTO app_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"); $st->execute([$key,$value]); }
}

if (!is_site_admin()) { http_response_code(403); exit('Apenas administradores do site.'); }

$msgOk=''; $msgErr=''; $smtpDebug='';

$smtp_enabled    = app_get('smtp_enabled','0');
$smtp_from_email = app_get('smtp_from_email','no-reply@seu-dominio.com');
$smtp_from_name  = app_get('smtp_from_name','Lifecycle Canvas');
$smtp_host       = app_get('smtp_host','');
$smtp_port       = app_get('smtp_port','587');
$smtp_secure     = app_get('smtp_secure','tls'); // tls|ssl|none
$smtp_user       = app_get('smtp_user','');

if ($_SERVER['REQUEST_METHOD']==='POST') {
  try{
    $smtp_enabled    = isset($_POST['smtp_enabled'])?'1':'0';
    $smtp_from_email = trim($_POST['smtp_from_email'] ?? $smtp_from_email);
    $smtp_from_name  = trim($_POST['smtp_from_name']  ?? $smtp_from_name);
    $smtp_host       = trim($_POST['smtp_host']       ?? $smtp_host);
    $smtp_port       = trim($_POST['smtp_port']       ?? $smtp_port);
    $smtp_secure     = trim($_POST['smtp_secure']     ?? $smtp_secure);
    $smtp_user       = trim($_POST['smtp_user']       ?? $smtp_user);
    $new_pass        = $_POST['smtp_pass'] ?? ''; // mantém se vazio
    $test_to         = trim($_POST['test_to'] ?? '');

    app_set('smtp_enabled',    $smtp_enabled);
    app_set('smtp_from_email', $smtp_from_email);
    app_set('smtp_from_name',  $smtp_from_name);
    app_set('smtp_host',       $smtp_host);
    app_set('smtp_port',       $smtp_port);
    app_set('smtp_secure',     $smtp_secure);
    app_set('smtp_user',       $smtp_user);
    if ($new_pass!=='') app_set('smtp_pass', $new_pass);

    $msgOk='Configurações salvas.';

    if ($test_to!=='') {
      require_once __DIR__ . '/mailer.php';
      $debug='';
      $smtpConf = ($smtp_enabled==='1') ? [
        'host'   => $smtp_host,
        'port'   => (int)$smtp_port,
        'secure' => strtolower($smtp_secure),
        'user'   => $smtp_user,
        'pass'   => app_get('smtp_pass',''),
      ] : null;

      $ok = send_mail([
        'to'         => [$test_to=>'Teste'],
        'subject'    => 'Teste de SMTP — Lifecycle Canvas',
        'html'       => '<p>Este é um <strong>teste</strong> de envio SMTP.</p>',
        'text'       => "Este é um teste de envio SMTP.",
        'from_email' => $smtp_from_email,
        'from_name'  => $smtp_from_name,
        'smtp'       => $smtpConf
      ], $debug);

      $smtpDebug = $debug;
      if (!$ok) { $msgErr = 'Não foi possível enviar o e-mail de teste. Verifique host/porta/credenciais/segurança.'; }
      else { $msgOk .= ' E-mail de teste enviado!'; }
    }
  }catch(Throwable $e){
    $msgErr='Erro: '.h($e->getMessage());
  }
}
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Configurações — Lifecycle Canvas</title>

  <script>(function(){try{var k='lifecycle.theme',s=localStorage.getItem(k);if(!s){s=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';localStorage.setItem(k,s);}if(s==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>

  <link rel="stylesheet" href="/assets/style.css"/>
  <link rel="stylesheet" href="/assets/style.append.css"/>
  <link rel="stylesheet" href="/assets/lcboard.css"/>
  <link rel="stylesheet" href="/assets/modern.append.css"/>

  <style>
    .wrap{ max-width:1200px; margin:16px auto 32px; padding:0 12px; }
    .top-strip{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
    .pillbar{ display:flex; gap:10px; }
    .btn.pill{ border-radius:999px; font-weight:800; padding:10px 16px; }
    .btn.primary{ background:#765475; color:#fff; border:none; }
    .btn.primary:hover{ background:#5b3c5c; }
    .btn.ghost{ background:#fff; border:1px solid #e5e7eb; }
    :root[data-theme="dark"] .btn.ghost{ background:transparent; border-color:#1f2937; color:#e5e7eb; }
    :root[data-theme="dark"] .btn.ghost:hover{ background:rgba(255,255,255,.06); }

    .card{ padding:14px; }
    .card header.bar{ background:#765475; color:#fff; font-weight:800; font-size:18px; padding:10px 12px; border-radius:12px; margin-bottom:12px; }

    .grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    @media (max-width:1000px){ .grid-2{ grid-template-columns: 1fr; } }
    .row{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    @media (max-width:700px){ .row{ grid-template-columns: 1fr; } }

    .muted{ color:#6b7280; }
    :root[data-theme="dark"] .muted{ color:#9ca3af; }

    :root[data-theme="dark"] body{ background:#0b1020; color:#e5e7eb; }
    :root[data-theme="dark"] .card{ background:#0f172a; border:1px solid #1f2937; box-shadow:0 6px 20px rgba(0,0,0,.45); }
    :root[data-theme="dark"] input, :root[data-theme="dark"] select, :root[data-theme="dark"] textarea{ background:#0f172a; color:#e5e7eb; border-color:#1f2937; }
    :root[data-theme="dark"] .card header.bar{ background:#6f5675; }

    .topbar { background:#e5e7eb; border-bottom:1px solid rgba(0,0,0,.06); }
    .topbar a { color:#1d4ed8; } .topbar strong { color:#0f172a; }
    :root[data-theme="dark"] .topbar{ background:#111827 !important; border-bottom:1px solid #1f2937 !important; }
    :root[data-theme="dark"] .topbar a{ color:#e5e7eb !important; }
    :root[data-theme="dark"] .topbar a:hover{ color:#facc15 !important; }
    :root[data-theme="dark"] .topbar strong{ color:#f3f4f6 !important; }

    pre.debug{ background:#111827; color:#d1d5db; padding:10px; border-radius:8px; overflow:auto; border:1px solid #1f2937; }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/topbar.inc.php'; ?>
<main class="wrap">
  <section class="card" style="padding:14px;">
    <div class="top-strip">
      <h1 style="margin:0">Configurações</h1>
      <div class="pillbar">
        <button id="themeToggle" class="btn ghost pill" type="button">🌙 Dark</button>
        <a class="btn ghost pill" href="/dashboard.php">← Voltar</a>
      </div>
    </div>
    <?php if ($msgOk): ?><div class="alert ok"><?= $msgOk ?></div><?php endif; ?>
    <?php if ($msgErr): ?><div class="alert err"><?= $msgErr ?></div><?php endif; ?>
  </section>

  <section class="card">
    <header class="bar">E-mail / SMTP</header>
    <form method="post" class="form">
      <label style="display:flex; align-items:center; gap:10px; margin-bottom:10px">
        <input type="checkbox" name="smtp_enabled" <?= $smtp_enabled==='1'?'checked':''; ?>><span>Ativar SMTP próprio</span>
      </label>

      <div class="grid-2">
        <label>Remetente (e-mail)<input name="smtp_from_email" value="<?= h($smtp_from_email) ?>" placeholder="no-reply@seu-dominio.com"></label>
        <label>Remetente (nome) <input name="smtp_from_name"  value="<?= h($smtp_from_name)  ?>" placeholder="Lifecycle Canvas"></label>
      </div>

      <div class="grid-2">
        <label>Host <input name="smtp_host" value="<?= h($smtp_host) ?>" placeholder="smtp.seu-dominio.com"></label>
        <label>Porta <input name="smtp_port" value="<?= h($smtp_port) ?>" placeholder="587"></label>
      </div>

      <div class="grid-2">
        <label>Segurança
          <select name="smtp_secure">
            <option value="tls"  <?= $smtp_secure==='tls'?'selected':''; ?>>TLS</option>
            <option value="ssl"  <?= $smtp_secure==='ssl'?'selected':''; ?>>SSL</option>
            <option value="none" <?= $smtp_secure==='none'?'selected':''; ?>>Nenhuma</option>
          </select>
        </label>
        <div></div>
      </div>

      <div class="grid-2">
        <label>Usuário (login) <input name="smtp_user" value="<?= h($smtp_user) ?>" placeholder="usuario@seu-dominio.com"></label>
        <label>Senha <input name="smtp_pass" type="password" value="" placeholder="••••••••"><small class="muted">Deixe em branco para manter a atual.</small></label>
      </div>

      <div class="row" style="margin-top:8px">
        <label>E-mail para testar envio (opcional)<input name="test_to" placeholder="seu-email@exemplo.com"></label>
        <div class="right" style="display:flex;align-items:end;justify-content:flex-end">
          <button class="btn primary pill" type="submit">Salvar (e enviar teste se informado)</button>
        </div>
      </div>
    </form>
    <?php if ($smtpDebug): ?>
      <div style="margin-top:12px">
        <details open><summary>Debug do envio</summary><pre class="debug"><?= h($smtpDebug) ?></pre></details>
      </div>
    <?php endif; ?>
  </section>
</main>

<script>
(function(){const KEY='lifecycle.theme',root=document.documentElement,btn=document.getElementById('themeToggle');function paint(){ if(btn) btn.textContent=root.getAttribute('data-theme')==='dark'?'☀️ Light':'🌙 Dark'; }paint();if(btn){btn.addEventListener('click',()=>{const dark=root.getAttribute('data-theme')==='dark'; if(dark){root.removeAttribute('data-theme');localStorage.setItem(KEY,'light');}else{root.setAttribute('data-theme','dark');localStorage.setItem(KEY,'dark');}paint();});}})();
</script>
</body>
</html>
