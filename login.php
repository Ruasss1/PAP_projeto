<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
session_start();
require_once __DIR__ . '/includes/auth.php';
if ($auth->is_authenticated()) { header('Location: /index.php'); exit; }
$error = '';
$success = '';
$db_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Preencha todos os campos';
    } else {
        try {
            $result = $auth->login($email, $password);
            if ($result['success']) { header('Location: /index.php'); exit; }
            else { $error = $result['message'] ?? 'Credenciais invalidas'; }
        } catch (RuntimeException $e) {
            $db_error = true;
            $error = $e->getMessage();
        }
    }
}
if (!$db_error && defined('DB_ERROR')) {
    $db_error = true;
    $error = 'Base de dados indisponível: ' . DB_ERROR;
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PAP Market</title>
  <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/assets/icons/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/master-ui.css?v=<?= time() ?>">
  <script>(function(){var t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t)})();</script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:      #060606;
      --bg2:     #0b0b0b;
      --bg3:     #131313;
      --border:  #1a1a1a;
      --border2: #2a2a2a;
      --txt:     #ececec;
      --txt2:    #7a7a7a;
      --txt3:    #3e3e3e;
      --btn:     #ececec;
      --btn-txt: #060606;
      --danger:  #f87171;
    }
    [data-theme="light"] {
      --bg:      #f5f4f1;
      --bg2:     #ffffff;
      --bg3:     #eeecea;
      --border:  #dedad5;
      --border2: #c8c4be;
      --txt:     #111111;
      --txt2:    #5c5c5c;
      --txt3:    #aaaaaa;
      --btn:     #111111;
      --btn-txt: #ffffff;
      --danger:  #dc2626;
    }

    html { -webkit-font-smoothing: antialiased; height: 100%; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: var(--bg);
      color: var(--txt);
      padding: 32px 20px;
      position: relative;
      overflow: hidden;
    }

    /* Background deco number */
    .bg-deco {
      position: fixed;
      bottom: -120px; right: -40px;
      font-family: 'Playfair Display', serif;
      font-size: clamp(240px, 28vw, 360px);
      font-weight: 900; line-height: 1;
      color: transparent;
      -webkit-text-stroke: 1px rgba(255,255,255,.03);
      user-select: none; pointer-events: none;
      letter-spacing: -.06em; z-index: 0;
    }
    [data-theme="light"] .bg-deco { -webkit-text-stroke: 1px rgba(0,0,0,.04); }

    /* ========== CARD ========== */
    .card {
      position: relative; z-index: 1;
      display: flex; align-items: stretch;
      width: 100%; max-width: 880px;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,.55), 0 1px 0 rgba(255,255,255,.04) inset;
      animation: up .5s cubic-bezier(.16,1,.3,1) both;
    }
    [data-theme="light"] .card {
      box-shadow: 0 16px 48px rgba(0,0,0,.1), 0 1px 0 rgba(255,255,255,.8) inset;
    }

    /* ========== CARD LEFT ========== */
    .card-left {
      flex: 0 0 46%;
      position: relative;
      background: var(--bg3);
      border-right: 1px solid var(--border);
      padding: 52px 48px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
    }
    .card-left::after {
      content: '';
      position: absolute; inset: 0; pointer-events: none;
      background: radial-gradient(ellipse 100% 70% at 0% 100%, rgba(255,255,255,.03) 0%, transparent 65%);
    }
    [data-theme="light"] .card-left { background: #111; }

    .l-rule { width: 28px; height: 1px; background: var(--txt3); opacity: .45; margin-bottom: 36px; }

    .l-tag {
      font-size: 9.5px; font-weight: 600;
      letter-spacing: .22em; text-transform: uppercase;
      color: #555; margin-bottom: 28px;
    }
    [data-theme="light"] .l-tag { color: #999; }

    .headline {
      font-family: 'Playfair Display', serif;
      font-size: clamp(28px, 3.2vw, 44px);
      font-weight: 900;
      line-height: 1.1; letter-spacing: -.025em;
      color: #edeae4; margin-bottom: 24px;
      min-height: 3.6em;
    }

    .tw-cursor {
      display: inline-block; width: 2px; height: .82em;
      background: #555; margin-left: 3px;
      vertical-align: middle;
      animation: blink .85s step-end infinite;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

    .l-desc {
      font-family: 'Playfair Display', serif;
      font-style: italic; font-size: 13.5px;
      color: #888; line-height: 1.85;
      margin-bottom: 36px;
      opacity: 0; transform: translateY(8px);
      transition: opacity .5s ease, transform .5s ease;
    }
    .l-desc.show { opacity: 1; transform: none; }

    .feats { list-style: none; display: flex; flex-direction: column; gap: 9px; }
    .feats li {
      font-family: 'Playfair Display', serif;
      font-style: italic; font-size: 12.5px;
      color: #777; display: flex; align-items: center; gap: 12px;
      opacity: 0; transform: translateX(-10px);
      transition: opacity .45s, transform .45s;
    }
    .feats li.show { opacity: 1; transform: none; }
    .feats li::before { content: ''; width: 16px; height: 1px; background: #333; flex-shrink: 0; }

    .l-foot {
      font-family: 'Playfair Display', serif;
      font-style: italic; font-size: 10.5px;
      color: #444; letter-spacing: .02em;
      margin-top: 40px;
    }

    /* ========== CARD RIGHT ========== */
    .card-right {
      flex: 1;
      display: flex; align-items: center; justify-content: center;
      padding: 52px 48px;
      background: var(--bg2);
    }
    [data-theme="light"] .card-right { background: #fff; }

    .form-box {
      width: 100%; max-width: 320px;
    }

    .f-eyebrow {
      font-size: 10px; font-weight: 600;
      letter-spacing: .2em; text-transform: uppercase;
      color: var(--txt3); margin-bottom: 12px;
    }

    .f-title {
      font-family: 'Playfair Display', serif;
      font-size: 28px; font-weight: 700;
      letter-spacing: -.022em; color: var(--txt);
      line-height: 1.1; margin-bottom: 5px;
    }

    .f-sub {
      font-family: 'Playfair Display', serif;
      font-style: italic; font-size: 13.5px;
      color: var(--txt2); margin-bottom: 32px; line-height: 1.5;
    }

    /* Error */
    .err {
      background: rgba(248,113,113,.05);
      border: 1px solid rgba(248,113,113,.14);
      border-left: 2px solid var(--danger);
      border-radius: 7px; padding: 11px 14px;
      font-size: 13px; color: var(--danger);
      margin-bottom: 22px; display: flex; align-items: center; gap: 8px;
    }

    /* Fields */
    .fld { margin-bottom: 18px; }
    .fld-lbl {
      display: block; font-size: 10px; font-weight: 600;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--txt3); margin-bottom: 8px;
    }
    .fld input {
      width: 100%; background: var(--bg3);
      border: 1px solid var(--border); border-radius: 9px;
      padding: 14px 16px; font-size: 14px;
      font-family: inherit; color: var(--txt); outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
      -webkit-appearance: none;
    }
    [data-theme="light"] .fld input { background: var(--bg2); }
    .fld input::placeholder { color: var(--txt3); font-size: 13.5px; }
    .fld input:hover { border-color: var(--border2); }
    .fld input:focus {
      border-color: var(--border2);
      background: var(--bg2);
      box-shadow: 0 0 0 3px rgba(255,255,255,.04), 0 2px 8px rgba(0,0,0,.15);
    }
    [data-theme="light"] .fld input:focus {
      background: #fff;
      box-shadow: 0 0 0 3px rgba(0,0,0,.05), 0 2px 6px rgba(0,0,0,.06);
    }

    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 46px; }
    .pw-btn {
      position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      color: var(--txt3); padding: 6px; line-height: 0;
      display: flex; align-items: center; transition: color .15s;
    }
    .pw-btn:hover { color: var(--txt2); }
    .pw-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

    /* ---- CUSTOM CHECKBOX ROW ---- */
    .remember-row {
      display: flex; align-items: center;
      margin: 8px 0 28px;
    }
    .remember-row input[type="checkbox"] { display: none; }
    .toggle-track {
      display: inline-flex; align-items: center;
      gap: 11px; cursor: pointer;
      font-size: 12.5px; font-weight: 400; color: var(--txt2);
      user-select: none; transition: color .15s;
    }
    .toggle-track:hover { color: var(--txt); }
    .toggle-pill {
      position: relative; width: 40px; height: 22px;
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 22px;
      transition: background .25s, border-color .25s, box-shadow .25s;
      flex-shrink: 0;
    }
    .toggle-track:hover .toggle-pill { border-color: var(--txt3); }
    .toggle-pill::after {
      content: '';
      position: absolute; top: 4px; left: 4px;
      width: 12px; height: 12px;
      background: var(--txt3);
      border-radius: 50%;
      transition: transform .25s cubic-bezier(.34,1.56,.64,1), background .25s;
      box-shadow: 0 1px 3px rgba(0,0,0,.3);
    }
    input[type="checkbox"]:checked + .toggle-track .toggle-pill {
      background: var(--txt); border-color: var(--txt);
      box-shadow: 0 0 0 3px rgba(236,236,236,.1);
    }
    [data-theme="light"] input[type="checkbox"]:checked + .toggle-track .toggle-pill {
      box-shadow: 0 0 0 3px rgba(0,0,0,.07);
    }
    input[type="checkbox"]:checked + .toggle-track .toggle-pill::after {
      transform: translateX(18px); background: var(--bg);
    }
    [data-theme="light"] input[type="checkbox"]:checked + .toggle-track .toggle-pill::after { background: var(--bg2); }
    .toggle-label-txt { line-height: 1; }

    /* ---- CTA BUTTON ---- */
    .cta {
      width: 100%; margin-top: 4px;
      background: var(--btn); color: var(--btn-txt);
      border: none; border-radius: 10px;
      padding: 0; height: 54px;
      font-family: inherit; cursor: pointer;
      display: flex; align-items: stretch;
      overflow: hidden; position: relative;
      box-shadow: 0 1px 0 rgba(255,255,255,.08) inset,
                  0 4px 12px rgba(0,0,0,.3);
      transition: transform .18s cubic-bezier(.34,1.4,.64,1), box-shadow .2s;
    }
    .cta:hover {
      transform: translateY(-3px);
      box-shadow: 0 1px 0 rgba(255,255,255,.08) inset,
                  0 20px 48px rgba(0,0,0,.6);
    }
    [data-theme="light"] .cta { box-shadow: 0 2px 8px rgba(0,0,0,.1); }
    [data-theme="light"] .cta:hover { box-shadow: 0 12px 32px rgba(0,0,0,.18); }
    .cta:active { transform: translateY(0) scale(.99); }

    /* Shimmer sweep */
    .cta::before {
      content: ''; position: absolute;
      top: 0; left: -80%; width: 50%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.11), transparent);
      transform: skewX(-18deg);
      transition: left .6s ease;
    }
    .cta:hover::before { left: 140%; }

    .cta-body {
      flex: 1; display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase;
      padding: 0 18px;
    }
    .cta-sep {
      width: 1px; margin: 12px 0;
      background: rgba(0,0,0,.14);
    }
    [data-theme="light"] .cta-sep { background: rgba(255,255,255,.22); }
    .cta-icon {
      width: 54px; display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; transition: transform .2s cubic-bezier(.34,1.4,.64,1);
    }
    .cta:hover .cta-icon { transform: translateX(4px); }
    .cta-icon svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

    /* Divider */
    .div-row {
      display: flex; align-items: center; gap: 14px;
      margin: 28px 0;
      font-size: 9.5px; font-weight: 600;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--txt3);
    }
    .div-row::before, .div-row::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    /* Creds */
    .creds {
      border: 1px solid var(--border); border-radius: 8px;
      padding: 13px 16px; font-size: 12.5px; color: var(--txt2); line-height: 1.9;
    }
    .creds strong { color: var(--txt); font-weight: 500; }
    .creds code {
      font-family: 'SF Mono', 'Fira Code', monospace; font-size: 11px;
      background: var(--bg3); color: var(--txt);
      padding: 1px 6px; border-radius: 4px; border: 1px solid var(--border);
    }

    .f-foot {
      margin-top: 26px; font-size: 11px; color: var(--txt3);
      text-align: center;
      font-family: 'Playfair Display', serif; font-style: italic;
    }

    /* Theme toggle */
    .th-btn {
      position: fixed; bottom: 20px; right: 20px;
      width: 36px; height: 36px;
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 50%; display: grid; place-items: center;
      font-size: 14px; cursor: pointer; z-index: 99;
      transition: border-color .2s, transform .2s;
    }
    .th-btn:hover { border-color: var(--border2); transform: scale(1.08); }
    .t-sun { display: none; }
    [data-theme="light"] .t-sun  { display: block; }
    [data-theme="light"] .t-moon { display: none; }

    @keyframes up {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .page-foot {
      position: relative; z-index: 1;
      margin-top: 24px;
      font-family: 'Playfair Display', serif;
      font-style: italic; font-size: 11px;
      color: var(--txt3); opacity: .4;
    }

    @media (max-width: 820px) {
      body { padding: 20px 16px; }
      .card { flex-direction: column; border-radius: 14px; }
      .card-left { flex: none; padding: 36px 32px 32px; border-right: none; border-bottom: 1px solid var(--border); }
      .card-left::after { display: none; }
      .l-desc, .feats { display: none; }
      .headline { font-size: 28px; min-height: auto; }
      .card-right { padding: 36px 32px; }
    }
  </style>
</head>
<body>

<div class="bg-deco">01</div>

<div class="card">

  <!-- CARD LEFT -->
  <div class="card-left">
    <div>
      <div class="l-rule"></div>
      <div class="l-tag">PAP Market &mdash; Sistema de Gestao</div>
      <h1 class="headline">
        <span id="tw-el"></span><span class="tw-cursor" id="tw-cur"></span>
      </h1>
      <p class="l-desc" id="l-desc">
        Uma plataforma completa para controlo de vendas,
        inventario, equipa e analise financeira.
      </p>
      <ul class="feats" id="feats">
        <li>Dashboard e KPIs em tempo real</li>
        <li>Gestao de stock e validades</li>
        <li>Ponto de venda integrado</li>
        <li>Relatorios e previsoes</li>
        <li>Gestao de equipa e turnos</li>
      </ul>
    </div>
    <div class="l-foot">&copy; <?php echo date('Y'); ?> PAP Market</div>
  </div>

  <!-- CARD RIGHT -->
  <div class="card-right">
  <div class="form-box">
    <div class="f-eyebrow">Acesso ao sistema</div>
    <h2 class="f-title">Bom regresso.</h2>
    <p class="f-sub">Autentique-se para continuar.</p>

    <?php if ($error): ?>
    <div class="err">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="fld">
        <label class="fld-lbl" for="email">Email</label>
        <input type="email" id="email" name="email"
               placeholder="nome@exemplo.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               autocomplete="email" required>
      </div>

      <div class="fld">
        <label class="fld-lbl" for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password"
                 placeholder="A sua password"
                 autocomplete="current-password" required>
          <button type="button" class="pw-btn" onclick="togglePw()" title="Mostrar password">
            <svg id="eye-on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eye-off" style="display:none" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>

      <!-- Custom toggle checkbox -->
      <div class="remember-row">
        <input type="checkbox" id="remember" name="remember" value="1">
        <label class="toggle-track" for="remember">
          <span class="toggle-pill"></span>
          <span class="toggle-label-txt">Manter sessao iniciada</span>
        </label>
      </div>

      <!-- Premium CTA button -->
      <button type="submit" class="cta">
        <span class="cta-body">Entrar no painel</span>
        <span class="cta-sep"></span>
        <span class="cta-icon">
          <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </button>
    </form>

    <div class="div-row">demonstracao</div>

    <div class="creds">
      <strong>Administrador</strong><br>
      <code>admin@example.com</code>&nbsp;&nbsp;<code>admin123</code>
    </div>

    <p class="f-foot">PAP Market &mdash; Plataforma de Gestao Comercial</p>
  </div>
  </div>

</div><!-- /card -->

<p class="page-foot">&copy; <?php echo date('Y'); ?> PAP Market &mdash; Todos os direitos reservados</p>

<button class="th-btn" onclick="toggleTheme()" title="Tema">
  <span class="t-moon">&#127769;</span>
  <span class="t-sun">&#9728;</span>
</button>

<script src="/assets/js/master-ui.js?v=<?= time() ?>"></script>
<script>
// Typewriter
var lines = [
  { text: 'Gestao', italic: false },
  { text: 'inteligente', italic: true },
  { text: 'do seu negocio.', italic: false }
];
var li = 0, ci = 0;
var el = document.getElementById('tw-el');
var cur = document.getElementById('tw-cur');
var nodes = [];

(function build() {
  el.innerHTML = '';
  nodes = [];
  lines.forEach(function(l, i) {
    if (i > 0) el.appendChild(document.createElement('br'));
    var s = document.createElement('span');
    if (l.italic) {
      s.style.cssText = 'font-style:italic;font-weight:400;color:var(--txt2);font-size:.87em';
    }
    el.appendChild(s);
    nodes.push(s);
  });
})();

function type() {
  if (li >= lines.length) { cur.style.display = 'none'; revealFeats(); return; }
  var line = lines[li];
  if (ci < line.text.length) {
    nodes[li].textContent += line.text[ci++];
    setTimeout(type, ci === 1 ? 100 : 50 + Math.random() * 45);
  } else {
    li++; ci = 0;
    setTimeout(type, li < lines.length ? 170 : 0);
  }
}
setTimeout(type, 500);

function revealFeats() {
  var desc = document.getElementById('l-desc');
  if (desc) desc.classList.add('show');
  document.querySelectorAll('#feats li').forEach(function(el, i) {
    setTimeout(function() { el.classList.add('show'); }, 180 + i * 110);
  });
}

// Password toggle
function togglePw() {
  var inp = document.getElementById('password');
  var on = document.getElementById('eye-on');
  var off = document.getElementById('eye-off');
  var show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  on.style.display = show ? 'none' : '';
  off.style.display = show ? '' : 'none';
}

// Theme
function toggleTheme() {
  var h = document.documentElement;
  var n = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  h.setAttribute('data-theme', n);
  localStorage.setItem('pap-theme', n);
}
</script>
</body>
</html>
