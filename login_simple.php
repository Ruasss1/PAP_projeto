<?php
// login_simple.php - Página de Login Simplificada para Testes

session_start();

// Verificar se está autenticado
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Processar login se enviado por POST
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email e senha são obrigatórios';
    } else {
        // Aqui iria a validação real contra a BD
        // Por enquanto, aceitar qualquer combinação para testar
        $_SESSION['user_id'] = 1;
        $_SESSION['email'] = $email;
        $_SESSION['name'] = 'Utilizador Teste';
        $success = 'Login bem-sucedido! Redirecionando...';
        // Não redirecionar ainda para mostrar a mensagem
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PAP Supermercado System</title>
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

        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
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

        .error {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }

        .success {
            background-color: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3c3;
        }

        .demo-users {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }

        .demo-users h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .demo-user {
            background: #f9f9f9;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 5px;
            font-size: 12px;
            border-left: 3px solid #667eea;
        }

        .demo-user strong {
            color: #333;
        }

        .demo-user .email {
            color: #667eea;
            font-family: monospace;
        }

        .demo-user .password {
            color: #764ba2;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 Login</h1>

        <?php if ($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">📧 Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="seu@email.com"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
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

            <button type="submit">Fazer Login</button>
        </form>

        <div class="demo-users">
            <h3>👥 Utilizadores de Demonstração</h3>
            <div class="demo-user">
                <strong>Admin</strong><br>
                <span class="email">admin@example.com</span> / 
                <span class="password">admin123</span>
            </div>
            <div class="demo-user">
                <strong>Gerente</strong><br>
                <span class="email">gerente@example.com</span> / 
                <span class="password">gerente123</span>
            </div>
            <div class="demo-user">
                <strong>Caixa</strong><br>
                <span class="email">caixa@example.com</span> / 
                <span class="password">caixa123</span>
            </div>
            <div class="demo-user">
                <strong>Stock</strong><br>
                <span class="email">stock@example.com</span> / 
                <span class="password">stock123</span>
            </div>
            <div class="demo-user">
                <strong>RH</strong><br>
                <span class="email">rh@example.com</span> / 
                <span class="password">rh123</span>
            </div>
        </div>
    </div>
</body>
</html>
