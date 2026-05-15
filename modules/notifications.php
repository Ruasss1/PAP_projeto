<?php
/**
 * Módulo de Notificações e Alertas
 * modules/notifications.php
 * 
 * Funções para gerenciar notificações, alertas automáticos e tarefas
 */

// Ensure database connection is available
if (!function_exists('db_connect')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// ============================================
// NOTIFICAÇÕES
// ============================================

/**
 * Criar notificação
 */
function create_notification($data) {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
            (user_id, type, title, message, link, icon, priority, expires_at)
        VALUES 
            (:user_id, :type, :title, :message, :link, :icon, :priority, :expires_at)
    ");
    
    return $stmt->execute([
        ':user_id' => $data['user_id'] ?? null,
        ':type' => $data['type'],
        ':title' => $data['title'],
        ':message' => $data['message'],
        ':link' => $data['link'] ?? null,
        ':icon' => $data['icon'] ?? null,
        ':priority' => $data['priority'] ?? 'Normal',
        ':expires_at' => $data['expires_at'] ?? null
    ]);
}

/**
 * Obter notificações de um utilizador
 */
function get_user_notifications($user_id = null, $unread_only = false) {
    $pdo = db_connect();
    
    $where = [];
    $params = [];
    
    if ($user_id !== null) {
        $where[] = "(user_id = ? OR user_id IS NULL)";
        $params[] = $user_id;
    } else {
        $where[] = "user_id IS NULL";
    }
    
    if ($unread_only) {
        $where[] = "is_read = 0";
    }
    
    $where[] = "(expires_at IS NULL OR expires_at > NOW())";
    
    $sql = "SELECT * FROM notifications WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Marcar notificação como lida
 */
function mark_notification_read($notification_id) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
    return $stmt->execute([$notification_id]);
}

/**
 * Marcar todas as notificações como lidas
 */
function mark_all_notifications_read($user_id = null) {
    $pdo = db_connect();
    
    if ($user_id !== null) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? OR user_id IS NULL");
        return $stmt->execute([$user_id]);
    } else {
        $pdo->exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id IS NULL");
        return true;
    }
}

/**
 * Contar notificações não lidas
 */
function count_unread_notifications($user_id = null) {
    $pdo = db_connect();
    
    if ($user_id !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE (user_id = ? OR user_id IS NULL) 
              AND is_read = 0 
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id IS NULL 
              AND is_read = 0 
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
    }
    
    return $stmt->fetchColumn();
}

/**
 * Eliminar notificação
 */
function delete_notification($notification_id) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    return $stmt->execute([$notification_id]);
}

// ============================================
// ALERTAS AUTOMÁTICOS
// ============================================

/**
 * Criar alerta de sistema
 */
function create_system_alert($data) {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("
        INSERT INTO system_alerts 
            (alert_type, entity_type, entity_id, threshold_value, current_value, message, status)
        VALUES 
            (:alert_type, :entity_type, :entity_id, :threshold_value, :current_value, :message, :status)
    ");
    
    return $stmt->execute([
        ':alert_type' => $data['alert_type'],
        ':entity_type' => $data['entity_type'],
        ':entity_id' => $data['entity_id'],
        ':threshold_value' => $data['threshold_value'] ?? null,
        ':current_value' => $data['current_value'] ?? null,
        ':message' => $data['message'],
        ':status' => $data['status'] ?? 'Ativo'
    ]);
}

/**
 * Listar alertas ativos
 */
function list_active_alerts($type = null) {
    $pdo = db_connect();
    
    $where = "status = 'Ativo'";
    $params = [];
    
    if ($type) {
        $where .= " AND alert_type = ?";
        $params[] = $type;
    }
    
    $sql = "SELECT * FROM system_alerts WHERE $where ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Resolver alerta
 */
function resolve_alert($alert_id, $user_id = null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("
        UPDATE system_alerts 
        SET status = 'Resolvido', resolved_at = NOW(), resolved_by = ?
        WHERE id = ?
    ");
    return $stmt->execute([$user_id, $alert_id]);
}

/**
 * Ignorar alerta
 */
function ignore_alert($alert_id, $user_id = null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("
        UPDATE system_alerts 
        SET status = 'Ignorado', resolved_at = NOW(), resolved_by = ?
        WHERE id = ?
    ");
    return $stmt->execute([$user_id, $alert_id]);
}

/**
 * Verificar stock baixo e criar alertas
 */
function check_low_stock_alerts() {
    $pdo = db_connect();
    
    // Obter configuração
    $stmt = $pdo->prepare("SELECT threshold_value FROM alert_settings WHERE alert_type = 'stock_baixo' AND is_enabled = 1");
    $stmt->execute();
    $setting = $stmt->fetch();
    
    if (!$setting) return 0;
    
    $threshold = $setting['threshold_value'];
    
    // Produtos com stock baixo
    $stmt = $pdo->prepare("SELECT * FROM products WHERE stock < ? AND stock > 0");
    $stmt->execute([$threshold]);
    $products = $stmt->fetchAll();
    
    $alerts_created = 0;
    
    foreach ($products as $product) {
        // Verificar se já existe alerta ativo para este produto
        $exists = $pdo->prepare("
            SELECT COUNT(*) FROM system_alerts 
            WHERE alert_type = 'Stock Baixo' 
              AND entity_type = 'produto' 
              AND entity_id = ? 
              AND status = 'Ativo'
        ");
        $exists->execute([$product['id']]);
        
        if ($exists->fetchColumn() == 0) {
            // Criar alerta
            create_system_alert([
                'alert_type' => 'Stock Baixo',
                'entity_type' => 'produto',
                'entity_id' => $product['id'],
                'threshold_value' => $threshold,
                'current_value' => $product['stock'],
                'message' => "Stock baixo: {$product['name']} - Apenas {$product['stock']} unidades",
                'status' => 'Ativo'
            ]);
            
            // Criar notificação
            create_notification([
                'type' => 'Stock',
                'title' => 'Stock Baixo',
                'message' => "O produto \"{$product['name']}\" tem apenas {$product['stock']} unidades em stock",
                'link' => '/modules/stock.php',
                'icon' => '',
                'priority' => 'Alta'
            ]);
            
            $alerts_created++;
        }
    }
    
    // Produtos esgotados
    $stmt = $pdo->query("SELECT * FROM products WHERE stock = 0");
    $out_of_stock = $stmt->fetchAll();
    
    foreach ($out_of_stock as $product) {
        $exists = $pdo->prepare("
            SELECT COUNT(*) FROM system_alerts 
            WHERE alert_type = 'Produto Esgotado' 
              AND entity_type = 'produto' 
              AND entity_id = ? 
              AND status = 'Ativo'
        ");
        $exists->execute([$product['id']]);
        
        if ($exists->fetchColumn() == 0) {
            create_system_alert([
                'alert_type' => 'Produto Esgotado',
                'entity_type' => 'produto',
                'entity_id' => $product['id'],
                'current_value' => 0,
                'message' => "Produto esgotado: {$product['name']}",
                'status' => 'Ativo'
            ]);
            
            create_notification([
                'type' => 'Stock',
                'title' => 'Produto Esgotado',
                'message' => "O produto \"{$product['name']}\" esgotou!",
                'link' => '/modules/stock.php',
                'icon' => '',
                'priority' => 'Urgente'
            ]);
            
            $alerts_created++;
        }
    }
    
    // Atualizar última verificação
    $pdo->prepare("UPDATE alert_settings SET last_check_at = NOW() WHERE alert_type IN ('stock_baixo', 'produto_esgotado')")
        ->execute();
    
    return $alerts_created++;
}

/**
 * Verificar clientes inativos
 */
function check_inactive_customers_alerts() {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("SELECT threshold_value FROM alert_settings WHERE alert_type = 'cliente_inativo' AND is_enabled = 1");
    $stmt->execute();
    $setting = $stmt->fetch();
    
    if (!$setting) return 0;
    
    $days = $setting['threshold_value'];
    
    $stmt = $pdo->prepare("
        SELECT * FROM customers 
        WHERE status = 'Ativo' 
          AND last_purchase_date IS NOT NULL
          AND DATEDIFF(NOW(), last_purchase_date) > ?
    ");
    $stmt->execute([$days]);
    $inactive_customers = $stmt->fetchAll();
    
    $alerts_created = 0;
    
    foreach ($inactive_customers as $customer) {
        // Verificar se já existe alerta
        $exists = $pdo->prepare("
            SELECT COUNT(*) FROM system_alerts 
            WHERE alert_type = 'Cliente Inativo' 
              AND entity_type = 'cliente' 
              AND entity_id = ? 
              AND status = 'Ativo'
        ");
        $exists->execute([$customer['id']]);
        
        if ($exists->fetchColumn() == 0) {
            $days_inactive = (new DateTime())->diff(new DateTime($customer['last_purchase_date']))->days;
            
            create_system_alert([
                'alert_type' => 'Cliente Inativo',
                'entity_type' => 'cliente',
                'entity_id' => $customer['id'],
                'threshold_value' => $days,
                'current_value' => $days_inactive,
                'message' => "Cliente inativo: {$customer['name']} - {$days_inactive} dias sem comprar",
                'status' => 'Ativo'
            ]);
            
            create_notification([
                'type' => 'Cliente',
                'title' => 'Cliente Inativo',
                'message' => "Cliente \"{$customer['name']}\" está {$days_inactive} dias sem fazer compras",
                'link' => '/admin/customers/view.php?id=' . $customer['id'],
                'icon' => '',
                'priority' => 'Normal'
            ]);
            
            $alerts_created++;
        }
    }
    
    $pdo->prepare("UPDATE alert_settings SET last_check_at = NOW() WHERE alert_type = 'cliente_inativo'")
        ->execute();
    
    return $alerts_created;
}

// ============================================
// TAREFAS
// ============================================

/**
 * Criar tarefa
 */
function create_task($data) {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("
        INSERT INTO tasks 
            (assigned_to, created_by, title, description, category, priority, due_date, status, notes)
        VALUES 
            (:assigned_to, :created_by, :title, :description, :category, :priority, :due_date, :status, :notes)
    ");
    
    return $stmt->execute([
        ':assigned_to' => $data['assigned_to'] ?? null,
        ':created_by' => $data['created_by'],
        ':title' => $data['title'],
        ':description' => $data['description'] ?? null,
        ':category' => $data['category'] ?? 'Geral',
        ':priority' => $data['priority'] ?? 'Normal',
        ':due_date' => $data['due_date'] ?? null,
        ':status' => $data['status'] ?? 'Pendente',
        ':notes' => $data['notes'] ?? null
    ]);
}

/**
 * Listar tarefas
 */
function list_tasks($filters = []) {
    $pdo = db_connect();
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['assigned_to'])) {
        $where[] = "assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['category'])) {
        $where[] = "category = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['priority'])) {
        $where[] = "priority = ?";
        $params[] = $filters['priority'];
    }
    
    $orderBy = $filters['order_by'] ?? 'due_date ASC, priority DESC';
    
    $sql = "SELECT * FROM tasks WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Atualizar tarefa
 */
function update_task($id, $data) {
    $pdo = db_connect();
    
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET title = :title,
            description = :description,
            category = :category,
            priority = :priority,
            status = :status,
            due_date = :due_date,
            notes = :notes,
            assigned_to = :assigned_to
        WHERE id = :id
    ");
    
    return $stmt->execute([
        ':id' => $id,
        ':title' => $data['title'],
        ':description' => $data['description'] ?? null,
        ':category' => $data['category'],
        ':priority' => $data['priority'],
        ':status' => $data['status'],
        ':due_date' => $data['due_date'] ?? null,
        ':notes' => $data['notes'] ?? null,
        ':assigned_to' => $data['assigned_to'] ?? null
    ]);
}

/**
 * Marcar tarefa como concluída
 */
function complete_task($id, $user_id = null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET status = 'Concluida', completed_at = NOW(), completed_by = ?
        WHERE id = ?
    ");
    return $stmt->execute([$user_id, $id]);
}

/**
 * Eliminar tarefa
 */
function delete_task($id) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    return $stmt->execute([$id]);
}

// FIM DO MÓDULO
