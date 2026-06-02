<?php
/**
 * Criar Novo Cliente
 * admin/customers/novo.php
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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'nif' => trim($_POST['nif'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? null,
        'status' => 'Ativo',
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    if (empty($data['name'])) {
        $error = 'O nome é obrigatório.';
    } else {
        $customer_id = create_customer($data);
        if ($customer_id) {
            header('Location: edit.php?id=' . $customer_id . '&success=1');
            exit;
        } else {
            $error = 'Erro ao criar cliente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Cliente - PAP Supermercado</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .create-container {
            max-width: 700px;
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
        
        .btn-create {
            background: #00d4ff;
            color: #000;
            padding: 14px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            width: 100%;
        }
        
        .btn-create:hover {
            background: #00a8cc;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .info-box {
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid #00d4ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #00d4ff;
            font-size: 14px;
        }
    </style>
</head>
<body class="dark-theme">
    <?php include '../../includes/header.php'; ?>
    
    <div class="create-container">
        <div class="header">
            <h1>➕ Novo Cliente</h1>
            <a href="index.php" class="btn-back">← Voltar</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            🎫 Um cartão de fidelidade será gerado automaticamente para o novo cliente.
        </div>
        
        <div class="form-card">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="name" required placeholder="Nome completo">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="email@exemplo.com">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="phone" placeholder="912 345 678">
                    </div>
                    <div class="form-group">
                        <label>NIF</label>
                        <input type="text" name="nif" maxlength="9" placeholder="123456789">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Data de Nascimento</label>
                    <input type="date" name="birth_date">
                </div>
                
                <div class="form-group">
                    <label>Morada</label>
                    <input type="text" name="address" placeholder="Rua, número, código postal, cidade">
                </div>
                
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notes" placeholder="Observações adicionais..."></textarea>
                </div>
                
                <button type="submit" class="btn-create">✅ Criar Cliente</button>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
