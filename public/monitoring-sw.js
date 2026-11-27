const CACHE = 'cbed-monitoring-v7';
const SHELL = '/monitoring/offline-shell';
const CLIENT_SCRIPT = '/js/monitoring-offline.js?v=7';
const CORE_ASSETS = [
    CLIENT_SCRIPT, '/css/cbed-typography.css?v=1',
    '/fonts/inter/InterVariable.woff2', '/fonts/inter/InterVariable-Italic.woff2',
];
const ASSETS = [
    ...CORE_ASSETS, '/css/cbed-buttons.css', '/css/table-drag-scroll.css',
    '/css/cbed-mobile.css?v=3', '/js/cbed-mobile.js?v=3',
    '/js/table-drag-scroll.js', '/js/auto-filters.js', '/js/table-pagination.js',
    '/monitoring.webmanifest', '/images/monitoring-icon-192.png', '/images/monitoring-icon-512.png',
    'https://cdn.tailwindcss.com/',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/webfonts/fa-solid-900.woff2',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/webfonts/fa-regular-400.woff2',
];
const assets = new Set(ASSETS.map(path => new URL(path, self.location.origin).href));
let version = 0;
const accountVersions = new Map();
let shellWrite = Promise.resolve();

function writeShell(change) {
    const update = shellWrite.catch(() => {}).then(async () => change(await caches.open(CACHE)));
    shellWrite = update;
    return update;
}

async function notify(message) {
    for (const client of await self.clients.matchAll({type: 'window', includeUncontrolled: true})) {
        if (new URL(client.url).pathname === '/monitoring') client.postMessage(message);
    }
}

function invalidate(account) {
    if (account) accountVersions.set(account, (accountVersions.get(account) ?? 0) + 1);
    else version++;
    return writeShell(async cache => {
        const shell = await cache.match(SHELL);
        const owner = shell?.headers.get('X-CBED-Monitoring-Account');
        if (account && owner && owner !== account) return;
        await cache.delete(SHELL);
        await notify({type: 'monitoring-locked', account: account || null});
    });
}

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        await cache.addAll(CORE_ASSETS);
        await Promise.allSettled(ASSETS.filter(path => !CORE_ASSETS.includes(path)).map(async path => {
            const response = await fetch(path).catch(() => fetch(path, {mode: 'no-cors'}));
            if (response.ok || response.type === 'opaque') await cache.put(path, response);
        }));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => event.waitUntil((async () => {
    for (const key of await caches.keys()) {
        if (key.startsWith('cbed-monitoring-') && key !== CACHE) await caches.delete(key);
    }
    await self.clients.claim();
})()));

self.addEventListener('message', event => {
    if (!event.source?.url || new URL(event.source.url).pathname !== '/monitoring') return;
    const {type, account} = event.data ?? {};
    if (!/^\d+$/.test(account ?? '')) return;
    if (type === 'lock-monitoring') {
        event.waitUntil(invalidate(account));
        return;
    }
    if (type !== 'prepare-monitoring') return;
    const expectedVersion = version;
    const expectedAccountVersion = accountVersions.get(account);
    event.waitUntil((async () => {
        const response = await fetch(SHELL, {credentials: 'same-origin', cache: 'no-store'});
        if (!response.ok || response.redirected || response.headers.get('X-CBED-Monitoring-Account') !== account) return;
        await writeShell(async cache => {
            if (expectedVersion !== version || expectedAccountVersion !== accountVersions.get(account)) return;
            await cache.put(SHELL, response);
            event.source.postMessage({type: 'monitoring-shell-ready', account});
        });
    })().catch(() => {}));
});

self.addEventListener('fetch', event => {
    const {request} = event;
    const url = new URL(request.url);
    const local = url.origin === self.location.origin;
    if (local && request.method === 'POST' && ['/login', '/logout'].includes(url.pathname)) {
        event.respondWith((async () => {
            try {
                const response = await fetch(request);
                if (response.ok || response.type === 'opaqueredirect' || response.redirected) await invalidate().catch(() => {});
                return response;
            } catch (error) {
                if (url.pathname === '/logout' && request.referrer.startsWith(self.location.origin + '/')) {
                    await invalidate().catch(() => {});
                }
                throw error;
            }
        })());
        return;
    }
    if (local && request.mode === 'navigate' && url.pathname === '/login') {
        event.respondWith((async () => {
            const response = await fetch(request);
            if (response.ok && !response.redirected) await invalidate();
            return response;
        })());
        return;
    }
    if (local && request.mode === 'navigate' && url.pathname === '/monitoring') {
        event.respondWith((async () => {
            try {
                const response = await fetch(request);
                if (response.status === 401 || response.status === 403 || (response.redirected && new URL(response.url).pathname === '/login')) {
                    await invalidate();
                }
                return response;
            } catch {
                const shell = await (await caches.open(CACHE)).match(SHELL);
                return shell || new Response('Animal Monitoring is unavailable offline. Sign in and open Monitoring while connected first.', {
                    status: 503, headers: {'Content-Type': 'text/plain; charset=UTF-8'},
                });
            }
        })());
        return;
    }
    if (request.method === 'GET' && assets.has(url.href)) {
        event.respondWith((async () => {
            const client = event.clientId ? await self.clients.get(event.clientId) : null;
            if (!client || new URL(client.url).pathname !== '/monitoring') return fetch(request);
            const cache = await caches.open(CACHE);
            const cached = await cache.match(request);
            if (cached && (cached.type !== 'opaque' || request.mode === 'no-cors')) return cached;
            const response = await fetch(request);
            if (response.ok || response.type === 'opaque') await cache.put(request, response.clone());
            return response;
        })());
    }
});
