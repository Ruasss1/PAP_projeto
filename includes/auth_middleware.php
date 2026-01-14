<?php
// includes/auth_middleware.php
// Middleware de Autenticação Global - Bloqueia Acesso Não Autenticado

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

// Páginas públicas (sem autenticação necessária)
$public_pages = [
    'login.php',
    'index.php',  // Redireciona se não autenticado
];

// Obter página atual
$current_page = basename($_SERVER['SCRIPT_FILENAME']);

// Se não está autenticado e não é uma página pública
if (!$auth->is_authenticated()) {
    // Verificar se é uma página que requer autenticação
    if ($current_page !== 'login.php') {
        // Guardar a página solicitada para redirecionar depois de login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirecionar para login
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Se está autenticado e está a tentar aceder ao login, redirecionar para dashboard
if ($auth->is_authenticated() && $current_page === 'login.php') {
    header('Location: /index.php');
    exit;
}

// Se o usuário tentou aceder a um módulo sem permissão
if ($current_page !== 'login.php' && $auth->is_authenticated()) {
    // Este check será feito em cada módulo com $auth->require_auth('module', 'action')
}
