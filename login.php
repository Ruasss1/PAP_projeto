<?php
// login.php
// User authentication page

session_start();
require_once 'includes/auth.php';

// If already logged in, redirect to dashboard
if ($auth->is_authenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

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
            header('Location: index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PAP Supermercado System</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
        }
        
        .login-header p {
            margin: 10px 0 0 0;
            color: #999;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-bottom: 20px;
            padding: 12px;
            background: #fadbd8;
            border-radius: 4px;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
        
        .test-credentials {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 12px;
        }
        
        .test-credentials p {
            margin: 5px 0;
            color: #555;
        }
        
        .test-credentials code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🛒 SuperMercado</h1>
            <p>Sistema de Gestão</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Entrar</button>
        </form>
        
        <div class="test-credentials">
            <p><strong>Credenciais de Teste:</strong></p>
            <p>Email: <code>admin@example.com</code></p>
            <p>Senha: <code>admin123</code></p>
        </div>
        
        <div class="login-footer">
            <p>Sistema seguro com autenticação e auditoria completa</p>
        </div>
    </div>
</body>
</html>
