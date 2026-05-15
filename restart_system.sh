#!/bin/bash

echo "🚀 RESTART COMPLETO DO SISTEMA PAP"
echo "=================================="

# Matar processo PHP se estiver a correr
echo "🔪 Terminando servidor PHP..."
pkill -f "php -S localhost:8000" 2>/dev/null

# Limpar logs
echo "🗑️ Limpando logs..."
> /tmp/php_server.log 2>/dev/null

# Esperar um pouco
sleep 2

# Reiniciar servidor
echo "🔄 Reiniciando servidor na porta 8000..."
cd /Users/vascoruas/Documents/PAP_projeto
nohup php -S localhost:8000 > /tmp/php_server.log 2>&1 &

# Esperar servidor iniciar
sleep 3

# Verificar se está a correr
if lsof -i :8000 > /dev/null 2>&1; then
    echo "✅ Servidor ativo na porta 8000"
else
    echo "❌ Erro ao iniciar servidor"
    exit 1
fi

# Testar conexão à base de dados
echo "🔗 Testando base de dados..."
php -r "
require_once 'includes/functions.php';
\$pdo = db_connect();
if (\$pdo) {
    echo '✅ Base de dados conectada\n';
    \$count = \$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo '📦 Produtos: ' . \$count . '\n';
} else {
    echo '❌ Erro na base de dados\n';
    exit 1;
}
"

echo ""
echo "🎉 SISTEMA PRONTO!"
echo "=================="
echo "🌐 URL: http://localhost:8000/reset.php"
echo "🔑 Login: admin@example.com / admin123"
echo ""
echo "1. Vai a http://localhost:8000/reset.php primeiro"
echo "2. Depois a http://localhost:8000/login.php"
echo "3. Faz login e testa o sistema"
echo ""