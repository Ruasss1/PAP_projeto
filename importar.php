<?php
/**
 * IMPORTADOR TEMPORÁRIO — corre por dentro do Railway (liga à BD interna).
 * Usar uma vez: /importar.php?token=SEU_TOKEN
 * APAGAR este ficheiro (e db_full.sql) depois de usar.
 */
@set_time_limit(600);
@ini_set('memory_limit', '512M');
header('Content-Type: text/plain; charset=utf-8');

$token = getenv('SETUP_TOKEN') ?: 'pap-setup-2026';
if (($_GET['token'] ?? '') !== $token) {
    http_response_code(403);
    die("Acesso negado. Usa: /importar.php?token={$token}\n");
}

require __DIR__ . '/config/database.php';
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    die('Sem ligação à base de dados: ' . (defined('DB_ERROR') ? DB_ERROR : 'desconhecido') . "\n");
}

$file = __DIR__ . '/db_full.sql';
if (!is_file($file)) { die("Ficheiro db_full.sql não encontrado.\n"); }

echo "A importar... (pode demorar um pouco)\n\n";

$fh = fopen($file, 'r');
$stmt = '';
$ok = 0; $fail = 0; $errors = [];
while (($line = fgets($fh)) !== false) {
    $t = rtrim($line);
    if ($t === '' || strpos($t, '--') === 0) continue;   // ignora linhas vazias e comentários --
    $stmt .= $line;
    if (substr($t, -1) === ';') {                          // fim de instrução
        try {
            $pdo->exec($stmt);
            $ok++;
        } catch (Throwable $e) {
            $fail++;
            if (count($errors) < 15) $errors[] = trim(substr($stmt, 0, 90)) . ' ... => ' . $e->getMessage();
        }
        $stmt = '';
    }
}
fclose($fh);

echo "Instruções executadas: {$ok}  |  com erro: {$fail}\n\n";
if ($errors) {
    echo "Primeiros erros (normalmente inofensivos):\n";
    foreach ($errors as $e) echo "  - {$e}\n";
    echo "\n";
}

// Resumo
try {
    $tabs = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Total de tabelas: " . count($tabs) . "\n";
    foreach (['users','products','sales','stores'] as $tb) {
        try { $n = $pdo->query("SELECT COUNT(*) FROM `$tb`")->fetchColumn(); echo "  {$tb}: {$n} registos\n"; }
        catch (Throwable $e) {}
    }
    echo "\nIMPORTAÇÃO TERMINADA. Já podes entrar no site com a tua conta.\n";
    echo "Apaga agora o importar.php e o db_full.sql por segurança.\n";
} catch (Throwable $e) {
    echo "Aviso ao listar tabelas: " . $e->getMessage() . "\n";
}
