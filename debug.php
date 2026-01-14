<?php
// debug.php — testa ligação à BD usando config/database.php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = db_connect();
    echo "OK: ligado à base de dados (host=" . DB_HOST . ", db=" . DB_NAME . ")\n";
    // Mostra contagens rápidas
    $tables = ['products','suppliers','sales','breaks','employees','orders','transactions'];
    foreach ($tables as $t) {
        $count = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as c FROM `" . $t . "`");
            $count = $stmt->fetchColumn();
        } catch (Exception $e) { $count = 'n/a'; }
        echo "- $t: $count\n";
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
