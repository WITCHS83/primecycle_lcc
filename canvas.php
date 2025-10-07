<?php
require_once __DIR__ . '/config.php'; require_login();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* Documento + permissão do usuário */
$stmt = $pdo->prepare("
  SELECT d.*, du.role
  FROM documents d
  JOIN doc_users du ON du.doc_id = d.id
  WHERE d.id = ? AND du.user_id = ?
  LIMIT 1
");
$stmt->execute([$docId, current_user_id()]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); echo "Documento não encontrado ou sem acesso."; exit; }
$role = $doc['role'];  // 'admin' | 'editor' | 'viewer'
$canEdit = in_array($role, ['admin','editor'], true);

/* Sections existentes */
$secStmt = $pdo->prepare("SELECT slug, title, content FROM sections WHERE doc_id = ?");
$secStmt->execute([$docId]);
$sections = [];
foreach ($secStmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $sections[$row['slug']] = $row; }

function preview($slug, $sections){
  if (!isset($sections[$slug]['content'])) return '';
  $txt = trim(strip_tags($sections[$slug]['content']));
  $txt = preg_replace('/\s+/', ' ', $txt);
  return mb_strimwidth($txt, 0, 220, '…', 'UTF-8');
}

/* grid tiles */
$TILES = [
  'justificativas' => 'Justificativas',
  'produto'        => 'Produto',
  'partes'         => 'Partes interessadas',
  'premissas'      => 'Premissas',
  'riscos'         => 'Riscos',
  'objetivos'      => 'Objetivos',
  'requisitos'     => 'Requisitos',
  'equipe'         => 'Equipe',
  'entregas'       => 'Entregas',
  'tempo'          => 'Tempo',
  'beneficios'     => 'Benefícios',
  'restricoes'     => 'Restrições',
  'comunicacoes'   => 'Comunicações',
  'aquisoes'       => 'Aquisições',
  'custo'          => 'Custo',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <!-- BOOT DO TEMA -->
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

  <title><?= h($doc['title']) ?> — Lifecycle Canvas</title>

  <!-- CSS -->
  <link rel="stylesheet" href="/assets/style.css"/>
  <link rel="stylesheet" href="/assets/style.append.css"/>
  <link rel="stylesheet" href="/assets/lcboard.css"/>
  <link rel="stylesheet" href="/assets/modern.append.css"/>

  <!-- DARK FIX (cabeçalho + geral) -->
  <style>
    /* topbar coerente */
    :root{ --topbar-bg:#ffffffcc; --topbar-text:#111827; --topbar-border:#e5e7eb; }
    :root[data-theme="dark"]{ --topbar-bg:#0f172acc; --topbar-text:#e5e7eb; --topbar-border:#1f2937; }
    .topbar{
      background:var(--topbar-bg) !important;
      color:var(--topbar-text) !important;
      border-bottom:1px solid var(--topbar-border) !important;
      backdrop-filter:saturate(140%) blur(6px);
    }
    .topbar .btn.ghost{ color:var(--topbar-text) !important; border:1px solid var(--topbar-border) !important; }

    /* fundo, cards e inputs */
    :root[data-theme="dark"] body{ background:#0b1020 !important; color:#e5e7eb !important; }
    :root[data-theme="dark"] .card, :root[data-theme="dark"] .doc-card, :root[data-theme="dark"] .tile, :root[data-theme="dark"] .modal{
      background:#0f172a !important; color:#e5e7eb !important; border:1px solid #1f2937 !important; box-shadow:0 8px 24px rgba(0,0,0,.45) !important;
    }
    :root[data-theme="dark"] input, :root[data-theme="dark"] select, :root[data-theme="dark"] textarea{
      background:#0f172a !important; color:#e5e7eb !important; border-color:#1f2937 !important;
    }
    :root[data-theme="dark"] .muted{ color:#9ca3af !important; }

    /* ===== Dark para o cabeçalho do Canvas (mais específico) ===== */
    :root[data-theme="dark"] .lcx-head .card{ background:#111827 !important; border-color:#1f2937 !important; }
    :root[data-theme="dark"] .lcx-head .card header{ background:#4b5563 !important; color:#f3f4f6 !important; border-radius:6px; padding:6px 8px; }
    :root[data-theme="dark"] .lcx-foot .card header{ background:#4b5563 !important; color:#f3f4f6 !important; }

    /* Logo (opcional: deixa a plaquinha menos brilhante no dark) */
    :root[data-theme="dark"] .lcx-head .logo .logo-badge{ color:#f59e0b; }
    :root[data-theme="dark"] .lcx-head .logo .logo-sub{ color:#cbd5e1; }

    /* status de autosave */
    .save-dot{ margin-left:8px; font-size:12px; opacity:.8; }
    .save-dot.saving{ color:#f59e0b; }
    .save-dot.ok{ color:#22c55e; }
    .save-dot.err{ color:#ef4444; }
  </style>
</head>

<body>
<?php include __DIR__ . '/partials/topbar.inc.php'; ?>

<main class="lcx">
  <!-- Ações -->
  <div class="modern-actions" style="display:flex;gap:8px;margin:8px 0 0">
    <button id="themeToggle" class="btn ghost" type="button">🌙 Dark</button>
    <a class="btn ghost" href="/dashboard.php">← Voltar</a>
  </div>

  <!-- HEADER STRIP -->
  <section class="lcx-head">
    <!-- Logo -->
    <div class="card logo">
      <div class="logo-badge">LifeCycle<br>CANVAS</div>
      <div class="logo-sub">TECNOLOGIA DE GESTÃO</div>
    </div>

    <!-- Projeto (EDITÁVEL) -->
    <div class="card projeto">
      <header>Projeto</header>
      <label>Título
        <input class="doc-edit" data-field="title" value="<?= h($doc['title']) ?>" <?= $canEdit?'':'disabled' ?>>
        <span class="save-dot" data-field-dot="title"></span>
      </label>
      <label>Nickname
        <input class="doc-edit" data-field="nickname" value="<?= h($doc['nickname'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
        <span class="save-dot" data-field-dot="nickname"></span>
      </label>
    </div>

    <!-- IGDP -->
    <div class="card igdp">
      <header>IGDP</header>
      <div class="dots">
        <span class="dot green"></span><small>VERDE</small>
        <span class="dot yellow"></span><small>AMARELO</small>
        <span class="dot red"></span><small>VERMELHO</small>
        <span class="dot teal"></span>
      </div>
    </div>

    <!-- Ciclo de Vida (EDITÁVEL) -->
    <div class="card ciclo">
      <header>Ciclo de Vida</header>
      <div class="row">
        <label>Fase
          <select class="doc-edit" data-field="fase" <?= $canEdit?'':'disabled' ?>>
            <option value="IN" <?= ($doc['fase']??'')==='IN'?'selected':''; ?>>IN</option>
            <option value="PL" <?= ($doc['fase']??'')==='PL'?'selected':''; ?>>PL</option>
            <option value="MO" <?= ($doc['fase']??'')==='MO'?'selected':''; ?>>MO</option>
            <option value="EX" <?= ($doc['fase']??'')==='EX'?'selected':''; ?>>EX</option>
            <option value="EN" <?= ($doc['fase']??'')==='EN'?'selected':''; ?>>EN</option>
          </select>
          <span class="save-dot" data-field-dot="fase"></span>
        </label>
        <label>Artefato
          <select class="doc-edit" data-field="artefato" <?= $canEdit?'':'disabled' ?>>
            <option value="TAP" <?= ($doc['artefato']??'')==='TAP'?'selected':''; ?>>TAP</option>
            <option value="PGP" <?= ($doc['artefato']??'')==='PGP'?'selected':''; ?>>PGP</option>
            <option value="REP" <?= ($doc['artefato']??'')==='REP'?'selected':''; ?>>REP</option>
            <option value="TEP" <?= ($doc['artefato']??'')==='TEP'?'selected':''; ?>>TEP</option>
          </select>
          <span class="save-dot" data-field-dot="artefato"></span>
        </label>
      </div>
      <div class="mini-mapa"></div>
    </div>

    <!-- Data/Local (EDITÁVEL) -->
    <div class="card meta">
      <label>Data
        <input type="date" class="doc-edit" data-field="data" value="<?= h($doc['data'] ?? date('Y-m-d')) ?>" <?= $canEdit?'':'disabled' ?>>
        <span class="save-dot" data-field-dot="data"></span>
      </label>
      <label>Local
        <input type="text" class="doc-edit" data-field="local" value="<?= h($doc['local'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
        <span class="save-dot" data-field-dot="local"></span>
      </label>
    </div>
  </section>

  <!-- GRID 5×3 -->
  <section class="lcx-grid">
    <?php
      $tone = [
        'justificativas'=>'tone-just','produto'=>'tone-prod','partes'=>'tone-partes','premissas'=>'tone-prem','riscos'=>'tone-riscos',
        'objetivos'=>'tone-obj','requisitos'=>'tone-req','equipe'=>'tone-equipe','entregas'=>'tone-ent','tempo'=>'tone-tempo',
        'beneficios'=>'tone-benef','restricoes'=>'tone-rest','comunicacoes'=>'tone-com','aquisoes'=>'tone-aq','custo'=>'tone-custo',
      ];
      foreach ($TILES as $slug=>$title):
        $has = !empty($sections[$slug]['content']);
    ?>
      <div class="tile <?= $tone[$slug] ?>" data-slug="<?= h($slug) ?>">
        <div class="tile-head">
          <div class="title">
            <span class="ic"></span>
            <strong><?= h($title) ?></strong>
          </div>
          <button type="button" class="tile-add" title="<?= $has?'Editar':'Adicionar' ?>"><?= $has?'✎':'+' ?></button>
        </div>
        <div class="tile-body">
          <?php if ($has): ?>
            <p><strong>Resumo:</strong> <?= h(preview($slug,$sections)) ?></p>
          <?php else: ?>
            <p class="muted">—</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- FOOTER -->
  <section class="lcx-foot">
    <div class="foot card">
      <header>Versão</header>
      <input type="text" class="doc-edit" data-field="versao" value="<?= h($doc['versao'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
      <span class="save-dot" data-field-dot="versao"></span>
    </div>
    <div class="foot card">
      <header>Gerente</header>
      <input type="text" class="doc-edit" data-field="gerente" value="<?= h($doc['gerente'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
      <span class="save-dot" data-field-dot="gerente"></span>
    </div>
    <div class="foot card">
      <header>Patrocinador</header>
      <input type="text" class="doc-edit" data-field="patrocinador" value="<?= h($doc['patrocinador'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
      <span class="save-dot" data-field-dot="patrocinador"></span>
    </div>
    <div class="foot card">
      <header>Cliente</header>
      <input type="text" class="doc-edit" data-field="cliente" value="<?= h($doc['cliente'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
      <span class="save-dot" data-field-dot="cliente"></span>
    </div>
  </section>
</main>

<!-- Modal (seções) -->
<div class="modal-back" id="secModal">
  <div class="modal">
    <div class="modal-head">
      <div id="secTitle">Editar</div>
      <button id="btnClose" class="btn ghost">Fechar</button>
    </div>
    <div class="modal-body">
      <label>Título <input id="secInputTitle" type="text"></label>
      <label>Conteúdo <textarea id="secTextarea" placeholder="Escreva aqui…"></textarea></label>
    </div>
    <div class="modal-foot">
      <button id="btnSave" class="btn primary">Salvar</button>
    </div>
  </div>
</div>

<!-- JS -->
<script>
(function(){
  // Tema
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

  // Autosave
  const DOC_ID = <?= (int)$docId ?>;
  const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;

  function setDot(field, state){
    const dot = document.querySelector(`[data-field-dot="${field}"]`);
    if(!dot) return;
    dot.className = 'save-dot ' + (state||'');
    dot.textContent = state==='saving' ? 'salvando…' : state==='ok' ? 'salvo' : state==='err' ? 'erro' : '';
  }

  function saveField(field, value){
    setDot(field, 'saving');
    const fd = new FormData();
    fd.set('doc_id', DOC_ID);
    fd.set('field', field);
    fd.set('value', value);
    return fetch('/api/doc_update.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(r=>r.json())
      .then(j=>{ setDot(field, j.ok ? 'ok' : 'err'); if(!j.ok && j.error) alert(j.error); setTimeout(()=>setDot(field,''), 1500); })
      .catch(()=>{ setDot(field, 'err'); setTimeout(()=>setDot(field,''), 2000); });
  }

  if(CAN_EDIT){
    document.querySelectorAll('.doc-edit').forEach(el=>{
      const field = el.dataset.field;
      const handler = ()=> saveField(field, el.value);
      if(el.tagName==='SELECT' || el.type==='date'){
        el.addEventListener('change', handler);
      }else{
        let t=null;
        el.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(handler, 500); });
        el.addEventListener('blur', handler);
      }
    });
  }

  // Modal de seções
  const ROLE = <?= json_encode($role) ?>;
  const canEdit = ROLE === 'admin' || ROLE === 'editor';

  const modalBack=document.getElementById('secModal');
  const txt=document.getElementById('secTextarea');
  const inTitle=document.getElementById('secInputTitle');
  const titleEl=document.getElementById('secTitle');
  let currentSlug=null;

  function openModal(slug,title){
    if(!canEdit){ alert('Somente leitura.'); return; }
    currentSlug=slug; titleEl.textContent=title;
    fetch(`/api/sections.php?doc_id=${DOC_ID}&slug=${encodeURIComponent(slug)}`, {credentials:'same-origin'})
      .then(r=>r.ok?r.json():Promise.reject())
      .then(j=>{ inTitle.value=j.title||title; txt.value=j.content||''; })
      .catch(()=>{ inTitle.value=title; txt.value=''; })
      .finally(()=>{ modalBack.classList.add('show'); document.body.style.overflow='hidden'; });
  }

  document.querySelectorAll('.tile-add').forEach(b=>{
    b.addEventListener('click',ev=>{
      const t=ev.target.closest('.tile');
      openModal(t.dataset.slug, t.querySelector('.title strong').textContent.trim());
    });
  });
  document.getElementById('btnClose').onclick=()=>{ modalBack.classList.remove('show'); document.body.style.overflow=''; };
  modalBack.addEventListener('click',e=>{ if(e.target===modalBack){ modalBack.classList.remove('show'); document.body.style.overflow=''; }});

  document.getElementById('btnSave').onclick=()=>{
    if(!currentSlug) return;
    const payload=new FormData();
    payload.set('doc_id',DOC_ID);
    payload.set('slug',currentSlug);
    payload.set('title',inTitle.value.trim());
    payload.set('content',txt.value);

    fetch('/api/sections.php',{method:'POST',body:payload,credentials:'same-origin'})
      .then(r=>r.json())
      .then(j=>{
        if(j.ok){
          const tile=document.querySelector(`.tile[data-slug="${currentSlug}"]`);
          tile.querySelector('.tile-add').textContent='✎';
          const prev=(txt.value||'').replace(/\s+/g,' ').slice(0,220) + ((txt.value||'').length>220?'…':'');
          tile.querySelector('.tile-body').innerHTML = prev ? `<p><strong>Resumo:</strong> ${prev}</p>` : `<p class="muted">—</p>`;
          modalBack.classList.remove('show'); document.body.style.overflow='';
        }else{ alert(j.error||'Falha ao salvar.'); }
      })
      .catch(()=>alert('Erro de rede.'));
  };
})();
</script>
</body>
</html>
