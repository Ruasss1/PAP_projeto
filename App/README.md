# PAP Supermercado - Aplicação Desktop

Aplicação desktop do Sistema de Gestão PAP Supermercado, construída com Electron.

## 📋 Requisitos

- Node.js 18+ 
- npm ou yarn
- Servidor PHP a correr em localhost:8000

## 🚀 Instalação

```bash
# Navegar até à pasta App
cd App

# Instalar dependências
npm install
```

## ▶️ Executar

**Importante:** O servidor PHP deve estar a correr primeiro!

```bash
# Terminal 1 - Iniciar servidor PHP (na pasta raiz do projeto)
cd /Users/vascoruas/Documents/PAP_projeto
php -S localhost:8000

# Terminal 2 - Iniciar aplicação (na pasta App)
cd /Users/vascoruas/Documents/PAP_projeto/App
npm start
```

## 📦 Criar Instalador

```bash
# Para macOS
npm run build:mac

# Para Windows
npm run build:win

# Para Linux
npm run build:linux

# Para todos
npm run build
```

Os instaladores serão gerados na pasta `dist/`.

## 🎨 Funcionalidades

- ✅ Interface nativa do sistema operativo
- ✅ Menu de navegação rápida
- ✅ Atalhos de teclado (Ctrl+R, F11, etc.)
- ✅ Zoom in/out
- ✅ Ecrã completo
- ✅ Página de erro amigável se o servidor não estiver disponível

## ⌨️ Atalhos

| Atalho | Ação |
|--------|------|
| Ctrl+R | Recarregar página |
| Ctrl+Shift+R | Limpar cache e recarregar |
| Ctrl+Q | Sair |
| F11 | Ecrã completo |
| F12 | DevTools |
| Ctrl++ | Zoom in |
| Ctrl+- | Zoom out |
| Ctrl+0 | Zoom normal |
| Alt+← | Voltar |
| Alt+→ | Avançar |

## 📁 Estrutura

```
App/
├── main.js          # Entry point Electron
├── package.json     # Configuração e dependências
├── error.html       # Página de erro de conexão
├── icons/           # Ícones da aplicação
│   └── icon.svg
├── dist/            # Instaladores (após build)
└── README.md        # Este ficheiro
```

---

Desenvolvido para o Projeto PAP 2026
