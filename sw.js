// Service Worker DISABLED - Cache removido
// Este service worker vai auto-destruir-se para limpar o cache

const CACHE_NAME = 'pap-supermercado-DISABLED';

// Ao instalar, remove todos os caches antigos
self.addEventListener('install', event => {
    console.log('[SW] Auto-destruindo service worker...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    console.log('[SW] Removendo cache:', cacheName);
                    return caches.delete(cacheName);
                })
            );
        }).then(() => {
            console.log('[SW] Todos os caches removidos');
            return self.skipWaiting();
        })
    );
});

// Ao ativar, remove-se a si próprio
self.addEventListener('activate', event => {
    console.log('[SW] Desregistando service worker...');
    event.waitUntil(
        self.registration.unregister().then(() => {
            console.log('[SW] Service worker desregistado');
            return self.clients.claim();
        })
    );
});

// Não interceptar mais nenhuma requisição
self.addEventListener('fetch', event => {
    // Deixar todas as requisições passar normalmente
    return;
    // Ignorar requisições não-GET
    if (event.request.method !== 'GET') {
        return;
    }

    // Ignorar requisições de API
    if (event.request.url.includes('/api/') || event.request.url.includes('action=')) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Retornar do cache se disponível
                if (response) {
                    return response;
                }

                // Fazer requisição à rede
                return fetch(event.request)
                    .then(response => {
                        // Não cachear respostas inválidas
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clonar resposta para cache
                        const responseToCache = response.clone();
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    })
                    .catch(() => {
                        // Se offline e for navegação, mostrar página offline
                        if (event.request.mode === 'navigate') {
                            return caches.match(OFFLINE_URL);
                        }
                    });
            })
    );
});

// Push Notifications
self.addEventListener('push', event => {
    console.log('[SW] Push recebido');
    
    const options = {
        body: event.data ? event.data.text() : 'Nova notificação',
        icon: '/assets/icons/icon-192.png',
        badge: '/assets/icons/icon-72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            { action: 'explore', title: 'Ver detalhes' },
            { action: 'close', title: 'Fechar' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('PAP Supermercado', options)
    );
});

// Clique em notificação
self.addEventListener('notificationclick', event => {
    console.log('[SW] Clique na notificação');
    event.notification.close();

    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/admin/notifications/')
        );
    }
});

// Sync em background
self.addEventListener('sync', event => {
    console.log('[SW] Background sync:', event.tag);
    
    if (event.tag === 'sync-sales') {
        event.waitUntil(syncSales());
    }
});

// Função para sincronizar vendas offline
async function syncSales() {
    try {
        const offlineSales = await getOfflineSales();
        
        for (const sale of offlineSales) {
            await fetch('/api/sales.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sale)
            });
        }
        
        await clearOfflineSales();
        console.log('[SW] Vendas sincronizadas com sucesso');
    } catch (error) {
        console.error('[SW] Erro ao sincronizar:', error);
    }
}

// IndexedDB para vendas offline
function getOfflineSales() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('pap-offline', 1);
        
        request.onerror = () => reject(request.error);
        
        request.onsuccess = () => {
            const db = request.result;
            const tx = db.transaction('sales', 'readonly');
            const store = tx.objectStore('sales');
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = () => resolve(getAllRequest.result);
            getAllRequest.onerror = () => reject(getAllRequest.error);
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            db.createObjectStore('sales', { keyPath: 'id', autoIncrement: true });
        };
    });
}

function clearOfflineSales() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('pap-offline', 1);
        
        request.onsuccess = () => {
            const db = request.result;
            const tx = db.transaction('sales', 'readwrite');
            const store = tx.objectStore('sales');
            store.clear();
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        };
    });
}
