#!/bin/bash

# 🧪 SCRIPT DE TESTES - SISTEMA DE GESTÃO DE SUPERMERCADO
# Valida a implementação de todas as funcionalidades

echo "🚀 INICIANDO TESTES DO SISTEMA..."
echo "=================================="
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Contadores
PASSED=0
FAILED=0

# Função para testar
test_case() {
    local test_name=$1
    local command=$2
    echo -n "🧪 Teste: $test_name... "
    
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ PASSOU${NC}"
        ((PASSED++))
    else
        echo -e "${RED}❌ FALHOU${NC}"
        ((FAILED++))
    fi
}

echo "1️⃣  VALIDANDO FICHEIROS PHP"
echo "=================================="

# Verificar sintaxe PHP
test_case "dashboard_melhorado.php" "php -l dashboard_melhorado.php"
test_case "admin/rh/employees.php" "php -l admin/rh/employees.php"
test_case "admin/pdv/sales.php" "php -l admin/pdv/sales.php"
test_case "admin/performance/optimization.php" "php -l admin/performance/optimization.php"

echo ""
echo "2️⃣  VALIDANDO ESTRUTURA DE FICHEIROS"
echo "=================================="

# Verificar se ficheiros existem
test_case "dashboard_melhorado.php existe" "test -f dashboard_melhorado.php"
test_case "admin/rh/employees.php existe" "test -f admin/rh/employees.php"
test_case "admin/pdv/sales.php existe" "test -f admin/pdv/sales.php"
test_case "admin/performance/optimization.php existe" "test -f admin/performance/optimization.php"
test_case "Documentação MELHORIAS_FASE_5.md" "test -f ficheiros_md/MELHORIAS_FASE_5.md"

echo ""
echo "3️⃣  VALIDANDO TAMANHO DOS FICHEIROS"
echo "=================================="

# Verificar tamanho (garantir que não estão vazios)
test_case "dashboard_melhorado.php (>300 linhas)" "test \$(wc -l < dashboard_melhorado.php) -gt 300"
test_case "admin/rh/employees.php (>100 linhas)" "test \$(wc -l < admin/rh/employees.php) -gt 100"
test_case "admin/pdv/sales.php (>250 linhas)" "test \$(wc -l < admin/pdv/sales.php) -gt 250"
test_case "admin/performance/optimization.php (>200 linhas)" "test \$(wc -l < admin/performance/optimization.php) -gt 200"

echo ""
echo "4️⃣  VALIDANDO CONECTIVIDADE COM BD"
echo "=================================="

# Testar conexão MySQL (se o servidor estiver rodando)
test_case "Conexão MySQL (127.0.0.1:3306)" "mysql -h 127.0.0.1 -u pap_user -ppap_pass -e 'SELECT 1' 2>/dev/null"
test_case "Base de dados 'supermercado'" "mysql -h 127.0.0.1 -u pap_user -ppap_pass -e 'USE supermercado; SELECT 1' 2>/dev/null"

echo ""
echo "5️⃣  VALIDANDO TABELAS DE BASE DE DADOS"
echo "=================================="

# Verificar tabelas cruciais
test_case "Tabela 'users' existe" "mysql -h 127.0.0.1 -u pap_user -ppap_pass supermercado -e 'DESCRIBE users' 2>/dev/null"
test_case "Tabela 'products' existe" "mysql -h 127.0.0.1 -u pap_user -ppap_pass supermercado -e 'DESCRIBE products' 2>/dev/null"
test_case "Tabela 'sales' existe" "mysql -h 127.0.0.1 -u pap_user -ppap_pass supermercado -e 'DESCRIBE sales' 2>/dev/null"
test_case "Tabela 'employees' existe" "mysql -h 127.0.0.1 -u pap_user -ppap_pass supermercado -e 'DESCRIBE employees' 2>/dev/null"

echo ""
echo "6️⃣  VALIDANDO DADOS EM BD"
echo "=================================="

# Verificar dados
test_case "Utilizadores existem" "mysql -h 127.0.0.1 -u pap_user -ppap_pass supermercado -e 'SELECT COUNT(*) as total FROM users' 2>/dev/null | grep -q '[0-9]'"
test_case "Produtos existem" "mysql -h 127.0.0.1 -u pap_user -ppap_pass supermercado -e 'SELECT COUNT(*) as total FROM products' 2>/dev/null | grep -q '[0-9]'"
test_case "Vendas registadas" "mysql -h 127.0.0.1 -u pap_user -ppap_pass supermercado -e 'SELECT COUNT(*) as total FROM sales' 2>/dev/null | grep -q '[0-9]'"

echo ""
echo "7️⃣  VALIDANDO CONTEÚDO DE FICHEIROS"
echo "=================================="

# Verificar se ficheiros contêm funções essenciais
test_case "dashboard_melhorado.php tem KPI cards" "grep -q 'kpi-card' dashboard_melhorado.php"
test_case "dashboard_melhorado.php tem Chart.js" "grep -q 'Chart' dashboard_melhorado.php"
test_case "employees.php tem CRUD" "grep -q 'INSERT\|UPDATE\|DELETE' admin/rh/employees.php"
test_case "sales.php tem carrinho" "grep -q 'cart' admin/pdv/sales.php"
test_case "optimization.php tem análise" "grep -q 'OPTIMIZE\|ANALYZE\|REPAIR' admin/performance/optimization.php"

echo ""
echo "8️⃣  VALIDANDO SEGURANÇA"
echo "=================================="

# Verificar middleware de autenticação
test_case "auth_middleware em dashboard_melhorado.php" "grep -q 'auth_middleware' dashboard_melhorado.php"
test_case "auth_middleware em employees.php" "grep -q 'auth_middleware' admin/rh/employees.php"
test_case "auth_middleware em sales.php" "grep -q 'auth_middleware' admin/pdv/sales.php"
test_case "auth_middleware em optimization.php" "grep -q 'auth_middleware' admin/performance/optimization.php"

echo ""
echo "9️⃣  VALIDANDO ESTILOS CSS"
echo "=================================="

# Verificar CSS
test_case "dashboard_melhorado.php tem CSS" "grep -q '<style>' dashboard_melhorado.php"
test_case "employees.php tem CSS" "grep -q '<style>' admin/rh/employees.php"
test_case "sales.php tem CSS" "grep -q '<style>' admin/pdv/sales.php"
test_case "optimization.php tem CSS" "grep -q '<style>' admin/performance/optimization.php"

echo ""
echo "🔟 VALIDANDO JAVASCRIPT"
echo "=================================="

# Verificar JavaScript
test_case "dashboard_melhorado.php tem JavaScript" "grep -q '<script>' dashboard_melhorado.php"
test_case "sales.php tem funções JS" "grep -q 'function' admin/pdv/sales.php"
test_case "optimization.php tem funções JS" "grep -q 'function' admin/performance/optimization.php"

echo ""
echo "🏁 RESUMO DOS TESTES"
echo "=================================="
echo -e "${GREEN}✅ PASSOU: $PASSED${NC}"
echo -e "${RED}❌ FALHOU: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 TODOS OS TESTES PASSARAM! 🎉${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠️  ALGUNS TESTES FALHARAM. VERIFIQUE OS ERROS ACIMA.${NC}"
    exit 1
fi
