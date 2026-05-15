<?php
/**
 * Página de Criar Novo Colaborador
 * admin/employees/create.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/rh.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$message = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'nif' => $_POST['nif'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'hire_date' => $_POST['hire_date'] ?? '',
        'contract_type' => $_POST['contract_type'] ?? 'Permanente',
        'department' => $_POST['department'] ?? '',
        'position' => $_POST['position'] ?? '',
        'base_salary' => $_POST['base_salary'] ?? 0
    ];
    
    // Validações
    if (empty($data['name'])) {
        $error = 'Nome é obrigatório.';
    } elseif (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email válido é obrigatório.';
    } elseif (empty($data['nif'])) {
        $error = 'NIF é obrigatório.';
    } elseif (empty($data['hire_date'])) {
        $error = 'Data de contratação é obrigatória.';
    } elseif (empty($data['department'])) {
        $error = 'Departamento é obrigatório.';
    } elseif (empty($data['position'])) {
        $error = 'Cargo é obrigatório.';
    } else {
        // Criar colaborador
        $result = create_employee($data);
        if ($result) {
            $message = 'Colaborador criado com sucesso!';
            // Redirecionar após 2 segundos
            header('Refresh: 2; url=/admin/employees/view.php?id=' . $result['id']);
        } else {
            $error = 'Erro ao criar colaborador. Verifique se o email ou NIF já existe.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Colaborador &mdash; PAP Market</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/master-ui.css?v=<?= time() ?>">
    <script>(function(){var t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t)})();</script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #060606; --bg2: #0b0b0b; --bg3: #131313;
            --border: #1a1a1a; --border2: #2a2a2a;
            --txt: #ececec; --txt2: #7a7a7a; --txt3: #3e3e3e;
            --btn: #ececec; --btn-txt: #060606;
            --success: #10b981; --danger: #f87171;
        }
        [data-theme="light"] {
            --bg: #f5f4f1; --bg2: #ffffff; --bg3: #eeecea;
            --border: #dedad5; --border2: #c8c4be;
            --txt: #111111; --txt2: #5c5c5c; --txt3: #aaaaaa;
            --btn: #111111; --btn-txt: #ffffff;
            --success: #16a34a; --danger: #dc2626;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            background: var(--bg);
            color: var(--txt);
            padding: 30px 20px 60px;
        }
        .wrap { max-width: 860px; margin: 0 auto; }
        .back-link { margin-bottom: 20px; }
        .back-link a {
            font-size: 11px; font-weight: 600; letter-spacing: .14em;
            text-transform: uppercase; color: var(--txt2); text-decoration: none;
        }
        .back-link a:hover { color: var(--txt); }
        h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(24px, 3vw, 36px);
            font-weight: 900; letter-spacing: -.02em;
            margin-bottom: 20px;
        }
        .msg {
            border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;
            border-left: 2px solid; font-size: 13px;
        }
        .msg.ok { color: var(--success); background: rgba(16,185,129,.08); border-color: var(--success); }
        .msg.err { color: var(--danger); background: rgba(248,113,113,.08); border-color: var(--danger); }

        .card {
            background: var(--bg2); border: 1px solid var(--border);
            border-radius: 12px; padding: 20px; margin-bottom: 16px;
        }
        .card h3 {
            font-size: 11px; text-transform: uppercase; letter-spacing: .14em;
            color: var(--txt3); margin-bottom: 14px;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block; margin-bottom: 6px; color: var(--txt3);
            font-size: 10px; letter-spacing: .12em; text-transform: uppercase;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%; border: 1px solid var(--border); border-radius: 8px;
            background: var(--bg3); color: var(--txt);
            padding: 12px 13px; font-size: 14px; font-family: inherit;
            outline: none;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color: var(--border2); }
        .form-group textarea { min-height: 88px; resize: vertical; }

        .actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn {
            height: 46px; border-radius: 10px; cursor: pointer;
            padding: 0 18px; font-size: 11px; font-weight: 700;
            letter-spacing: .13em; text-transform: uppercase;
            text-decoration: none; display: inline-flex; align-items: center;
        }
        .btn-ghost {
            border: 1px solid var(--border); color: var(--txt2); background: transparent;
        }
        .btn-primary {
            border: none; background: var(--btn); color: var(--btn-txt);
        }

        .th-btn {
            position: fixed; bottom: 20px; right: 20px;
            width: 36px; height: 36px;
            background: var(--bg2); border: 1px solid var(--border);
            border-radius: 50%; display: grid; place-items: center;
            cursor: pointer;
        }
        .t-sun { display: none; }
        [data-theme="light"] .t-sun { display: block; }
        [data-theme="light"] .t-moon { display: none; }

        @media (max-width: 700px) {
            .form-row { grid-template-columns: 1fr; }
            .actions { justify-content: stretch; flex-direction: column; }
            .btn { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="back-link">
        <a href="/admin/employees/list.php">← Voltar à Lista de Colaboradores</a>
    </div>

    <h1>Novo Colaborador</h1>

    <?php if ($message): ?><div class="msg ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="post">
        <div class="card">
            <h3>Dados Pessoais</h3>
            <div class="form-group">
                <label for="name">Nome Completo *</label>
                <input type="text" id="name" name="name" required placeholder="João Silva" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required placeholder="joao@exemplo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="nif">NIF *</label>
                    <input type="text" id="nif" name="nif" required placeholder="123456789" maxlength="9" minlength="9" pattern="[0-9]{9}" title="O NIF deve ter exatamente 9 dígitos" value="<?php echo htmlspecialchars($_POST['nif'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Telefone</label>
                    <input type="tel" id="phone" name="phone" placeholder="+351 96 123 4567" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="hire_date">Data de Contratação *</label>
                    <input type="date" id="hire_date" name="hire_date" required value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Morada</label>
                <textarea id="address" name="address" placeholder="Rua, nº, Código Postal, Cidade"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="card">
            <h3>Dados Profissionais</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="department">Departamento *</label>
                    <select id="department" name="department" required>
                        <option value="">Selecionar...</option>
                        <option value="Caixa" <?php echo (($_POST['department'] ?? '') === 'Caixa') ? 'selected' : ''; ?>>Caixa</option>
                        <option value="Reposição" <?php echo (($_POST['department'] ?? '') === 'Reposição') ? 'selected' : ''; ?>>Reposição</option>
                        <option value="Limpeza" <?php echo (($_POST['department'] ?? '') === 'Limpeza') ? 'selected' : ''; ?>>Limpeza</option>
                        <option value="Administração" <?php echo (($_POST['department'] ?? '') === 'Administração') ? 'selected' : ''; ?>>Administração</option>
                        <option value="Gerência" <?php echo (($_POST['department'] ?? '') === 'Gerência') ? 'selected' : ''; ?>>Gerência</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="position">Cargo *</label>
                    <input type="text" id="position" name="position" required placeholder="Ex: Assistente de Caixa" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contract_type">Tipo de Contrato</label>
                    <select id="contract_type" name="contract_type">
                        <option value="Permanente" <?php echo (($_POST['contract_type'] ?? 'Permanente') === 'Permanente') ? 'selected' : ''; ?>>Permanente</option>
                        <option value="Termo Certo" <?php echo (($_POST['contract_type'] ?? '') === 'Termo Certo') ? 'selected' : ''; ?>>Termo Certo</option>
                        <option value="Termo Incerto" <?php echo (($_POST['contract_type'] ?? '') === 'Termo Incerto') ? 'selected' : ''; ?>>Termo Incerto</option>
                        <option value="Estágio" <?php echo (($_POST['contract_type'] ?? '') === 'Estágio') ? 'selected' : ''; ?>>Estágio</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="base_salary">Salário Base (€) *</label>
                    <input type="number" id="base_salary" name="base_salary" required step="0.01" placeholder="800.00" value="<?php echo htmlspecialchars($_POST['base_salary'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="actions">
            <a href="/admin/employees/list.php" class="btn btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary">Criar Colaborador</button>
        </div>
    </form>
</div>

<button class="th-btn" onclick="toggleTheme()" title="Tema">
    <span class="t-moon">🌙</span>
    <span class="t-sun">☀️</span>
</button>
<script src="/assets/js/master-ui.js?v=<?= time() ?>"></script>
<script>
function toggleTheme() {
    var h = document.documentElement;
    var n = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    h.setAttribute('data-theme', n);
    localStorage.setItem('pap-theme', n);
}
</script>
</body>
</html>
