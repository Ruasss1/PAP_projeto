#!/bin/bash
# start_server.sh - Iniciar servidor PHP para PAP Project

echo "🚀 Iniciando Servidor PHP..."
echo ""

# Parar qualquer servidor anterior
echo "⏸️  Parando servidores anteriores..."
pkill -f "php -S" 2>/dev/null

# Aguardar um momento
sleep 1

# Iniciar servidor
echo "▶️  Iniciando servidor em http://localhost:8000"
cd /Users/vascoruas/Documents/PAP_projeto
php -S localhost:8000

# Se chegar aqui, o servidor foi parado
echo ""
echo "⏹️  Servidor parado."
