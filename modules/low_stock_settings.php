<?php
$page_title = 'Alertas de Stock';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$message = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        // Save threshold
        $threshold = intval($_POST['low_stock_threshold'] ?? 5);
        set_setting('low_stock_global_threshold', max(1, $threshold));
        
        // Save email for notifications
        $email = trim($_POST['low_stock_email'] ?? '');
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_setting('low_stock_notify_email', $email);
        }
        
        // Save email sender
        $sender_email = trim($_POST['email_from'] ?? '');
        if ($sender_email && filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
            set_setting('email_from', $sender_email);
        }
        
        $sender_name = trim($_POST['email_from_name'] ?? '');
        if ($sender_name) {
            set_setting('email_from_name', $sender_name);
        }
        
        // Save email enabled flag
        $enabled = isset($_POST['low_stock_notify_enabled']) ? 1 : 0;
        set_setting('low_stock_notify_enabled', $enabled);
        
        $message = ' Configurações guardadas com sucesso';
    } elseif ($action === 'check_now') {
        // Check for low stock and send email immediately
        $result = check_and_send_low_stock_email();
        if ($result) {
            $message = ' Email de alerta enviado com sucesso!';
        } else {
            $low_stock = list_low_stock_products();
            if (empty($low_stock)) {
                $message = ' Nenhum produto com stock baixo encontrado';
            } else {
                $message = ' Erro ao enviar email ou notificações desativadas';
            }
        }
    } elseif ($action === 'test_email') {
        $email = trim($_POST['test_email'] ?? '');
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $low_stock = list_low_stock_products();
            if (empty($low_stock)) {
                // Create a test product array if no low stock exists
                $low_stock = [[
                    'id' => 1,
                    'name' => 'TESTE - Produto Exemplo',
                    'stock' => 2,
                    'min_stock' => 5,
                    'supplier_name' => 'Fornecedor Exemplo'
                ]];
            }
            $sent = send_low_stock_email($low_stock, $email);
            $message = $sent ? ' Email de teste enviado com sucesso para ' . htmlspecialchars($email) : ' Erro ao enviar email. Verifique as credenciais SMTP.';
        } else {
            $message = ' Email inválido';
        }
    }
}

// Get current settings
$current_threshold = get_setting('low_stock_global_threshold', 5);
$current_email = get_setting('low_stock_notify_email', 'sheltzx7@gmail.com');
$notify_enabled = get_setting('low_stock_notify_enabled', 1);
$last_email_at = get_setting('low_stock_last_email_at', '');
$recovery_code = get_setting('recovery_code', '');
$email_from = get_setting('email_from', 'sheltzx7@gmail.com');
$email_from_name = get_setting('email_from_name', 'Supermercado');

$low_stock_products = list_low_stock_products($current_threshold);
?>
<h1> Configurações - Alertas de Stock Baixo</h1>

<?php if (!empty($message)): ?>
    <div class="notice" style="background: <?php echo strpos($message, '') !== false ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo strpos($message, '') !== false ? '#155724' : '#721c24'; ?>;">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<section class="forms">
    <h2> Configuração de Email (SendGrid)</h2>
    <div style="background: #e8f4f8; padding: 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #0066cc;">
        <strong style="color: #0066cc;"> SendGrid Configurado</strong><br>
        <span style="font-size: 12px; color: #333; display: block; margin-top: 8px;">
            Email provider: <strong>SendGrid SMTP</strong><br>
            Host: <strong>smtp.sendgrid.net:587</strong><br>
            API Key armazenada com segurança no banco de dados
        </span>
    </div>
    
    <form method="post">
        <input type="hidden" name="action" value="save_settings">
        
        <div class="form-group">
            <label>Limite Global de Stock Baixo
                <input type="number" name="low_stock_threshold" value="<?php echo $current_threshold; ?>" min="1" max="100" required style="width: 120px;">
                <span style="font-size: 12px; color: #666; display: block; margin-top: 4px;">
                    Produtos com stock ≤ este valor serão considerados com stock baixo
                </span>
            </label>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="low_stock_notify_enabled" value="1" <?php echo $notify_enabled ? 'checked' : ''; ?>>
                <span> Ativar notificações por email</span>
            </label>
            <span style="font-size: 12px; color: #666; display: block; margin-top: 8px;">
                Emails de alerta serão enviados automaticamente quando stock cair abaixo do limite
            </span>
        </div>

        <div class="form-group">
            <label>Email para Notificações
                <input type="email" name="low_stock_email" value="<?php echo htmlspecialchars($current_email); ?>" placeholder="sheltzx7@gmail.com" style="width: 100%;">
                <span style="font-size: 12px; color: #666; display: block; margin-top: 4px;">
                    Email onde serão recebidas as notificações de stock baixo
                </span>
            </label>
        </div>

        <div class="form-group">
            <label>Email do Remetente (From)
                <input type="email" name="email_from" value="<?php echo htmlspecialchars($email_from); ?>" placeholder="sheltzx7@gmail.com" style="width: 100%;" required>
                <span style="font-size: 12px; color: #666; display: block; margin-top: 4px;">
                    Email que aparecerá como remetente nos emails enviados
                </span>
            </label>
        </div>

        <div class="form-group">
            <label>Nome do Remetente
                <input type="text" name="email_from_name" value="<?php echo htmlspecialchars($email_from_name); ?>" placeholder="Supermercado" style="width: 100%;" required>
                <span style="font-size: 12px; color: #666; display: block; margin-top: 4px;">
                    Nome que aparecerá como remetente dos emails
                </span>
            </label>
        </div>

        <button type="submit" class="btn"> Guardar Configurações</button>
    </form>
</section>

<?php if ($notify_enabled && $current_email): ?>
    <section class="forms">
        <h2> Verificação de Stock</h2>
        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <form method="post" style="flex: 1; min-width: 300px;">
                <input type="hidden" name="action" value="check_now">
                <div style="background: #fff3cd; padding: 16px; border-radius: 8px; border-left: 4px solid #ff9800; margin-bottom: 12px;">
                    <strong style="color: #ff6f00;"> Verificação Imediata</strong><br>
                    <span style="font-size: 12px; color: #666; display: block; margin-top: 8px;">
                        Clique no botão abaixo para verificar produtos com stock baixo AGORA e enviar email se houver produtos abaixo do limite.
                    </span>
                </div>
                <button type="submit" class="btn" style="background: #ff9800; width: 100%;"> Verificar Stock Agora e Enviar Email</button>
            </form>
            
            <form method="post" style="flex: 1; min-width: 300px;">
                <input type="hidden" name="action" value="test_email">
                <div style="background: #e8f4f8; padding: 16px; border-radius: 8px; border-left: 4px solid #0066cc; margin-bottom: 12px;">
                    <strong style="color: #0066cc;"> Email de Teste</strong><br>
                    <span style="font-size: 12px; color: #666; display: block; margin-top: 8px;">
                        Envie um email de teste com produtos exemplo para verificar se o sistema está funcionando.
                    </span>
                </div>
                <label style="margin-bottom: 8px; display: block;">
                    <input type="email" name="test_email" value="<?php echo htmlspecialchars($current_email); ?>" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </label>
                <button type="submit" class="btn" style="width: 100%;"> Enviar Email de Teste</button>
            </form>
        </div>
    </section>
    
    <section class="forms">
        <h2> Status</h2>
        <?php if ($last_email_at): ?>
            <p style="font-size: 12px; color: #666; margin-top: 12px;">
                Último email enviado: <strong><?php echo date('d/m/Y H:i:s', strtotime($last_email_at)); ?></strong>
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($recovery_code): ?>
    <section class="forms">
        <h2> Código de Recuperação</h2>
        <div style="background: #fff3cd; padding: 16px; border-radius: 8px; border-left: 4px solid #ff9800;">
            <strong style="color: #ff6f00;">Código de Recuperação Armazenado</strong><br>
            <code style="display: block; background: white; padding: 12px; border-radius: 4px; margin-top: 8px; font-family: monospace; word-break: break-all;">
                <?php echo htmlspecialchars($recovery_code); ?>
            </code>
            <span style="font-size: 12px; color: #666; display: block; margin-top: 8px;">
                Este código é armazenado com segurança no banco de dados para fins de recuperação do sistema.
            </span>
        </div>
    </section>
<?php endif; ?>

<section>
    <h2> Produtos com Stock Baixo</h2>
    <?php if (empty($low_stock_products)): ?>
        <p style="color: #27ae60; font-weight: 600;"> Todos os produtos têm stock adequado</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Stock Atual</th>
                        <th>Stock Mínimo</th>
                        <th>Fornecedor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_products as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo htmlspecialchars($p['category'] ?? '-'); ?></td>
                            <td class="negative" style="font-weight: 600;"><?php echo intval($p['stock']); ?></td>
                            <td><?php echo intval($p['min_stock']); ?></td>
                            <td><?php echo htmlspecialchars($p['supplier_name'] ?? 'Sem fornecedor'); ?></td>
                            <td>
                                <a href="/modules/produtos.php?id=<?php echo $p['id']; ?>" class="btn" style="padding: 4px 8px; font-size: 12px; text-decoration: none;"> Ver</a>
                                <a href="/modules/encomendas.php" class="btn" style="padding: 4px 8px; font-size: 12px; text-decoration: none;"> Encomendar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p style="font-size: 12px; color: #666; margin-top: 12px;">
            Total: <strong><?php echo count($low_stock_products); ?></strong> produto(s) com stock baixo
        </p>
    <?php endif; ?>
</section>

<style>
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="number"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.notice {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
    border-left: 4px solid;
}

code {
    color: #d63384;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
