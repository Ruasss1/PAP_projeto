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

// Carrega variáveis do ficheiro .env (raiz do projeto), se existir
$__envFile = __DIR__ . '/../.env';
if (is_file($__envFile)) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__line) {
        $__line = trim($__line);
        if ($__line === '' || $__line[0] === '#' || strpos($__line, '=') === false) continue;
        [$__k, $__v] = explode('=', $__line, 2);
        $__k = trim($__k); $__v = trim($__v);
        if (getenv($__k) === false) { putenv("$__k=$__v"); $_ENV[$__k] = $__v; }
    }
}

// Credenciais da base de dados (env vars para hosting; fallback para local)
define('DB_HOST', getenv('MYSQLHOST')      ?: getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('MYSQLPORT')      ?: getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE')  ?: getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'supermercado');
define('DB_USER', getenv('MYSQLUSER')      ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD')  ?: getenv('DB_PASS') ?: '');

// Caminho do certificado CA (necessário para ligações TLS a alojamento cloud, ex.: TiDB)
define('DB_CA', getenv('DB_CA') ?: '');

// Variável global de conexão
$pdo = null;

try {
    // String de conexão DSN (Data Source Name)
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    // Opções base
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,           // Lança exceções em erros
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // Retorna arrays associativos
    ];

    // TLS só quando é mesmo preciso: TiDB exige; Railway/local NÃO.
    // Ativa TLS se: DB_CA definido, ou host do TiDB, ou DB_SSL=1.
    $isLocal = in_array(DB_HOST, ['127.0.0.1', 'localhost', '::1'], true);
    $wantSSL = !$isLocal && (DB_CA !== '' || stripos(DB_HOST, 'tidbcloud') !== false || getenv('DB_SSL') === '1');
    if ($wantSSL) {
        // Usa o CA definido em DB_CA, ou tenta encontrar o do sistema (Mac/Linux)
        $ca = DB_CA;
        if (!$ca || !is_file($ca)) {
            foreach (['/etc/ssl/cert.pem', '/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt'] as $p) {
                if (is_file($p)) { $ca = $p; break; }
            }
        }
        if ($ca && is_file($ca)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
    }

    // Cria conexão PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Regista erro no log do servidor
    error_log('Erro de conexão à base de dados: ' . $e->getMessage());
    // Guarda mensagem para mostrar na UI se necessário
    define('DB_ERROR', $e->getMessage());
}
