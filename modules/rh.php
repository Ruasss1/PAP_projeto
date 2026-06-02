<?php
/**
 * Módulo de Gestão de Recursos Humanos (RH)
 * 
 * Funcionalidades:
 * - Gestão de Colaboradores
 * - Horários e Turnos
 * - Férias e Faltas
 * - Salários e Comissões
 * - Auditoria
 */

// =====================================
// 1. FUNÇÕES DE COLABORADORES
// =====================================

/**
 * Criar novo colaborador
 * @return array|false Dados do colaborador criado ou false
 */
function create_employee($data) {
    global $pdo;
    
    try {
        $position    = $data['position']    ?? '';
        $base_salary = floatval($data['base_salary'] ?? 0);

        $query = "INSERT INTO employees (
            name, email, nif, phone, address, hire_date,
            contract_type, department, position, role, base_salary, salary, status, created_by
        ) VALUES (
            :name, :email, :nif, :phone, :address, :hire_date,
            :contract_type, :department, :position, :role, :base_salary, :salary, :status, :created_by
        )";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':name'          => $data['name'],
            ':email'         => $data['email'],
            ':nif'           => $data['nif'],
            ':phone'         => $data['phone'] ?? null,
            ':address'       => $data['address'] ?? null,
            ':hire_date'     => $data['hire_date'],
            ':contract_type' => $data['contract_type'] ?? 'Permanente',
            ':department'    => $data['department'],
            ':position'      => $position,
            ':role'          => $position,          // coluna legada — mesmo valor que position
            ':base_salary'   => $base_salary,
            ':salary'        => $base_salary,       // coluna legada — mesmo valor que base_salary
            ':status'        => 'Ativo',
            ':created_by'    => $_SESSION['user_id'] ?? null
        ]);
        
        if ($result) {
            $employee_id = $pdo->lastInsertId();
            
            // Tentar criar saldo de férias (falha silenciosa se tabela não existir)
            try { create_vacation_balance($employee_id, date('Y')); } catch (Exception $e) {}
            
            // Tentar log de auditoria (falha silenciosa se tabela não existir)
            try { log_rh_audit('INSERT', 'employees', $employee_id, $employee_id, null, $data); } catch (Exception $e) {}
            
            return get_employee($employee_id);
        }
        return false;
    } catch (Exception $e) {
        error_log("Erro ao criar colaborador: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter colaborador por ID
 */
function get_employee($employee_id) {
    global $pdo;
    
    try {
        $query = "SELECT * FROM employees WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $employee_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao obter colaborador: " . $e->getMessage());
        return false;
    }
}

/**
 * Listar todos os colaboradores com filtros
 */
function list_employees($filters = []) {
    global $pdo;
    
    try {
        $query = "SELECT * FROM employees WHERE 1=1";
        $params = [];
        
        // Filtros
        if (!empty($filters['status'])) {
            $query .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['department'])) {
            $query .= " AND department = :department";
            $params[':department'] = $filters['department'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (name LIKE :search OR email LIKE :search OR nif LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $query .= " ORDER BY name ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao listar colaboradores: " . $e->getMessage());
        return [];
    }
}

/**
 * Atualizar dados do colaborador
 */
function update_employee($employee_id, $data) {
    global $pdo;
    
    try {
        // Obter valores antigos para auditoria
        $old_data = get_employee($employee_id);
        
        $updates = [];
        $params = [':id' => $employee_id];
        
        $allowed_fields = ['name', 'email', 'phone', 'address', 'department', 
                          'position', 'base_salary', 'status', 'notes'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return $old_data;
        }
        
        $query = "UPDATE employees SET " . implode(', ', $updates) . 
                 ", updated_by = :updated_by WHERE id = :id";
        $params[':updated_by'] = $_SESSION['user_id'] ?? null;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        // Log de auditoria
        log_rh_audit('UPDATE', 'employees', $employee_id, $employee_id, $old_data, $data);
        
        return get_employee($employee_id);
    } catch (Exception $e) {
        error_log("Erro ao atualizar colaborador: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletar colaborador (soft delete)
 */
function delete_employee($employee_id) {
    global $pdo;
    
    try {
        $query = "UPDATE employees SET status = 'Inativo' WHERE id = :id";
        $stmt = $pdo->prepare($query);
        
        // Log de auditoria
        $old_data = get_employee($employee_id);
        log_rh_audit('DELETE', 'employees', $employee_id, $employee_id, $old_data, null);
        
        return $stmt->execute([':id' => $employee_id]);
    } catch (Exception $e) {
        error_log("Erro ao deletar colaborador: " . $e->getMessage());
        return false;
    }
}

/**
 * Upload de documento para colaborador
 */
function upload_employee_document($employee_id, $file, $document_type, $description = '') {
    global $pdo;
    
    try {
        $upload_dir = 'assets/uploads/documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_error = $file['error'];
        $file_size = $file['size'];
        
        // Validações
        if ($file_error !== UPLOAD_ERR_OK) {
            return false;
        }
        
        if ($file_size > 10 * 1024 * 1024) { // 10MB limite
            return false;
        }
        
        // Nome único do arquivo
        $new_file_name = 'emp_' . $employee_id . '_' . time() . '_' . 
                        preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file_name));
        $file_path = $upload_dir . $new_file_name;
        
        if (move_uploaded_file($file_tmp, $file_path)) {
            $query = "INSERT INTO employee_documents (
                employee_id, document_type, file_path, file_name, file_size, 
                mime_type, uploaded_by, description
            ) VALUES (
                :employee_id, :document_type, :file_path, :file_name, :file_size,
                :mime_type, :uploaded_by, :description
            )";
            
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                ':employee_id' => $employee_id,
                ':document_type' => $document_type,
                ':file_path' => $file_path,
                ':file_name' => $file_name,
                ':file_size' => $file_size,
                ':mime_type' => $file['type'],
                ':uploaded_by' => $_SESSION['user_id'] ?? null,
                ':description' => $description
            ]);
            
            return $result ? $pdo->lastInsertId() : false;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erro ao upload de documento: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter documentos do colaborador
 */
function get_employee_documents($employee_id) {
    global $pdo;
    
    try {
        $query = "SELECT * FROM employee_documents 
                 WHERE employee_id = :employee_id 
                 ORDER BY uploaded_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':employee_id' => $employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao obter documentos: " . $e->getMessage());
        return [];
    }
}

// =====================================
// 2. FUNÇÕES DE TURNOS E HORÁRIOS
// =====================================

/**
 * Criar novo turno
 */
function create_shift($data) {
    global $pdo;
    
    try {
        $query = "INSERT INTO shifts (name, start_time, end_time, break_duration, description)
                 VALUES (:name, :start_time, :end_time, :break_duration, :description)";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':name' => $data['name'],
            ':start_time' => $data['start_time'],
            ':end_time' => $data['end_time'],
            ':break_duration' => $data['break_duration'] ?? 60,
            ':description' => $data['description'] ?? null
        ]);
        
        return $result ? $pdo->lastInsertId() : false;
    } catch (Exception $e) {
        error_log("Erro ao criar turno: " . $e->getMessage());
        return false;
    }
}

/**
 * Listar todos os turnos
 */
function list_shifts() {
    global $pdo;
    
    try {
        $query = "SELECT * FROM shifts WHERE status = 'Ativo' ORDER BY start_time ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao listar turnos: " . $e->getMessage());
        return [];
    }
}

/**
 * Atribuir colaborador a turno (agendamento)
 */
function assign_schedule($employee_id, $shift_id, $schedule_date) {
    global $pdo;
    
    try {
        // Verificar conflitos
        $conflicts = check_schedule_conflict($employee_id, $shift_id, $schedule_date);
        if ($conflicts) {
            return ['error' => 'Conflito de horário detectado'];
        }
        
        $query = "INSERT INTO schedules (employee_id, shift_id, schedule_date, status, created_by)
                 VALUES (:employee_id, :shift_id, :schedule_date, 'Confirmado', :created_by)
                 ON DUPLICATE KEY UPDATE 
                 shift_id = :shift_id, status = 'Confirmado'";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':employee_id' => $employee_id,
            ':shift_id' => $shift_id,
            ':schedule_date' => $schedule_date,
            ':created_by' => $_SESSION['user_id'] ?? null
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Erro ao atribuir horário: " . $e->getMessage());
        return false;
    }
}

/**
 * Verificar conflitos de horário
 */
function check_schedule_conflict($employee_id, $shift_id, $schedule_date) {
    global $pdo;
    
    try {
        // Obter horas do turno
        $shift_query = "SELECT start_time, end_time FROM shifts WHERE id = :shift_id";
        $shift_stmt = $pdo->prepare($shift_query);
        $shift_stmt->execute([':shift_id' => $shift_id]);
        $shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar se colaborador tem outro turno no mesmo dia
        $query = "SELECT s.id FROM schedules s
                 INNER JOIN shifts sh ON s.shift_id = sh.id
                 WHERE s.employee_id = :employee_id
                 AND s.schedule_date = :schedule_date
                 AND s.status != 'Cancelado'
                 AND sh.id != :shift_id
                 AND (
                    (sh.start_time < :end_time AND sh.end_time > :start_time)
                 )";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':schedule_date' => $schedule_date,
            ':shift_id' => $shift_id,
            ':start_time' => $shift['start_time'],
            ':end_time' => $shift['end_time']
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Erro ao verificar conflitos: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter horários de um colaborador
 */
function get_employee_schedule($employee_id, $date_from = null, $date_to = null) {
    global $pdo;
    
    try {
        $query = "SELECT s.*, sh.name as shift_name, sh.start_time, sh.end_time
                 FROM schedules s
                 INNER JOIN shifts sh ON s.shift_id = sh.id
                 WHERE s.employee_id = :employee_id";
        
        $params = [':employee_id' => $employee_id];
        
        if ($date_from && $date_to) {
            $query .= " AND s.schedule_date BETWEEN :date_from AND :date_to";
            $params[':date_from'] = $date_from;
            $params[':date_to'] = $date_to;
        }
        
        $query .= " ORDER BY s.schedule_date ASC, sh.start_time ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao obter horários: " . $e->getMessage());
        return [];
    }
}

// =====================================
// 3. FUNÇÕES DE FÉRIAS E FALTAS
// =====================================

/**
 * Garante que a tabela vacation_balance existe
 */
function ensure_vacation_balance_table() {
    global $pdo;

    static $checked = false;
    if ($checked) {
        return true;
    }

    try {
        $sql = "CREATE TABLE IF NOT EXISTS vacation_balance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            year INT NOT NULL,
            total_days INT NOT NULL DEFAULT 22,
            used_days INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_year (employee_id, year),
            INDEX idx_employee_year (employee_id, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);
        $checked = true;
        return true;
    } catch (Exception $e) {
        error_log("Erro ao garantir tabela vacation_balance: " . $e->getMessage());
        return false;
    }
}

/**
 * Criar saldo de férias para colaborador
 */
function create_vacation_balance($employee_id, $year) {
    global $pdo;
    
    try {
        ensure_vacation_balance_table();

        $query = "INSERT INTO vacation_balance (employee_id, year, total_days, used_days)
                 VALUES (:employee_id, :year, 22, 0)
                 ON DUPLICATE KEY UPDATE total_days = 22";
        
        $stmt = $pdo->prepare($query);
        return $stmt->execute([
            ':employee_id' => $employee_id,
            ':year' => $year
        ]);
    } catch (Exception $e) {
        error_log("Erro ao criar saldo de férias: " . $e->getMessage());
        return false;
    }
}

/**
 * Solicitar férias
 */
function request_vacation($employee_id, $start_date, $end_date, $vacation_type = 'Férias', $reason = '') {
    global $pdo;
    
    try {
        $days = calculate_business_days($start_date, $end_date);
        
        // Verificar saldo
        $balance = get_vacation_balance($employee_id);
        $remaining_days = (int)($balance['remaining_days'] ?? 0);
        if ($remaining_days < $days) {
            return ['error' => 'Saldo insuficiente de férias'];
        }
        
        $query = "INSERT INTO vacation_requests 
                 (employee_id, start_date, end_date, vacation_type, number_of_days, reason, status)
                 VALUES 
                 (:employee_id, :start_date, :end_date, :vacation_type, :number_of_days, :reason, 'Pendente')";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':employee_id' => $employee_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':vacation_type' => $vacation_type,
            ':number_of_days' => $days,
            ':reason' => $reason
        ]);
        
        return $result ? $pdo->lastInsertId() : false;
    } catch (Exception $e) {
        error_log("Erro ao solicitar férias: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter saldo de férias do colaborador
 */
function get_vacation_balance($employee_id, $year = null) {
    global $pdo;
    
    try {
        ensure_vacation_balance_table();

        if (!$year) {
            $year = date('Y');
        }
        
        $query = "SELECT * FROM vacation_balance 
                 WHERE employee_id = :employee_id AND year = :year";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':year' => $year
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !is_array($result)) {
            return ['total_days' => 22, 'used_days' => 0, 'remaining_days' => 22];
        }

        $total_days = (int)($result['total_days'] ?? 22);
        $used_days = (int)($result['used_days'] ?? 0);
        $remaining_days = array_key_exists('remaining_days', $result)
            ? (int)$result['remaining_days']
            : max(0, $total_days - $used_days);

        return [
            'total_days' => $total_days,
            'used_days' => $used_days,
            'remaining_days' => $remaining_days,
        ];
    } catch (Exception $e) {
        error_log("Erro ao obter saldo: " . $e->getMessage());
        return ['total_days' => 22, 'used_days' => 0, 'remaining_days' => 22];
    }
}

/**
 * Registar falta
 */
function record_absence($employee_id, $absence_date, $absence_type, $justification = '', $document = null) {
    global $pdo;
    
    try {
        $query = "INSERT INTO absences 
                 (employee_id, absence_date, absence_type, justification)
                 VALUES 
                 (:employee_id, :absence_date, :absence_type, :justification)";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':employee_id' => $employee_id,
            ':absence_date' => $absence_date,
            ':absence_type' => $absence_type,
            ':justification' => $justification
        ]);
        
        return $result ? $pdo->lastInsertId() : false;
    } catch (Exception $e) {
        error_log("Erro ao registar falta: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcular dias úteis entre duas datas
 */
function calculate_business_days($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day');
    
    $days = 0;
    for ($date = $start; $date < $end; $date->modify('+1 day')) {
        $dow = $date->format('w');
        // 0 = Domingo, 6 = Sábado
        if ($dow != 0 && $dow != 6) {
            $days++;
        }
    }
    
    return $days;
}

// =====================================
// 4. FUNÇÕES DE SALÁRIOS
// =====================================

/**
 * Gerar folha de pagamento mensal
 */
function generate_payroll($month, $year) {
    global $pdo;
    
    try {
        // Obter todos os colaboradores ativos
        $employees = list_employees(['status' => 'Ativo']);
        
        $results = [];
        foreach ($employees as $emp) {
            $payroll_id = create_payroll_record($emp['id'], $month, $year);
            $results[] = $payroll_id;
        }
        
        return $results;
    } catch (Exception $e) {
        error_log("Erro ao gerar folha de pagamento: " . $e->getMessage());
        return false;
    }
}

/**
 * Criar registro de pagamento
 */
function create_payroll_record($employee_id, $month, $year) {
    global $pdo;
    
    try {
        // Obter dados do colaborador
        $emp = get_employee($employee_id);
        
        // Calcular comissões do período
        $commission = calculate_sales_commission($employee_id, $month, $year);
        
        // Descontos padrão (SS 11%, IRS simplificado)
        $base = $emp['base_salary'] ?? ($emp['salary'] ?? 0);
        $gross = $base + ($commission['amount'] ?? 0);
        $ss_discount = $gross * 0.11;
        $tax_discount = $gross * 0.15; // Aproximado
        $net = $gross - $ss_discount - $tax_discount;

        // Guardar month no formato YYYY-MM (estrutura real da tabela)
        $month_year = sprintf('%04d-%02d', intval($year), intval($month));

        $query = "INSERT INTO payroll 
                 (employee_id, month, base_salary, deductions, net_salary)
                 VALUES 
                 (:employee_id, :month, :base_salary, :deductions, :net_salary)
                 ON DUPLICATE KEY UPDATE
                 base_salary = :base_salary,
                 deductions  = :deductions,
                 net_salary  = :net_salary";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':employee_id' => $employee_id,
            ':month'       => $month_year,
            ':base_salary' => $base,
            ':deductions'  => $ss_discount + $tax_discount,
            ':net_salary'  => $net
        ]);
        
        return $result ? $pdo->lastInsertId() : false;
    } catch (Exception $e) {
        error_log("Erro ao criar registro de pagamento: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcular comissões de vendas
 */
function calculate_sales_commission($employee_id, $month, $year) {
    // A tabela sales não tem employee_id — comissões não aplicáveis
    return ['total_sales' => 0, 'percentage' => 0, 'amount' => 0];
}

/**
 * Obter folha de pagamento
 */
function get_payroll($employee_id, $month, $year) {
    global $pdo;
    
    try {
        $month_year = sprintf('%04d-%02d', intval($year), intval($month));
        $query = "SELECT * FROM payroll 
                 WHERE employee_id = :employee_id 
                 AND month = :month";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':month'       => $month_year
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao obter folha de pagamento: " . $e->getMessage());
        return false;
    }
}

// =====================================
// 5. FUNÇÕES DE AUDITORIA RH
// =====================================

/**
 * Log de auditoria RH
 */
function log_rh_audit($action, $table_name, $record_id, $employee_id, $old_value = null, $new_value = null) {
    global $pdo;
    
    try {
        $query = "INSERT INTO rh_audit_log 
                 (action, table_name, record_id, employee_id, old_value, new_value, user_id, ip_address)
                 VALUES 
                 (:action, :table_name, :record_id, :employee_id, :old_value, :new_value, :user_id, :ip_address)";
        
        $stmt = $pdo->prepare($query);
        return $stmt->execute([
            ':action' => $action,
            ':table_name' => $table_name,
            ':record_id' => $record_id,
            ':employee_id' => $employee_id,
            ':old_value' => json_encode($old_value),
            ':new_value' => json_encode($new_value),
            ':user_id' => $_SESSION['user_id'] ?? null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Erro ao registar auditoria: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter histórico de auditoria
 */
function get_rh_audit_log($filters = []) {
    global $pdo;
    
    try {
        $query = "SELECT * FROM rh_audit_log WHERE 1=1";
        $params = [];
        
        if (!empty($filters['employee_id'])) {
            $query .= " AND employee_id = :employee_id";
            $params[':employee_id'] = $filters['employee_id'];
        }
        
        if (!empty($filters['action'])) {
            $query .= " AND action = :action";
            $params[':action'] = $filters['action'];
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 500";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao obter auditoria: " . $e->getMessage());
        return [];
    }
}