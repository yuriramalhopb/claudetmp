// Ducks Offroad — JS sem dependências. Nenhum handler inline (compatível com a CSP).
(() => {
  'use strict';

  // Menu mobile
  const toggle = document.querySelector('[data-nav-toggle]');
  const menu = document.getElementById('menu');
  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      const open = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  // Fechar avisos
  document.querySelectorAll('[data-dismiss]').forEach((btn) => {
    btn.addEventListener('click', () => btn.closest('.flash')?.remove());
  });

  // Confirmação em ações destrutivas
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (ev) => {
      if (!window.confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });

  // Evita duplo envio de formulários
  document.querySelectorAll('form[method="post"]').forEach((form) => {
    form.addEventListener('submit', (ev) => {
      if (ev.defaultPrevented) return;
      if (form.dataset.sending === '1') { ev.preventDefault(); return; }
      form.dataset.sending = '1';
      setTimeout(() => { form.dataset.sending = ''; }, 8000);
    });
  });

  // Validação de tamanho de arquivo no navegador (o servidor valida de novo)
  const MAX = 8 * 1024 * 1024;
  document.querySelectorAll('input[type=file]').forEach((input) => {
    input.addEventListener('change', () => {
      const big = Array.from(input.files || []).filter((f) => f.size > MAX);
      if (big.length) {
        alert('Arquivo(s) maior(es) que 8 MB: ' + big.map((f) => f.name).join(', '));
        input.value = '';
      }
    });
  });

  // Compartilhar (Web Share API com fallback para copiar link)
  document.querySelectorAll('[data-share]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const url = btn.dataset.url || window.location.href;
      const title = btn.dataset.title || document.title;
      if (navigator.share) {
        try { await navigator.share({ title, url }); } catch (_) { /* cancelado */ }
        return;
      }
      try {
        await navigator.clipboard.writeText(url);
        const old = btn.textContent;
        btn.textContent = 'Link copiado!';
        setTimeout(() => { btn.textContent = old; }, 2000);
      } catch (_) {
        window.prompt('Copie o link:', url);
      }
    });
  });

  // Lightbox da galeria
  const lb = document.querySelector('[data-lightbox]');
  if (lb) {
    const img = lb.querySelector('[data-lb-img]');
    const cap = lb.querySelector('[data-lb-caption]');
    let items = [];
    let index = 0;

    const show = (i) => {
      index = (i + items.length) % items.length;
      const a = items[index];
      img.src = a.getAttribute('href');
      img.alt = a.querySelector('img')?.alt || '';
      cap.textContent = a.dataset.caption || '';
      const link = lb.querySelector('[data-lb-link]');
      if (link) {
        const page = a.dataset.page || '';
        link.hidden = !page.startsWith('/fotos/');
        if (!link.hidden) link.href = page;
      }
    };
    const close = () => { lb.hidden = true; img.src = ''; document.body.style.overflow = ''; };

    document.querySelectorAll('[data-gallery]').forEach((gallery) => {
      const links = Array.from(gallery.querySelectorAll('[data-lb-item]'));
      links.forEach((a, i) => {
        a.addEventListener('click', (ev) => {
          ev.preventDefault();
          items = links;
          show(i);
          lb.hidden = false;
          document.body.style.overflow = 'hidden';
        });
      });
    });

    lb.querySelector('[data-lb-close]').addEventListener('click', close);
    lb.querySelector('[data-lb-prev]').addEventListener('click', () => show(index - 1));
    lb.querySelector('[data-lb-next]').addEventListener('click', () => show(index + 1));
    lb.addEventListener('click', (ev) => { if (ev.target === lb) close(); });
    document.addEventListener('keydown', (ev) => {
      if (lb.hidden) return;
      if (ev.key === 'Escape') close();
      if (ev.key === 'ArrowLeft') show(index - 1);
      if (ev.key === 'ArrowRight') show(index + 1);
    });

    // gesto de arrastar no celular
    let startX = null;
    lb.addEventListener('touchstart', (ev) => { startX = ev.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', (ev) => {
      if (startX === null) return;
      const dx = ev.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
      startX = null;
    });
  }
})();

// ---------- App instalável (PWA) ----------
(() => {
  'use strict';

  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const store = {
    get: (k) => { try { return localStorage.getItem(k); } catch (_) { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch (_) { /* modo privado */ } },
  };

  // Registra o service worker (exige HTTPS ou localhost)
  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => { /* sem suporte */ });
    });
  }

  if (isStandalone) {
    document.documentElement.classList.add('is-app');
    document.querySelectorAll('[data-installed-msg]').forEach((el) => { el.hidden = false; });
  }

  // Android / Chrome / Edge: botão "Instalar app"
  let deferredPrompt = null;
  const installButtons = document.querySelectorAll('[data-install]');
  window.addEventListener('beforeinstallprompt', (ev) => {
    ev.preventDefault();
    deferredPrompt = ev;
    installButtons.forEach((b) => { b.hidden = false; });
  });
  installButtons.forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!deferredPrompt) { window.location.href = '/instalar'; return; }
      deferredPrompt.prompt();
      await deferredPrompt.userChoice.catch(() => null);
      deferredPrompt = null;
      installButtons.forEach((b) => { b.hidden = true; });
    });
  });
  window.addEventListener('appinstalled', () => {
    installButtons.forEach((b) => { b.hidden = true; });
    store.set('ducks-installed', '1');
  });

  // iPhone/iPad no Safari: não existe botão automático, mostramos as instruções (uma vez por semana no máximo)
  const ua = window.navigator.userAgent;
  const isIos = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const isSafari = /safari/i.test(ua) && !/crios|fxios|edgios/i.test(ua);
  const banner = document.querySelector('[data-ios-install]');
  const lastClosed = Number(store.get('ducks-ios-banner') || 0);
  if (banner && isIos && isSafari && !isStandalone && Date.now() - lastClosed > 7 * 86400000) {
    setTimeout(() => { banner.hidden = false; }, 2500);
    banner.querySelector('[data-ios-close]')?.addEventListener('click', () => {
      banner.hidden = true;
      store.set('ducks-ios-banner', String(Date.now()));
    });
  }

  // Ao sair da conta, limpa as imagens guardadas pelo app neste aparelho
  document.querySelectorAll('form[action="/sair"]').forEach((form) => {
    form.addEventListener('submit', () => {
      navigator.serviceWorker?.controller?.postMessage('clear-image-cache');
    });
  });
})();

// ---------- Notificações push ----------
(() => {
  'use strict';
  const btn = document.querySelector('[data-push-toggle]');
  const vapid = document.querySelector('meta[name="vapid-key"]')?.content;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!btn || !vapid || !csrf || !('serviceWorker' in navigator) || !('PushManager' in window) || !window.isSecureContext) return;

  const toBytes = (b64) => {
    const pad = '='.repeat((4 - (b64.length % 4)) % 4);
    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
  };
  const post = (url, data) => fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ _csrf: csrf, ...data }),
  });
  const render = (on) => {
    btn.hidden = false;
    btn.textContent = on ? '🔔 Avisos ativos' : '🔕 Ativar avisos';
    btn.setAttribute('aria-pressed', String(on));
  };

  navigator.serviceWorker.ready.then(async (reg) => {
    let sub = await reg.pushManager.getSubscription();
    render(!!sub && Notification.permission === 'granted');
    if (sub) { // mantém a inscrição sincronizada com o servidor
      const j = sub.toJSON();
      post('/push/inscrever', { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth }).catch(() => {});
    }
    btn.addEventListener('click', async () => {
      try {
        sub = await reg.pushManager.getSubscription();
        if (sub) {
          await post('/push/cancelar', { endpoint: sub.endpoint });
          await sub.unsubscribe();
          render(false);
          return;
        }
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
          alert('As notificações foram bloqueadas. Libere nas configurações do navegador/app.');
          return;
        }
        sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toBytes(vapid) });
        const j = sub.toJSON();
        const res = await post('/push/inscrever', { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth });
        if (!res.ok) throw new Error('servidor');
        render(true);
      } catch (e) {
        alert('Não foi possível ativar as notificações neste aparelho.');
      }
    });
  });
})();

// Botão imprimir
document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
