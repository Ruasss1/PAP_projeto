<?php
/**
 * Página de Visualizar Colaborador
 * admin/employees/view.php
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/rh.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$employee_id = $_GET['id'] ?? null;
if (!$employee_id) {
    header('Location: /admin/employees/list.php');
    exit;
}

$employee = get_employee($employee_id);
if (!$employee) {
    header('Location: /admin/employees/list.php');
    exit;
}

$tab = $_GET['tab'] ?? 'info';
$documents = get_employee_documents($employee_id);
$vacation_balance = get_vacation_balance($employee_id);
$schedule = get_employee_schedule($employee_id, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));

if (!is_array($vacation_balance)) {
    $vacation_balance = [
        'total_days' => 0,
        'used_days' => 0,
        'remaining_days' => 0,
    ];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'info') {
    $update_data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'department' => $_POST['department'] ?? '',
        'position' => $_POST['position'] ?? '',
        'base_salary' => $_POST['base_salary'] ?? 0
    ];

    if (update_employee($employee_id, $update_data)) {
        $message = 'Colaborador atualizado com sucesso.';
        $employee = get_employee($employee_id);
    } else {
        $error = 'Erro ao atualizar colaborador.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'documents') {
    if (isset($_FILES['document'])) {
        $document_type = $_POST['document_type'] ?? 'Outro';
        $description = $_POST['description'] ?? '';

        if (upload_employee_document($employee_id, $_FILES['document'], $document_type, $description)) {
            $message = 'Documento carregado com sucesso.';
            $documents = get_employee_documents($employee_id);
        } else {
            $error = 'Erro ao carregar documento.';
        }
    }
}

$status_class = strtolower($employee['status'] ?? 'inativo');
?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/css/design-system.css?v=<?= time() ?>">
    <title>Colaborador: <?php echo htmlspecialchars($employee['name']); ?> &mdash; PAP Market</title>
    <script>(function(){var t=localStorage.getItem('pap-theme')||'dark';document.documentElement.setAttribute('data-theme',t)})();</script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #060606; --bg2: #0b0b0b; --bg3: #131313;
            --border: #1a1a1a; --border2: #2a2a2a;
            --txt: #ececec; --txt2: #7a7a7a; --txt3: #3e3e3e;
            --btn: #ececec; --btn-txt: #060606;
            --success: #10b981; --warning: #f59e0b; --danger: #f87171;
        }
        [data-theme="light"] {
            --bg: #f5f4f1; --bg2: #ffffff; --bg3: #eeecea;
            --border: #dedad5; --border2: #c8c4be;
            --txt: #111111; --txt2: #5c5c5c; --txt3: #aaaaaa;
            --btn: #111111; --btn-txt: #ffffff;
            --success: #16a34a; --warning: #ca8a04; --danger: #dc2626;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: var(--bg);
            color: var(--txt);
            padding: 30px 20px 60px;
        }
        .wrap { max-width: 1000px; margin: 0 auto; }
        .back-link { margin-bottom: 20px; }
        .back-link a {
            font-size: 11px; font-weight: 600; letter-spacing: .14em;
            text-transform: uppercase; color: var(--txt2); text-decoration: none;
        }
        .back-link a:hover { color: var(--txt); }
        .head {
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px; margin-bottom: 24px;
        }
        .head h1 {
            font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
            font-size: clamp(24px, 3vw, 36px);
            font-weight: 900; letter-spacing: -.02em;
        }
        .status {
            padding: 7px 12px; border-radius: 999px;
            font-size: 10px; font-weight: 700; letter-spacing: .13em;
            text-transform: uppercase; border: 1px solid;
        }
        .status-ativo { color: var(--success); border-color: var(--success); background: rgba(16,185,129,.08); }
        .status-inativo { color: var(--danger); border-color: var(--danger); background: rgba(248,113,113,.08); }
        .status-licença, .status-licenca { color: var(--warning); border-color: var(--warning); background: rgba(245,158,11,.08); }

        .msg {
            border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;
            border-left: 2px solid; font-size: 13px;
        }
        .msg.ok { color: var(--success); background: rgba(16,185,129,.08); border-color: var(--success); }
        .msg.err { color: var(--danger); background: rgba(248,113,113,.08); border-color: var(--danger); }

        .tabs {
            display: flex; gap: 0; border-bottom: 1px solid var(--border);
            margin-bottom: 18px; overflow-x: auto;
        }
        .tab-link {
            padding: 12px 16px; border: none; background: transparent;
            color: var(--txt2); cursor: pointer;
            font-size: 12px; letter-spacing: .06em;
            border-bottom: 2px solid transparent;
        }
        .tab-link.active { color: var(--txt); border-bottom-color: var(--txt); }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .card {
            background: var(--bg-tertiary); border: 1px solid var(--border-light); color: var(--text-primary);
            border-radius: 12px; padding: 20px; margin-bottom: 16px;
        }
        .card h3 {
            font-size: 11px; text-transform: uppercase; letter-spacing: .14em;
            color: var(--txt3); margin-bottom: 14px;
        }
        .info-row, .form-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
        }
        .info-row { margin-bottom: 14px; }
        .info-item strong {
            display: block; margin-bottom: 4px;
            color: var(--txt3); font-size: 10px; letter-spacing: .12em;
            text-transform: uppercase;
        }

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

        .btn {
            height: 46px; border: none; border-radius: 10px; cursor: pointer;
            background: var(--btn); color: var(--btn-txt);
            padding: 0 18px; font-size: 11px; font-weight: 700;
            letter-spacing: .13em; text-transform: uppercase;
        }

        .table { width: 100%; border-collapse: collapse; }
        .table th {
            text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border);
            color: var(--txt3); font-size: 10px; text-transform: uppercase; letter-spacing: .12em;
        }
        .table td { padding: 10px 8px; border-bottom: 1px solid var(--border); }

        .doc-item {
            background: var(--bg3); border: 1px solid var(--border);
            border-radius: 8px; padding: 12px; margin-bottom: 10px;
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
            .info-row, .form-row { grid-template-columns: 1fr; }
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

    <div class="head">
        <h1><?php echo htmlspecialchars($employee['name']); ?></h1>
        <span class="status status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($employee['status'] ?? 'N/A'); ?></span>
    </div>

    <?php if ($message): ?><div class="msg ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="tabs">
        <button class="tab-link <?php echo $tab === 'info' ? 'active' : ''; ?>" onclick="switchTab('info', this)">Informações</button>
        <button class="tab-link <?php echo $tab === 'documents' ? 'active' : ''; ?>" onclick="switchTab('documents', this)">Documentos</button>
        <button class="tab-link <?php echo $tab === 'schedule' ? 'active' : ''; ?>" onclick="switchTab('schedule', this)">Horários</button>
        <button class="tab-link <?php echo $tab === 'vacation' ? 'active' : ''; ?>" onclick="switchTab('vacation', this)">Férias</button>
        <button class="tab-link <?php echo $tab === 'payroll' ? 'active' : ''; ?>" onclick="switchTab('payroll', this)">Salário</button>
    </div>

    <div id="info" class="tab-content <?php echo $tab === 'info' ? 'active' : ''; ?>">
        <div class="card">
            <h3>Dados Pessoais</h3>
            <div class="info-row">
                <div class="info-item"><strong>Email</strong><?php echo htmlspecialchars($employee['email']); ?></div>
                <div class="info-item"><strong>NIF</strong><?php echo htmlspecialchars($employee['nif']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-item"><strong>Telefone</strong><?php echo htmlspecialchars($employee['phone'] ?? '-'); ?></div>
                <div class="info-item"><strong>Morada</strong><?php echo htmlspecialchars($employee['address'] ?? '-'); ?></div>
            </div>
        </div>

        <div class="card">
            <h3>Dados Profissionais</h3>
            <div class="info-row">
                <div class="info-item"><strong>Departamento</strong><?php echo htmlspecialchars($employee['department']); ?></div>
                <div class="info-item"><strong>Cargo</strong><?php echo htmlspecialchars($employee['position']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-item"><strong>Tipo de Contrato</strong><?php echo htmlspecialchars($employee['contract_type'] ?? '-'); ?></div>
                <div class="info-item"><strong>Salário Base</strong><?php echo number_format($employee['base_salary'] ?? 0, 2, ',', '.'); ?>€</div>
            </div>
            <div class="info-row">
                <div class="info-item"><strong>Data de Contratação</strong><?php echo date('d/m/Y', strtotime($employee['hire_date'])); ?></div>
                <div class="info-item"><strong>Data de Criação</strong><?php echo date('d/m/Y H:i', strtotime($employee['created_at'])); ?></div>
            </div>
        </div>

        <div class="card">
            <h3>Editar Informações</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nome Completo</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($employee['name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="department">Departamento</label>
                        <select id="department" name="department">
                            <option value="Caixa" <?php echo $employee['department'] === 'Caixa' ? 'selected' : ''; ?>>Caixa</option>
                            <option value="Reposição" <?php echo $employee['department'] === 'Reposição' ? 'selected' : ''; ?>>Reposição</option>
                            <option value="Limpeza" <?php echo $employee['department'] === 'Limpeza' ? 'selected' : ''; ?>>Limpeza</option>
                            <option value="Administração" <?php echo $employee['department'] === 'Administração' ? 'selected' : ''; ?>>Administração</option>
                            <option value="Gerência" <?php echo $employee['department'] === 'Gerência' ? 'selected' : ''; ?>>Gerência</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="position">Cargo</label>
                        <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="base_salary">Salário Base (€)</label>
                        <input type="number" id="base_salary" name="base_salary" step="0.01" value="<?php echo number_format($employee['base_salary'] ?? 0, 2, '.', ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Morada</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn">Guardar Alterações</button>
            </form>
        </div>
    </div>

    <div id="documents" class="tab-content <?php echo $tab === 'documents' ? 'active' : ''; ?>">
        <div class="card">
            <h3>Documentos do Colaborador</h3>
            <?php if (!empty($documents)): ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="doc-item">
                        <strong><?php echo htmlspecialchars($doc['document_type']); ?></strong> - <?php echo htmlspecialchars($doc['file_name']); ?><br>
                        <small>Carregado em <?php echo date('d/m/Y H:i', strtotime($doc['uploaded_at'])); ?><?php if ($doc['description']): ?> - <?php echo htmlspecialchars($doc['description']); ?><?php endif; ?></small><br>
                        <small>Tamanho: <?php echo number_format($doc['file_size'] / 1024, 0); ?> KB</small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Nenhum documento carregado ainda.</p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3>Carregar Novo Documento</h3>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="document_type">Tipo de Documento</label>
                    <select id="document_type" name="document_type" required>
                        <option value="Contrato">Contrato</option>
                        <option value="CV">CV</option>
                        <option value="Seguro">Seguro</option>
                        <option value="Certificado">Certificado</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="document">Arquivo</label>
                    <input type="file" id="document" name="document" required accept=".pdf,.doc,.docx,.jpg,.png">
                </div>
                <div class="form-group">
                    <label for="description">Descrição</label>
                    <textarea id="description" name="description" placeholder="Descrição adicional (opcional)"></textarea>
                </div>
                <button type="submit" class="btn">Carregar Documento</button>
            </form>
        </div>
    </div>

    <div id="schedule" class="tab-content <?php echo $tab === 'schedule' ? 'active' : ''; ?>">
        <div class="card">
            <h3>Horários Próximos 30 Dias</h3>
            <?php if (!empty($schedule)): ?>
                <table class="table">
                    <thead>
                    <tr><th>Data</th><th>Turno</th><th>Hora Início</th><th>Hora Fim</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($schedule as $s): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($s['schedule_date'])); ?></td>
                            <td><?php echo htmlspecialchars($s['shift_name']); ?></td>
                            <td><?php echo substr($s['start_time'], 0, 5); ?></td>
                            <td><?php echo substr($s['end_time'], 0, 5); ?></td>
                            <td><?php echo htmlspecialchars($s['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhum horário agendado nos próximos 30 dias.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="vacation" class="tab-content <?php echo $tab === 'vacation' ? 'active' : ''; ?>">
        <div class="card">
            <h3>Saldo de Férias</h3>
            <div class="info-row">
                <div class="info-item"><strong>Total de Dias</strong><?php echo $vacation_balance['total_days']; ?> dias</div>
                <div class="info-item"><strong>Dias Usados</strong><?php echo $vacation_balance['used_days']; ?> dias</div>
            </div>
            <div class="info-row">
                <div class="info-item"><strong>Dias Restantes</strong><?php echo $vacation_balance['remaining_days']; ?> dias</div>
                <div class="info-item"><strong>Ano</strong><?php echo date('Y'); ?></div>
            </div>
        </div>
    </div>

    <div id="payroll" class="tab-content <?php echo $tab === 'payroll' ? 'active' : ''; ?>">
        <div class="card">
            <h3>Informações de Salário</h3>
            <div class="info-row">
                <div class="info-item"><strong>Salário Base</strong><?php echo number_format($employee['base_salary'] ?? 0, 2, ',', '.'); ?>€/mês</div>
                <div class="info-item"><strong>Tipo de Contrato</strong><?php echo htmlspecialchars($employee['contract_type'] ?? '-'); ?></div>
            </div>
            <p style="color: var(--txt2); margin-top: 10px;">Folhas de pagamento e comissões podem ser geridas no módulo de Administração.</p>
        </div>
    </div>
</div>

<button class="th-btn" onclick="toggleTheme()" title="Tema">
    <span class="t-moon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
    <span class="t-sun"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>
</button>

<script src="/assets/js/master-ui.js?v=<?= time() ?>"></script>
<script>
function switchTab(tabName, button) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-link').forEach(el => el.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    if (button) button.classList.add('active');
    window.history.pushState({tab: tabName}, '', '?id=<?php echo $employee_id; ?>&tab=' + tabName);
}

function toggleTheme() {
    var h = document.documentElement;
    var n = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    h.setAttribute('data-theme', n);
    localStorage.setItem('pap-theme', n);
}
</script>
</body>
</html>
