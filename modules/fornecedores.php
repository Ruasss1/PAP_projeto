<?php
// modules/fornecedores.php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'delivery_days' => intval($_POST['delivery_days'] ?? 2),
            'contact' => trim($_POST['contact'] ?? ''),
        ];
        $id = add_supplier($data);
        $message = $id ? 'Fornecedor adicionado' : 'Erro ao adicionar fornecedor';
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'delivery_days' => intval($_POST['delivery_days'] ?? 2),
            'contact' => trim($_POST['contact'] ?? ''),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $ok = update_supplier($id, $data);
        $message = $ok ? 'Fornecedor atualizado' : 'Erro ao atualizar';
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $ok = delete_supplier($id);
        $message = $ok ? 'Fornecedor eliminado' : 'Erro ao eliminar';
    }
}

$suppliers = list_suppliers();
$editing = false;
$edit = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editing = true;
    $edit = get_supplier(intval($_GET['id']));
}
?>
<h1>Fornecedores</h1>
<?php if (!empty($message)): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<section class="forms">
    <?php if ($editing && $edit): ?>
        <h2>Editar Fornecedor #<?php echo $edit['id']; ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
            
            <label>Nome <input name="name" value="<?php echo htmlspecialchars($edit['name']); ?>" required></label>
            <label>Email <input name="email" type="email" value="<?php echo htmlspecialchars($edit['email'] ?? ''); ?>"></label>
            <label>Telefone <input name="phone" value="<?php echo htmlspecialchars($edit['phone'] ?? ''); ?>"></label>
            <label>Endereço <textarea name="address" rows="2"><?php echo htmlspecialchars($edit['address'] ?? ''); ?></textarea></label>
            <label>Dias de Entrega <input name="delivery_days" type="number" min="1" value="<?php echo $edit['delivery_days'] ?? 2; ?>"></label>
            <label>Contacto Extra <input name="contact" value="<?php echo htmlspecialchars($edit['contact'] ?? ''); ?>"></label>
            <label>Ativo <input type="checkbox" name="active" <?php echo ($edit['active'] ?? 1) ? 'checked' : ''; ?>></label>
            
            <button type="submit">Guardar</button>
            <a class="btn" href="/modules/fornecedores.php">Cancelar</a>
        </form>
    <?php else: ?>
        <h2>Adicionar Fornecedor</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <label>Nome <input name="name" required></label>
            <label>Email <input name="email" type="email"></label>
            <label>Telefone <input name="phone"></label>
            <label>Endereço <textarea name="address" rows="2"></textarea></label>
            <label>Dias de Entrega <input name="delivery_days" type="number" min="1" value="2"></label>
            <label>Contacto Extra <input name="contact"></label>
            <button type="submit">Adicionar</button>
        </form>
    <?php endif; ?>
</section>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Dias Entrega</th>
                <th>Endereço</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo htmlspecialchars($s['email'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($s['phone'] ?? '-'); ?></td>
                    <td><?php echo $s['delivery_days'] ?? 2; ?> dias</td>
                    <td><?php echo htmlspecialchars(substr($s['address'] ?? '-', 0, 30)); ?></td>
                    <td>
                        <span class="<?php echo ($s['active'] ?? 1) ? 'positive' : 'negative'; ?>">
                            <?php echo ($s['active'] ?? 1) ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td>
                        <a href="?action=edit&id=<?php echo $s['id']; ?>">Editar</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Eliminar fornecedor?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                            <button type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

