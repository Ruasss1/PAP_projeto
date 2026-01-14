<?php
// modules/db_status.php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = null;
$connected = false;
$message = '';
$tables = [];
$counts = [];
$expected = [
    'bebidas','breaks','congelados','employees','frutas','higiene','laticinios','limpeza','mercearia',
    'orders','padaria','products','sales','sale_items','suppliers','transactions','all_products','financial_report'
];

try {
    $pdo = db_connect();
    $connected = true;
    $stmt = $pdo->query('SHOW FULL TABLES');
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    foreach ($rows as $r) {
        $tables[] = $r[0];
    }

    foreach ($tables as $t) {
        try {
            $c = $pdo->query("SELECT COUNT(*) FROM `" . $t . "`")->fetchColumn();
        } catch (Exception $e) {
            // views may fail COUNT, fallback to -
            $c = '-';
        }
        $counts[$t] = $c;
    }

} catch (Exception $e) {
    $message = $e->getMessage();
}

?>
<h1>DB Status</h1>
<?php if (!$connected): ?>
    <p class="notice">Erro ao ligar à BD: <?php echo htmlspecialchars($message); ?></p>
<?php else: ?>
    <p>Ligado a: <strong><?php echo htmlspecialchars(DB_HOST); ?></strong> — base: <strong><?php echo htmlspecialchars(DB_NAME); ?></strong></p>
    <h2>Tabelas encontradas (<?php echo count($tables); ?>)</h2>
    <table class="table">
        <thead><tr><th>Tabela</th><th>Registos</th><th>Esperada?</th></tr></thead>
        <tbody>
        <?php foreach ($counts as $t => $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($t); ?></td>
                <td><?php echo htmlspecialchars($c); ?></td>
                <td><?php echo in_array($t, $expected) ? '✅' : ''; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Tabelas esperadas mas não encontradas</h3>
    <ul>
        <?php foreach ($expected as $e): if (!in_array($e, $tables)): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
        <?php endif; endforeach; ?>
    </ul>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>