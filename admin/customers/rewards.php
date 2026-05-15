<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

if (!$auth->is_authenticated()) {
    header('Location: /login.php');
    exit;
}

// Obter recompensas disponíveis
$pdo = db_connect();
$rewards = [];

// Se foi feito resgate
if (isset($_POST['redeem_reward'])) {
    $customer_id = (int)$_POST['customer_id'];
    $reward_id = (int)$_POST['reward_id'];
    
    // Implementação simplificada de resgate
    $message = "Funcionalidade de recompensas em desenvolvimento.";
}

// Busca de clientes
$search_customer = isset($_GET['customer']) ? trim($_GET['customer']) : '';
$matching_customers = [];

if (!empty($search_customer)) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE name LIKE :search OR email LIKE :search OR nif LIKE :search ORDER BY name LIMIT 5");
    $stmt->execute([':search' => "%{$search_customer}%"]);
    $matching_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Recompensas - PAP Supermercado</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .rewards-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #ffaa00;
        }

        .header h1 {
            color: #ffaa00;
            margin: 0;
            font-size: 28px;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-title {
            color: #00d4ff;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00d4ff;
        }

        .customer-selector {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .customer-search-box {
            position: relative;
            margin-bottom: 15px;
        }

        .customer-search-box input {
            width: 100%;
            background: #2a2a2a;
            color: #fff;
            border: 1px solid #444;
            padding: 12px 15px;
            border-radius: 4px;
            font-size: 14px;
        }

        .customer-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #2a2a2a;
            border: 1px solid #444;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 10;
        }

        .customer-suggestions.show {
            display: block;
        }

        .suggestion-item {
            padding: 10px 15px;
            border-bottom: 1px solid #333;
            cursor: pointer;
            color: #aaa;
            transition: all 0.2s;
        }

        .suggestion-item:hover,
        .suggestion-item.selected {
            background: #1a1a1a;
            color: #00d4ff;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .reward-card {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            border: 2px solid #333;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .reward-card:hover {
            border-color: #ffaa00;
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(255, 170, 0, 0.2);
        }

        .reward-icon {
            font-size: 40px;
            margin-bottom: 12px;
            text-align: center;
        }

        .reward-type {
            background: #ffaa00;
            color: #000;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 10px;
            width: fit-content;
        }

        .reward-title {
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .reward-description {
            color: #aaa;
            font-size: 13px;
            margin-bottom: 12px;
            flex-grow: 1;
        }

        .reward-value {
            color: #00d4ff;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .reward-points {
            background: #ffaa00;
            color: #000;
            padding: 10px 15px;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 12px;
            font-weight: bold;
        }

        .reward-stock {
            font-size: 12px;
            color: #888;
            margin-bottom: 12px;
        }

        .btn-redeem {
            background: #00d4ff;
            color: #000;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-redeem:hover {
            background: #00a8cc;
        }

        .btn-redeem:disabled {
            background: #666;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .message {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.success {
            background: #1a3a1a;
            color: #00ff00;
            border: 1px solid #00ff00;
        }

        .message.error {
            background: #3a1a1a;
            color: #ff4444;
            border: 1px solid #ff4444;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        @media (max-width: 768px) {
            .rewards-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body class="dark-theme">
    <?php include '../../includes/header.php'; ?>

    <div class="rewards-container">
        <div class="header">
            <div>
                <h1> Catálogo de Recompensas</h1>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, '') === 0 ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Selecionador de Cliente -->
        <div class="section">
            <div class="section-title"> Selecionar Cliente para Resgate</div>
            <div class="customer-selector">
                <div class="customer-search-box">
                    <input 
                        type="text" 
                        id="customerSearch" 
                        placeholder="Procurar cliente por nome, email ou cartão fidelidade..."
                        value="<?php echo htmlspecialchars($search_customer); ?>"
                        autocomplete="off"
                    >
                    <div class="customer-suggestions" id="suggestions"></div>
                </div>
                <div id="selectedCustomer" style="color: #aaa; margin-top: 10px;"></div>
            </div>
        </div>

        <!-- Recompensas Disponíveis -->
        <div class="section">
            <div class="section-title"> Recompensas Disponíveis</div>
            
            <?php if (!empty($rewards)): ?>
                <div class="rewards-grid">
                    <?php foreach ($rewards as $reward): 
                        $stock_text = is_null($reward['stock_quantity']) 
                            ? 'Stock Ilimitado' 
                            : ($reward['stock_quantity'] > 0 ? $reward['stock_quantity'] . ' em stock' : 'Sem stock');
                        
                        $icons = [
                            'Desconto' => '',
                            'Produto' => '',
                            'Voucher' => '',
                            'Cashback' => ''
                        ];
                        $icon = $icons[$reward['reward_type']] ?? '';
                    ?>
                        <div class="reward-card" data-reward-id="<?php echo $reward['id']; ?>" data-points="<?php echo $reward['points_required']; ?>">
                            <div class="reward-icon"><?php echo $icon; ?></div>
                            <span class="reward-type"><?php echo htmlspecialchars($reward['reward_type']); ?></span>
                            <div class="reward-title"><?php echo htmlspecialchars($reward['name']); ?></div>
                            <div class="reward-description"><?php echo htmlspecialchars($reward['description'] ?? ''); ?></div>
                            
                            <?php if ($reward['reward_type'] === 'Desconto' || $reward['reward_type'] === 'Cashback'): ?>
                                <div class="reward-value">
                                    <?php echo htmlspecialchars($reward['reward_value']); ?>
                                    <?php echo $reward['reward_type'] === 'Cashback' ? '%' : '€'; ?>
                                </div>
                            <?php elseif ($reward['reward_type'] === 'Voucher'): ?>
                                <div class="reward-value">€<?php echo number_format($reward['reward_value'], 2); ?></div>
                            <?php endif; ?>
                            
                            <div class="reward-points">
                                 <?php echo $reward['points_required']; ?> pontos
                            </div>
                            
                            <div class="reward-stock">
                                 <?php echo htmlspecialchars($stock_text); ?>
                            </div>
                            
                            <button 
                                class="btn-redeem" 
                                onclick="redeemReward(<?php echo $reward['id']; ?>)"
                                id="btn-<?php echo $reward['id']; ?>"
                                disabled
                            >
                                Resgatar
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Nenhuma recompensa disponível no momento</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <form id="redeemForm" method="POST" style="display: none;">
        <input type="hidden" name="redeem_reward" value="1">
        <input type="hidden" name="customer_id" id="formCustomerId">
        <input type="hidden" name="reward_id" id="formRewardId">
    </form>

    <script>
        let selectedCustomerId = null;
        let customers = [];

        // Buscar clientes quando o utilizador digita
        document.getElementById('customerSearch').addEventListener('input', async function() {
            const search = this.value.trim();
            const suggestionsBox = document.getElementById('suggestions');

            if (search.length < 2) {
                suggestionsBox.classList.remove('show');
                selectedCustomerId = null;
                return;
            }

            // Aqui iria fazer a busca - por agora usando dados hardcoded
            try {
                const response = await fetch('../../api/customers.php?search=' + encodeURIComponent(search));
                const data = await response.json();
                
                if (data.customers && data.customers.length > 0) {
                    suggestionsBox.innerHTML = data.customers.map(c => `
                        <div class="suggestion-item" onclick="selectCustomer(${c.id}, '${c.name} - ${c.loyalty_card_number}', ${c.points_balance})">
                             ${c.name}<br>
                            <small>${c.loyalty_card_number} | Pontos: ${c.points_balance}</small>
                        </div>
                    `).join('');
                    suggestionsBox.classList.add('show');
                } else {
                    suggestionsBox.classList.remove('show');
                }
            } catch (e) {
                console.error(e);
            }
        });

        function selectCustomer(id, name, points) {
            selectedCustomerId = id;
            document.getElementById('customerSearch').value = name + ' (' + points + ' pts)';
            document.getElementById('selectedCustomer').innerHTML = `
                <strong style="color: #00d4ff;"> ${name}</strong><br>
                <small>Saldo de pontos: <strong>${points}</strong></small>
            `;
            document.getElementById('suggestions').classList.remove('show');
            
            // Atualizar botões de resgate
            document.querySelectorAll('.btn-redeem').forEach(btn => {
                const requiredPoints = parseInt(btn.closest('.reward-card').dataset.points);
                btn.disabled = points < requiredPoints;
            });
        }

        function redeemReward(rewardId) {
            if (!selectedCustomerId) {
                alert('Selecione um cliente primeiro');
                return;
            }
            
            document.getElementById('formCustomerId').value = selectedCustomerId;
            document.getElementById('formRewardId').value = rewardId;
            document.getElementById('redeemForm').submit();
        }

        // Se houver cliente já selecionado, carregar a página
        <?php if (!empty($matching_customers)): ?>
            const firstCustomer = <?php echo json_encode($matching_customers[0]); ?>;
            selectCustomer(firstCustomer.id, firstCustomer.name, firstCustomer.points_balance);
        <?php endif; ?>
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
