<?php
/**
 * MÓDULO RH - INTERFACE DE GESTÃO
 * Ficheiro: admin/rh/employees.php
 * 
 * Gestão completa de colaboradores:
 * - Visualizar/Criar/Editar colaboradores
 * - Gestão de turnos e férias
 * - Cálculo de salários
 */

session_start();
require_once __DIR__ . '/../../includes/auth_middleware.php';
$page_title = 'Colaboradores';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../modules/rh.php';

// Verificar permissões (apenas admin/gerente)
if (!in_array($_SESSION['role_id'] ?? null, [1, 2])) {
    die('Acesso negado');
}

$pdo = db_connect();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'save') {
    $data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'nif' => $_POST['nif'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
        'hire_date' => $_POST['hire_date'] ?? date('Y-m-d'),
        'contract_type' => $_POST['contract_type'] ?? 'Permanente',
        'department' => $_POST['department'] ?? '',
        'position' => $_POST['position'] ?? '',
        'base_salary' => $_POST['base_salary'] ?? 0
    ];
    
    if (!empty($_POST['employee_id'])) {
        // Editar
        $stmt = $pdo->prepare("
            UPDATE employees SET 
            name = :name, email = :email, nif = :nif, phone = :phone,
            address = :address, hire_date = :hire_date, contract_type = :contract_type,
            department = :department, position = :position, base_salary = :base_salary
            WHERE id = :id
        ");
        $stmt->execute(array_merge($data, [':id' => $_POST['employee_id']]));
    } else {
        // Criar
        create_employee($data);
    }
    
    header('Location: ?action=list&success=1');
    exit;
}

?>

<div class="rh-container">
    <div class="rh-header">
        <h1>👥 Gestão de Recursos Humanos</h1>
        <?php if ($action == 'list'): ?>
            <button class="btn btn-primary" onclick="location.href='?action=new'">
                ➕ Novo Colaborador
            </button>
        <?php endif; ?>
    </div>

    <?php if ($action == 'list'): ?>
        <div class="employees-list">
            <?php
            $stmt = $pdo->prepare("
                SELECT id, name, position, department, email, phone, 
                       base_salary, status, hire_date
                FROM employees
                ORDER BY name
            ");
            $stmt->execute();
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($employees)) {
                echo "<p>Nenhum colaborador registado.</p>";
            } else {
                echo "<table class='employees-table'>";
                echo "<thead><tr>";
                echo "<th>Nome</th><th>Cargo</th><th>Departamento</th>";
                echo "<th>Email</th><th>Telefone</th><th>Salário</th><th>Status</th>";
                echo "<th>Ações</th>";
                echo "</tr></thead><tbody>";
                
                foreach ($employees as $emp) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($emp['name']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($emp['position']) . "</td>";
                    echo "<td>" . htmlspecialchars($emp['department']) . "</td>";
                    echo "<td>" . htmlspecialchars($emp['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($emp['phone'] ?? '-') . "</td>";
                    echo "<td>" . number_format($emp['base_salary'], 2, ',', '.') . "€</td>";
                    echo "<td>";
                    if ($emp['status'] == 'Ativo') {
                        echo "<span class='badge badge-success'>Ativo</span>";
                    } else {
                        echo "<span class='badge badge-danger'>Inativo</span>";
                    }
                    echo "</td>";
                    echo "<td>";
                    echo "<a href='?action=edit&id=" . $emp['id'] . "' class='btn btn-sm btn-info'>Editar</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
            }
            ?>
        </div>

    <?php elseif ($action == 'new' || $action == 'edit'): ?>
        <?php
        $employee = null;
        if ($action == 'edit' && isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
            $stmt->execute([':id' => $_GET['id']]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        ?>
        
        <form method="POST" class="employee-form">
            <input type="hidden" name="action" value="save">
            <?php if ($employee): ?>
                <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nome Completo *</label>
                <input type="text" name="name" required value="<?= $employee['name'] ?? '' ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= $employee['email'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>NIF</label>
                    <input type="text" name="nif" value="<?= $employee['nif'] ?? '' ?>" maxlength="9" minlength="9" pattern="[0-9]{9}" placeholder="123456789" title="O NIF deve ter exatamente 9 dígitos">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="tel" name="phone" value="<?= $employee['phone'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Data de Admissão</label>
                    <input type="date" name="hire_date" value="<?= $employee['hire_date'] ?? date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Morada</label>
                <input type="text" name="address" value="<?= $employee['address'] ?? '' ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Departamento</label>
                    <select name="department">
                        <option>Selecione...</option>
                        <option <?= ($employee['department'] ?? '') == 'Caixa' ? 'selected' : '' ?>>Caixa</option>
                        <option <?= ($employee['department'] ?? '') == 'Armazém' ? 'selected' : '' ?>>Armazém</option>
                        <option <?= ($employee['department'] ?? '') == 'Gestão' ? 'selected' : '' ?>>Gestão</option>
                        <option <?= ($employee['department'] ?? '') == 'Limpeza' ? 'selected' : '' ?>>Limpeza</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cargo</label>
                    <input type="text" name="position" value="<?= $employee['position'] ?? '' ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Contrato</label>
                    <select name="contract_type">
                        <option <?= ($employee['contract_type'] ?? 'Permanente') == 'Permanente' ? 'selected' : '' ?>>Permanente</option>
                        <option <?= ($employee['contract_type'] ?? '') == 'Termo Certo' ? 'selected' : '' ?>>Termo Certo</option>
                        <option <?= ($employee['contract_type'] ?? '') == 'Estágio' ? 'selected' : '' ?>>Estágio</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salário Base (€)</label>
                    <input type="number" name="base_salary" step="0.01" value="<?= $employee['base_salary'] ?? 0 ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="?action=list" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
.rh-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.rh-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.employees-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
}

.employees-table th {
    background: var(--accent);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
}

.employees-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

.employees-table tr:hover {
    background: var(--hover-bg);
}

.employee-form {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 8px;
    max-width: 600px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text);
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}
</style>
