<?php
/**
 * CONFIGURAÇÃO DA BASE DE DADOS
 * Ficheiro: config/database.php
 * 
 * Estabelece a conexão PDO com MySQL
 * Define credenciais e parâmetros de conexão
 */

// Inicia sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Credenciais da base de dados (env vars para hosting; fallback para local)
define('DB_HOST', getenv('MYSQLHOST')      ?: getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('MYSQLPORT')      ?: getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE')  ?: getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'supermercado');
define('DB_USER', getenv('MYSQLUSER')      ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD')  ?: getenv('DB_PASS') ?: '');

// Variável global de conexão
$pdo = null;

try {
    // String de conexão DSN (Data Source Name)
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    
    // Cria conexão PDO com opções de configuração
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,           // Lança exceções em erros
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // Retorna arrays associativos
    ]);
    
} catch (PDOException $e) {
    // Regista erro no log do servidor
    error_log('Erro de conexão à base de dados: ' . $e->getMessage());
    // Guarda mensagem para mostrar na UI se necessário
    define('DB_ERROR', $e->getMessage());
}
