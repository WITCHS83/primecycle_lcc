<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (is_logged_in()) { header('Location: /dashboard.php'); exit; }
?><!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Criar conta</title><link rel="stylesheet" href="/assets/style.css"/></head>
<body class="auth-body"><div class="card auth-card">
<h1><strong>Criar</strong> conta</h1>
<form class="form" method="post" action="/register.php">
<label>Nome <input type="text" name="name" required></label>
<label>E-mail <input type="email" name="email" required></label>
<label>Senha <input type="password" name="password" required></label>
<label>Confirmar senha <input type="password" name="password2" required></label>
<div class="right"><button class="primary">Criar conta</button></div>
</form><hr style="border:0;border-top:1px solid #eee;margin:16px 0">
<a class="button ghost" href="/index.php">Já tenho uma conta</a></div></body></html>