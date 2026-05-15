<?php
/**
 * Edição de Clientes
 * admin/customers/edit.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar acesso
if (!$auth->is_authenticated()) {
    header('Location: /login.php');
    exit;
}

$message = '';
$error = '';

// Obter ID do cliente
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    header('Location: index.php');
    exit;
}

// Buscar dados do cliente
$customer = get_customer($customer_id);

if (!$customer) {
    header('Location: index.php?error=Cliente não encontrado');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'nif' => trim($_POST['nif'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? null,
        'status' => $_POST['status'] ?? 'Ativo',
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    if (empty($data['name'])) {
        $error = 'O nome é obrigatório.';
    } else {
        if (update_customer($customer_id, $data)) {
            $message = 'Cliente atualizado com sucesso!';
            // Recarregar dados
            $customer = get_customer($customer_id);
        } else {
            $error = 'Erro ao atualizar cliente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - PAP Supermercado</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #00d4ff;
        }
        
        .header h1 {
            color: #00d4ff;
            margin: 0;
            font-size: 24px;
        }
        
        .btn-back {
            background: #555;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
        }
        
        .form-card {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #00d4ff;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 4px;
            color: white;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-save {
            background: #00d4ff;
            color: #000;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        
        .btn-save:hover {
            background: #00a8cc;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .loyalty-card {
            background: linear-gradient(135deg, #00d4ff, #00a8cc);
            color: #000;
            padding: 15px 20px;
            border-radius: 8px;
            font-family: monospace;
            font-weight: bold;
            font-size: 18px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .points-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .points-box {
            background: #2a2a2a;
            padding: 15px;
            border-radius: 8px;
            flex: 1;
            text-align: center;
        }
        
        .points-box h4 {
            color: #888;
            font-size: 12px;
            margin: 0 0 5px 0;
        }
        
        .points-box .value {
            color: #ffaa00;
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body class="dark-theme">
    <?php include '../../includes/header.php'; ?>
    
    <div class="edit-container">
        <div class="header">
            <h1>✏️ Editar Cliente</h1>
            <a href="index.php" class="btn-back">← Voltar</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Cartão de Fidelidade -->
        <div class="loyalty-card">
            🎫 <?= htmlspecialchars($customer['loyalty_card_number']) ?>
        </div>
        
        <!-- Informação de Pontos -->
        <div class="points-info">
            <div class="points-box">
                <h4>PONTOS ATUAIS</h4>
                <div class="value"><?= $customer['points_balance'] ?? 0 ?></div>
            </div>
            <div class="points-box">
                <h4>TOTAL GASTO</h4>
                <div class="value">€<?= number_format($customer['total_spent'] ?? 0, 2) ?></div>
            </div>
            <div class="points-box">
                <h4>COMPRAS</h4>
                <div class="value"><?= $customer['total_purchases'] ?? 0 ?></div>
            </div>
        </div>
        
        <div class="form-card">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($customer['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>NIF</label>
                        <input type="text" name="nif" value="<?= htmlspecialchars($customer['nif'] ?? '') ?>" maxlength="9">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($customer['birth_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Ativo" <?= ($customer['status'] === 'Ativo') ? 'selected' : '' ?>>Ativo</option>
                            <option value="Inativo" <?= ($customer['status'] === 'Inativo') ? 'selected' : '' ?>>Inativo</option>
                            <option value="Bloqueado" <?= ($customer['status'] === 'Bloqueado') ? 'selected' : '' ?>>Bloqueado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Morada</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notes"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn-save">💾 Guardar Alterações</button>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
