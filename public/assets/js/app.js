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
