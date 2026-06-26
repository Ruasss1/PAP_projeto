<?php
// Página de credenciais de teste — apenas para desenvolvimento local
$users = [
    ['role' => 'Admin',       'name' => 'Administrador',    'email' => 'admin@papmarket.pt',   'pass' => 'admin123',   'color' => '#f87171', 'desc' => 'Acesso total ao sistema'],
    ['role' => 'Gerente',     'name' => 'Gerente Teste',    'email' => 'gerente@papmarket.pt', 'pass' => 'gerente123', 'color' => '#fb923c', 'desc' => 'Relatórios e gestão'],
    ['role' => 'Caixa',       'name' => 'Caixa Teste',      'email' => 'caixa@papmarket.pt',   'pass' => 'caixa123',   'color' => '#34d399', 'desc' => 'Operador de caixa / PDV'],
    ['role' => 'Funcionário', 'name' => 'Funcionário Teste','email' => 'func@papmarket.pt',    'pass' => 'func123',    'color' => '#60a5fa', 'desc' => 'Acesso básico'],
];
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Credenciais — PAP Market</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', system-ui, sans-serif; background: #0a0a0a; color: #eee; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; }
  h1 { font-size: 13px; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: #555; margin-bottom: 32px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; width: 100%; max-width: 900px; }
  .card { background: #111; border: 1px solid #1f1f1f; border-radius: 14px; padding: 24px; position: relative; overflow: hidden; }
  .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--c); }
  .role-badge { font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--c); margin-bottom: 12px; }
  .name { font-size: 17px; font-weight: 600; color: #eee; margin-bottom: 4px; }
  .desc { font-size: 12px; color: #555; margin-bottom: 20px; }
  .field { margin-bottom: 10px; }
  .label { font-size: 10px; color: #444; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 4px; }
  .value { display: flex; align-items: center; justify-content: space-between; background: #0d0d0d; border: 1px solid #1a1a1a; border-radius: 8px; padding: 9px 12px; font-size: 13px; font-family: 'SF Mono', monospace; color: #ccc; }
  .copy-btn { font-size: 10px; color: #444; cursor: pointer; background: none; border: none; padding: 0; transition: color .15s; }
  .copy-btn:hover { color: #aaa; }
  .login-btn { display: block; text-align: center; margin-top: 16px; padding: 9px; background: #1a1a1a; border: 1px solid #222; border-radius: 8px; font-size: 12px; font-weight: 600; color: #888; text-decoration: none; transition: background .15s, color .15s; }
  .login-btn:hover { background: #222; color: #ccc; }
  .footer { margin-top: 40px; font-size: 11px; color: #333; }
</style>
</head>
<body>
<h1>Credenciais de Teste — PAP Market</h1>
<div class="grid">
<?php foreach ($users as $u): ?>
  <div class="card" style="--c: <?= $u['color'] ?>">
    <div class="role-badge"><?= $u['role'] ?></div>
    <div class="name"><?= $u['name'] ?></div>
    <div class="desc"><?= $u['desc'] ?></div>
    <div class="field">
      <div class="label">Email</div>
      <div class="value">
        <span><?= $u['email'] ?></span>
        <button class="copy-btn" onclick="copy(this,'<?= $u['email'] ?>')">copiar</button>
      </div>
    </div>
    <div class="field">
      <div class="label">Password</div>
      <div class="value">
        <span><?= $u['pass'] ?></span>
        <button class="copy-btn" onclick="copy(this,'<?= $u['pass'] ?>')">copiar</button>
      </div>
    </div>
    <a class="login-btn" href="/login.php">Ir para o Login →</a>
  </div>
<?php endforeach; ?>
</div>
<div class="footer">localhost:8000/credenciais.php · apenas desenvolvimento local</div>
<script>
function copy(btn, text) {
  navigator.clipboard.writeText(text);
  btn.textContent = 'copiado!';
  setTimeout(() => btn.textContent = 'copiar', 1500);
}
</script>
</body>
</html>
