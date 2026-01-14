<?php
// modules/rh.php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

// Lista de trabalhadores (implementar CRUD completo mais tarde)
$pdo = db_connect();
$employees = $pdo->query('SELECT * FROM employees ORDER BY name')->fetchAll();
?>
<h1>Recursos Humanos</h1>
<table>
    <thead><tr><th>Nome</th><th>Cargo</th><th>Salário</th></tr></thead>
    <tbody>
    <?php foreach ($employees as $e): ?>
        <tr>
            <td><?php echo htmlspecialchars($e['name']); ?></td>
            <td><?php echo htmlspecialchars($e['role']); ?></td>
            <td><?php echo number_format($e['salary'],2); ?>€</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>