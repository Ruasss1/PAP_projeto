<?php
/**
 * CONFIGURAÇÕES DO SISTEMA DE CAIXA (PDV)
 * Ficheiro: caixa/config.php
 * 
 * Define constantes e parâmetros para o ponto de venda:
 * - Moeda e taxas de IVA
 * - Configurações de impressão
 * - Métodos de pagamento
 * - Limites de desconto e turno
 */

// Incluir configurações principais da base de dados
require_once __DIR__ . '/../config/database.php';

// Middleware garante redirecionamento para login se não autenticado
require_once __DIR__ . '/../includes/auth_middleware.php';

// ============================================
// CONFIGURAÇÕES GERAIS DO PDV
// ============================================

define('PDV_VERSION', '1.0.0');           // Versão do sistema PDV
define('PDV_CURRENCY', '€');              // Símbolo da moeda
define('PDV_TAX_RATE', 0.23);             // Taxa de IVA padrão (23%)

// ============================================
// CONFIGURAÇÕES DE IMPRESSÃO
// ============================================

define('PDV_PRINTER_WIDTH', 80);          // Largura do papel em mm
define('PDV_RECEIPT_COPIES', 2);          // Número de cópias: Original + Cópia

// ============================================
// CONFIGURAÇÕES DE TURNO
// ============================================

define('PDV_SHIFT_DURATION_HOURS', 8);    // Duração padrão do turno em horas
define('PDV_MAX_CASH_DRAWER', 1000.00);   // Limite para sangria automática

// ============================================
// CONFIGURAÇÕES DE VENDAS
// ============================================

define('PDV_MAX_DISCOUNT_PERCENT', 50);           // Desconto máximo sem aprovação (%)
define('PDV_SUSPENDED_SALE_EXPIRY_HOURS', 24);    // Expiração de vendas suspensas

// Categorias que requerem pesagem (balança)
$PDV_WEIGHTED_CATEGORIES = ['Frutas', 'Legumes', 'Carnes', 'Peixe'];

// ============================================
// MÉTODOS DE PAGAMENTO
// ============================================

$PDV_PAYMENT_METHODS = [
    'cash' => ['name' => 'Dinheiro', 'icon' => '', 'enabled' => true],
    'debit_card' => ['name' => 'Multibanco', 'icon' => '', 'enabled' => false],
    'credit_card' => ['name' => 'Cartão Crédito', 'icon' => '', 'enabled' => false],
    'mbway' => ['name' => 'MB WAY', 'icon' => '', 'enabled' => false],
    'voucher' => ['name' => 'Vale/Gift Card', 'icon' => '', 'enabled' => false],
    'mixed' => ['name' => 'Pagamento Misto', 'icon' => '', 'enabled' => false],
];

// ============================================
// DADOS DO UTILIZADOR ATUAL
// ============================================

// Obter dados do utilizador via gestor de autenticação
if (isset($auth) && method_exists($auth, 'get_current_user')) {
    $current_user = $auth->get_current_user();
} else {
    // Fallback se auth não disponível
    $current_user = [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['email'] ?? 'Utilizador',
        'role_name' => 'caixa'
    ];
}

?>
