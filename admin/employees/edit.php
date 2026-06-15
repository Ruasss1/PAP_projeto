<?php
/**
 * Página de Editar Colaborador
 * admin/employees/edit.php
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

// Obter ID do colaborador
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$employee_id) {
    header('Location: /admin/employees/list.php');
    exit;
}

// Buscar dados do colaborador
$employee = get_employee($employee_id);

if (!$employee) {
    header('Location: /admin/employees/list.php?error=Colaborador não encontrado');
    exit;
}

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
        'base_salary' => $_POST['base_salary'] ?? 0,
        'status' => $_POST['status'] ?? 'Ativo'
    ];
    
    // Validações
    if (empty($data['name'])) {
        $error = 'Nome é obrigatório.';
    } elseif (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email válido é obrigatório.';
    } elseif (empty($data['nif'])) {
        $error = 'NIF é obrigatório.';
    } else {
        // Atualizar colaborador
        if (update_employee($employee_id, $data)) {
            $message = 'Colaborador atualizado com sucesso!';
            // Recarregar dados
            $employee = get_employee($employee_id);
        } else {
            $error = 'Erro ao atualizar colaborador.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/css/design-system.css?v=<?= time() ?>">
    <title>Editar Colaborador - <?= htmlspecialchars($employee['name']) ?></title>
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
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: var(--bg);
            color: var(--txt);
            padding: 30px 20px 60px;
        }
        .wrap { max-width: 900px; margin: 0 auto; }
        .back-link { margin-bottom: 20px; }
        .back-link a {
            font-size: 11px; font-weight: 600; letter-spacing: .14em;
            text-transform: uppercase; color: var(--txt2); text-decoration: none;
        }
        .back-link a:hover { color: var(--txt); }
        h1 {
            font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
            font-size: clamp(24px, 3vw, 36px);
            font-weight: 900; letter-spacing: -.02em;
            margin-bottom: 16px;
        }
        .employee-meta {
            margin-bottom: 18px; color: var(--txt2);
            font-size: 13px;
        }
        .status-badge {
            display: inline-flex; align-items: center;
            padding: 6px 10px; border-radius: 999px;
            font-size: 10px; letter-spacing: .1em; text-transform: uppercase;
            border: 1px solid var(--border2);
            margin-left: 8px;
        }
        .msg {
            border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;
            border-left: 2px solid; font-size: 13px;
        }
        .msg.ok { color: var(--success); background: rgba(16,185,129,.08); border-color: var(--success); }
        .msg.err { color: var(--danger); background: rgba(248,113,113,.08); border-color: var(--danger); }

        .card {
            background: var(--bg-tertiary); border: 1px solid var(--border-light); color: var(--text-primary);
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

        .actions { display: flex; gap: 10px; }
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
            background: var(--bg-tertiary); border: 1px solid var(--border-light); color: var(--text-primary);
            border-radius: 50%; display: grid; place-items: center;
            cursor: pointer;
        }
        .t-sun { display: none; }
        [data-theme="light"] .t-sun { display: block; }
        [data-theme="light"] .t-moon { display: none; }

        @media (max-width: 700px) {
            .form-row { grid-template-columns: 1fr; }
            .actions { flex-direction: column; }
            .btn { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="back-link">
        <a href="/admin/rh/equipa.php" style="display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:var(--txt3);text-decoration:none;border:1px solid var(--border);border-radius:8px;padding:9px 16px;background:var(--bg2);transition:border-color .2s,color .2s,transform .2s;" onmouseover="this.style.borderColor='var(--border2)';this.style.color='var(--txt2)';this.style.transform='translateX(-3px)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--txt3)';this.style.transform='translateX(0)'">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Voltar à Equipa
        </a>
    </div>

    <h1>Editar Colaborador</h1>
    <div class="employee-meta">
        <?= htmlspecialchars($employee['name']) ?> • <?= htmlspecialchars($employee['department'] ?? 'N/A') ?> • <?= htmlspecialchars($employee['position'] ?? 'N/A') ?>
        <span class="status-badge"><?= htmlspecialchars($employee['status'] ?? 'N/A') ?></span>
    </div>

    <?php if ($message): ?><div class="msg ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>Dados do Colaborador</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($employee['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($employee['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>NIF *</label>
                    <input type="text" name="nif" value="<?= htmlspecialchars($employee['nif'] ?? '') ?>" maxlength="9" required>
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Morada</label>
                <input type="text" name="address" value="<?= htmlspecialchars($employee['address'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Data de Contratação</label>
                    <input type="date" name="hire_date" value="<?= htmlspecialchars($employee['hire_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Tipo de Contrato</label>
                    <select name="contract_type">
                        <option value="Permanente" <?= ($employee['contract_type'] ?? '') === 'Permanente' ? 'selected' : '' ?>>Permanente</option>
                        <option value="Temporário" <?= ($employee['contract_type'] ?? '') === 'Temporário' ? 'selected' : '' ?>>Temporário</option>
                        <option value="Part-time" <?= ($employee['contract_type'] ?? '') === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                        <option value="Estágio" <?= ($employee['contract_type'] ?? '') === 'Estágio' ? 'selected' : '' ?>>Estágio</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Departamento *</label>
                    <select name="department" required>
                        <option value="">Selecionar...</option>
                        <option value="Operações" <?= ($employee['department'] ?? '') === 'Operações' ? 'selected' : '' ?>>Operações</option>
                        <option value="Caixa" <?= ($employee['department'] ?? '') === 'Caixa' ? 'selected' : '' ?>>Caixa</option>
                        <option value="Reposição" <?= ($employee['department'] ?? '') === 'Reposição' ? 'selected' : '' ?>>Reposição</option>
                        <option value="Administração" <?= ($employee['department'] ?? '') === 'Administração' ? 'selected' : '' ?>>Administração</option>
                        <option value="Logística" <?= ($employee['department'] ?? '') === 'Logística' ? 'selected' : '' ?>>Logística</option>
                        <option value="Limpeza" <?= ($employee['department'] ?? '') === 'Limpeza' ? 'selected' : '' ?>>Limpeza</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cargo *</label>
                    <input type="text" name="position" value="<?= htmlspecialchars($employee['position'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Salário Base (€)</label>
                    <input type="number" name="base_salary" step="0.01" min="0" value="<?= htmlspecialchars($employee['base_salary'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Ativo" <?= ($employee['status'] ?? '') === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="Inativo" <?= ($employee['status'] ?? '') === 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="Licença" <?= ($employee['status'] ?? '') === 'Licença' ? 'selected' : '' ?>>Licença</option>
                    </select>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Guardar Alterações</button>
                <a href="/admin/employees/view.php?id=<?= $employee_id ?>" class="btn btn-ghost">Ver Perfil</a>
            </div>
        </form>
    </div>
</div>

<button class="th-btn" onclick="toggleTheme()" title="Tema">
    <span class="t-moon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
    <span class="t-sun"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>
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
