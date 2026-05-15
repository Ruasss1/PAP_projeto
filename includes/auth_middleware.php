<?php
/**
 * MIDDLEWARE DE AUTENTICAÇÃO GLOBAL
 * Ficheiro: includes/auth_middleware.php
 * 
 * Bloqueia acesso não autenticado a todas as páginas
 * Redireciona para login quando necessário
 * Deve ser incluído no início de cada página protegida
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

// Em ambiente CLI (tests/scripts), não aplicar redirecionamentos
if (PHP_SAPI === 'cli') {
    return;  // Ignora middleware em CLI
}

// Lista de páginas públicas (sem autenticação necessária)
$public_pages = [
    'login.php',
    'index.php',  // Redireciona se não autenticado
];

// Obter nome do ficheiro da página atual
$current_page = basename($_SERVER['SCRIPT_FILENAME'] ?? 'index.php');

// Se utilizador NÃO está autenticado e NÃO é uma página pública
if (!$auth->is_authenticated()) {
    // Verificar se é uma página que requer autenticação
    if ($current_page !== 'login.php') {
        // Guardar URL solicitado para redirecionar após login
        $req = $_SERVER['REQUEST_URI'] ?? '/index.php';
        $_SESSION['redirect_after_login'] = $req;
        
        // Redirecionar para página de login
        header('Location: /login.php?redirect=' . urlencode($req));
        exit;
    }
}

// Se utilizador JÁ está autenticado e tenta aceder ao login, redirecionar para dashboard
if ($auth->is_authenticated() && $current_page === 'login.php') {
    header('Location: /index.php');
    exit;
}

// Nota: Verificação de permissões específicas é feita em cada módulo
// usando $auth->require_auth('modulo', 'acao')
