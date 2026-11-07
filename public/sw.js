/*
 * Service worker do app Ducks Offroad (PWA).
 *
 * Segurança e privacidade:
 *  - páginas HTML NUNCA são guardadas em cache (podem conter dados pessoais,
 *    token CSRF, painel admin); sem internet mostramos /offline.html
 *  - só arquivos públicos e estáticos vão para o cache: CSS, JS, ícones e as
 *    imagens públicas de /uploads (fotos da galeria, banners, Instagram)
 *  - nada além de GET do próprio domínio passa pelo cache
 */
'use strict';

const VERSION = 'v2';
const STATIC_CACHE = `ducks-static-${VERSION}`;
const IMAGE_CACHE = `ducks-images-${VERSION}`;
const MAX_IMAGES = 150;

const PRECACHE = [
  '/offline.html',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/offline.js',
  '/assets/img/logo.svg',
  '/assets/img/avatar.svg',
  '/assets/img/hero-pattern.svg',
  '/assets/icons/icon-192.png',
  '/assets/icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE.map((url) => new Request(url, { cache: 'reload' }))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k.startsWith('ducks-') && k !== STATIC_CACHE && k !== IMAGE_CACHE).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

// O site pede para limpar o cache (ex.: ao sair da conta)
self.addEventListener('message', (event) => {
  if (event.origin && event.origin !== self.location.origin) return;
  if (event.data === 'clear-image-cache') {
    event.waitUntil(caches.delete(IMAGE_CACHE));
  }
});

// ---------- Notificações push ----------
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (_) { data = {}; }
  const title = String(data.title || 'Ducks Offroad').slice(0, 120);
  const options = {
    body: String(data.body || 'Novidades na turma!').slice(0, 300),
    icon: '/assets/icons/icon-192.png',
    badge: '/assets/icons/icon-192.png',
    tag: String(data.tag || 'ducks'),
    data: { url: typeof data.url === 'string' && data.url.startsWith('/') ? data.url : '/' },
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || '/', self.location.origin);
  if (target.origin !== self.location.origin) return; // nunca abre sites externos
  event.waitUntil((async () => {
    const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      if (new URL(c.url).origin === self.location.origin && 'focus' in c) {
        await c.focus();
        return c.navigate ? c.navigate(target.href) : undefined;
      }
    }
    return self.clients.openWindow(target.href);
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // Google Fonts, Instagram etc.: direto da rede

  // Navegação (páginas): sempre da rede; sem internet, página offline
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // CSS/JS/ícones: rápido do cache e atualiza em segundo plano
  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(staleWhileRevalidate(req, STATIC_CACHE));
    return;
  }

  // Imagens públicas enviadas (nomes aleatórios e imutáveis): cache primeiro
  if (url.pathname.startsWith('/uploads/') && /\.jpg$/.test(url.pathname)) {
    event.respondWith(cacheFirst(req, IMAGE_CACHE));
  }
  // Demais requisições (ex.: .ics, .csv, .json de dados pessoais): rede, sem cache
});

async function staleWhileRevalidate(req, cacheName) {
  const cache = await caches.open(cacheName);
  // chave sem "?v=hash" para manter só uma cópia de cada arquivo
  const url = new URL(req.url);
  const key = url.origin + url.pathname;
  const cached = await cache.match(key);
  const network = fetch(req).then((res) => {
    if (res.ok && res.type === 'basic') cache.put(key, res.clone());
    return res;
  }).catch(() => cached || Response.error());
  return cached || network;
}

async function cacheFirst(req, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(req);
  if (cached) return cached;
  try {
    const res = await fetch(req);
    if (res.ok && res.type === 'basic') {
      await cache.put(req, res.clone());
      trimCache(cacheName, MAX_IMAGES);
    }
    return res;
  } catch (e) {
    return new Response('', { status: 504, statusText: 'Offline' });
  }
}

async function trimCache(cacheName, max) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - max; i++) {
    await cache.delete(keys[i]);
  }
}
