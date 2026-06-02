const { app, BrowserWindow, Menu, shell, dialog } = require('electron');
const path = require('path');

// Configuração do servidor
const SERVER_URL = 'http://localhost:8000';

let mainWindow;

function createWindow() {
    // Criar janela principal
    mainWindow = new BrowserWindow({
        width: 1400,
        height: 900,
        minWidth: 1024,
        minHeight: 768,
        icon: path.join(__dirname, 'icons', 'icon.png'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            webSecurity: true
        },
        titleBarStyle: 'default',
        show: false,
        backgroundColor: '#0a0a0a'
    });

    // Menu da aplicação
    const menuTemplate = [
        {
            label: 'PAP Supermercado',
            submenu: [
                { label: 'Sobre', click: showAbout },
                { type: 'separator' },
                { label: 'Recarregar', accelerator: 'CmdOrCtrl+R', click: () => mainWindow.reload() },
                { label: 'Limpar Cache', accelerator: 'CmdOrCtrl+Shift+R', click: clearCache },
                { type: 'separator' },
                { label: 'Sair', accelerator: 'CmdOrCtrl+Q', click: () => app.quit() }
            ]
        },
        {
            label: 'Editar',
            submenu: [
                { label: 'Desfazer', accelerator: 'CmdOrCtrl+Z', role: 'undo' },
                { label: 'Refazer', accelerator: 'CmdOrCtrl+Shift+Z', role: 'redo' },
                { type: 'separator' },
                { label: 'Cortar', accelerator: 'CmdOrCtrl+X', role: 'cut' },
                { label: 'Copiar', accelerator: 'CmdOrCtrl+C', role: 'copy' },
                { label: 'Colar', accelerator: 'CmdOrCtrl+V', role: 'paste' },
                { label: 'Selecionar Tudo', accelerator: 'CmdOrCtrl+A', role: 'selectAll' }
            ]
        },
        {
            label: 'Navegação',
            submenu: [
                { label: '🏠 Dashboard', click: () => mainWindow.loadURL(SERVER_URL) },
                { label: '🛒 Caixa PDV', click: () => mainWindow.loadURL(`${SERVER_URL}/caixa/`) },
                { label: '📦 Produtos', click: () => mainWindow.loadURL(`${SERVER_URL}/modules/produtos.php`) },
                { label: '👥 RH', click: () => mainWindow.loadURL(`${SERVER_URL}/admin/rh/`) },
                { type: 'separator' },
                { label: '⬅️ Voltar', accelerator: 'Alt+Left', click: () => mainWindow.webContents.goBack() },
                { label: '➡️ Avançar', accelerator: 'Alt+Right', click: () => mainWindow.webContents.goForward() }
            ]
        },
        {
            label: 'Ver',
            submenu: [
                { label: 'Zoom +', accelerator: 'CmdOrCtrl+Plus', click: () => zoomIn() },
                { label: 'Zoom -', accelerator: 'CmdOrCtrl+-', click: () => zoomOut() },
                { label: 'Zoom Normal', accelerator: 'CmdOrCtrl+0', click: () => resetZoom() },
                { type: 'separator' },
                { label: 'Ecrã Completo', accelerator: 'F11', click: () => mainWindow.setFullScreen(!mainWindow.isFullScreen()) },
                { type: 'separator' },
                { label: 'DevTools', accelerator: 'F12', click: () => mainWindow.webContents.toggleDevTools() }
            ]
        },
        {
            label: 'Ajuda',
            submenu: [
                { label: 'Documentação', click: () => shell.openExternal('https://github.com/vascoruas/PAP_projeto') },
                { label: 'Reportar Problema', click: showReportDialog }
            ]
        }
    ];

    const menu = Menu.buildFromTemplate(menuTemplate);
    Menu.setApplicationMenu(menu);

    // Carregar página inicial
    mainWindow.loadURL(SERVER_URL);

    // Mostrar janela quando pronta
    mainWindow.once('ready-to-show', () => {
        mainWindow.show();
    });

    // Tratar erros de carregamento
    mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
        mainWindow.loadFile(path.join(__dirname, 'error.html'));
    });

    // Abrir links externos no browser
    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (!url.startsWith(SERVER_URL)) {
            shell.openExternal(url);
            return { action: 'deny' };
        }
        return { action: 'allow' };
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

// Funções auxiliares
function showAbout() {
    dialog.showMessageBox(mainWindow, {
        type: 'info',
        title: 'Sobre PAP Supermercado',
        message: 'PAP Supermercado v1.0.0',
        detail: 'Sistema de Gestão de Supermercado\n\nDesenvolvido por Vasco Ruas\nProjeto PAP 2026\n\n© 2026 Todos os direitos reservados'
    });
}

function showReportDialog() {
    dialog.showMessageBox(mainWindow, {
        type: 'info',
        title: 'Reportar Problema',
        message: 'Para reportar um problema:',
        detail: 'Envie um email para: suporte@papsupermercado.pt\n\nOu abra um issue no GitHub.'
    });
}

function clearCache() {
    mainWindow.webContents.session.clearCache().then(() => {
        mainWindow.reload();
    });
}

let currentZoom = 1.0;
function zoomIn() {
    currentZoom = Math.min(currentZoom + 0.1, 2.0);
    mainWindow.webContents.setZoomFactor(currentZoom);
}
function zoomOut() {
    currentZoom = Math.max(currentZoom - 0.1, 0.5);
    mainWindow.webContents.setZoomFactor(currentZoom);
}
function resetZoom() {
    currentZoom = 1.0;
    mainWindow.webContents.setZoomFactor(currentZoom);
}

// Eventos da aplicação
app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
    }
});

// Segurança
app.on('web-contents-created', (event, contents) => {
    contents.on('will-navigate', (event, navigationUrl) => {
        const parsedUrl = new URL(navigationUrl);
        if (parsedUrl.origin !== SERVER_URL && parsedUrl.origin !== 'http://localhost:8000') {
            // Permitir navegação interna
        }
    });
});
