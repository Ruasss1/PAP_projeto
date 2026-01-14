<?php
// login.php - Página de Login Melhorada
// Sistema de Autenticação Robusto com Diferentes Roles

session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

// Se já está autenticado, redirecionar para dashboard
if ($auth->is_authenticated()) {
    header('Location: index.php');
    exit;
}

// Se saiu de outro lugar, guardar redirect
$redirect = $_GET['redirect'] ?? 'index.php';

$error = '';
$success = '';
$email = '';

// Verificar se há demo users para criar
$pdo = db_connect();
$check = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($check == 0) {
    // Criar utilizadores padrão se a BD está vazia
    create_demo_users($pdo);
    $success = 'Utilizadores de demonstração criados com sucesso!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email e senha são obrigatórios';
    } else {
        $result = $auth->login($email, $password);
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['session_id'] = $result['session_id'];
            
            // Log de login
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $auth->log_audit('LOGIN', 'auth', $result['user_id'], 'success', $ip_address, ['email' => $email]);
            
            // Redirecionar para página solicitada ou dashboard
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
            // Log de tentativa falhada
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $auth->log_audit('LOGIN_FAILED', 'auth', 0, 'failed', $ip_address, ['email' => $email, 'reason' => $result['message']]);
        }
    }
}

function create_demo_users($pdo) {
    $users = [
        [
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'Admin',
            'active' => 1
        ],
        [
            'name' => 'Gerente da Loja',
            'email' => 'gerente@example.com',
            'password' => password_hash('gerente123', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'Gerente',
            'active' => 1
        ],
        [
            'name' => 'Caixa',
            'email' => 'caixa@example.com',
            'password' => password_hash('caixa123', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'Caixa',
            'active' => 1
        ],
        [
            'name' => 'Responsável de Stock',
            'email' => 'stock@example.com',
            'password' => password_hash('stock123', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'Stock',
            'active' => 1
        ],
        [
            'name' => 'RH',
            'email' => 'rh@example.com',
            'password' => password_hash('rh123', PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => 'RH',
            'active' => 1
        ]
    ];
    
    foreach ($users as $user) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role_id, active, created_at)
                SELECT ?, ?, ?, id, ?, NOW()
                FROM roles WHERE name = ?
            ");
            $stmt->execute([
                $user['name'],
                $user['email'],
                $user['password'],
                $user['active'],
                $user['role']
            ]);
        } catch (Exception $e) {
            // Ignorar erros (usuário pode já existir)
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Login - PAP Supermercado System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
        }

        .login-left h1 {
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .login-left p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .demo-users {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .demo-users h3 {
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .demo-user {
            background: rgba(255, 255, 255, 0.15);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            font-size: 13px;
            border-left: 3px solid rgba(255, 255, 255, 0.3);
        }

        .demo-user strong {
            display: block;
            margin-bottom: 5px;
        }

        .demo-user code {
            background: rgba(0, 0, 0, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Monaco', monospace;
            font-size: 12px;
        }

        .login-right {
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .login-form p {
            color: #999;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-error {
            color: #e74c3c;
            background: #fadbd8;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-size: 14px;
        }

        .form-success {
            color: #27ae60;
            background: #d5f4e6;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .info-box {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }

        .info-box strong {
            color: #333;
        }

        @media (max-width: 768px) {
            .login-wrapper {
                grid-template-columns: 1fr;
            }

            .login-left {
                padding: 30px;
                display: none;
            }

            .login-right {
                padding: 30px;
            }

            .login-left h1 {
                font-size: 24px;
            }

            .login-form h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side - Info -->
        <div class="login-left">
            <h1>🛒 PAP Supermercado</h1>
            <p>Sistema completo de gestão para supermercados com autenticação segura, controlo de preços, inventário avançado e análise de vendas.</p>
            
            <div class="demo-users">
                <h3>👥 Utilizadores de Demonstração</h3>
                
                <div class="demo-user">
                    <strong>👨‍💼 Admin</strong>
                    <code>admin@example.com</code><br>
                    Senha: <code>admin123</code>
                </div>
                
                <div class="demo-user">
                    <strong>📊 Gerente</strong>
                    <code>gerente@example.com</code><br>
                    Senha: <code>gerente123</code>
                </div>
                
                <div class="demo-user">
                    <strong>💰 Caixa</strong>
                    <code>caixa@example.com</code><br>
                    Senha: <code>caixa123</code>
                </div>
                
                <div class="demo-user">
                    <strong>📦 Stock</strong>
                    <code>stock@example.com</code><br>
                    Senha: <code>stock123</code>
                </div>
                
                <div class="demo-user">
                    <strong>👤 RH</strong>
                    <code>rh@example.com</code><br>
                    Senha: <code>rh123</code>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <form class="login-form" method="POST">
                <h2>🔐 Fazer Login</h2>
                <p>Aceda ao seu painel de controlo</p>

                <?php if (!empty($error)): ?>
                    <div class="form-error">
                        <strong>❌ Erro:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="form-success">
                        <strong>✅ Sucesso:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">📧 Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($email); ?>"
                        placeholder="seu@email.com"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">🔑 Senha</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="••••••••"
                        required
                    >
                </div>

                <button type="submit">Entrar</button>

                <div class="info-box">
                    <strong>💡 Dica:</strong> Este é um sistema de demonstração. Use uma das credenciais de exemplo à esquerda para aceder com diferentes permissões.
                </div>

                <div class="forgot-password">
                    <a href="#help">Esqueceu a senha?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
