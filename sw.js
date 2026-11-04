// Service worker da Tabuada Divertida.
// Guarda os arquivos fixos (visual, ícones) e mostra uma tela amigável quando estiver sem internet.
const VERSAO = 'tabuada-v2';
const ARQUIVOS = [
  'estilo.css',
  'app.js',
  'offline.html',
  'manifest.webmanifest',
  'icones/icone.svg',
  'icones/icone-192.png',
  'icones/icone-512.png',
  'icones/icone-maskable-512.png',
  'icones/apple-touch-icon.png',
  'icones/favicon-32.png',
];

self.addEventListener('install', (evento) => {
  evento.waitUntil(caches.open(VERSAO).then((cache) => cache.addAll(ARQUIVOS)));
  self.skipWaiting();
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches.keys()
      .then((nomes) => Promise.all(nomes.filter((n) => n !== VERSAO).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (evento) => {
  const req = evento.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== self.location.origin) {
    return; // respostas (POST) sempre vão direto para o servidor
  }

  // Páginas: sempre busca no servidor (as contas são geradas pelo PHP); sem internet, mostra o aviso.
  if (req.mode === 'navigate') {
    evento.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }

  // Arquivos fixos: usa o que está guardado e atualiza em segundo plano.
  evento.respondWith(
    caches.open(VERSAO).then((cache) =>
      cache.match(req).then((guardado) => {
        const rede = fetch(req).then((resp) => {
          if (resp.ok) cache.put(req, resp.clone());
          return resp;
        }).catch(() => guardado);
        return guardado || rede;
      })
    )
  );
});
