// Vela da Fé — JavaScript do site e do painel (sem dependências). Sem scripts inline: compatível com o CSP.
(() => {
  'use strict';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const brl = (cents) => cents > 0 ? 'R$ ' + (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : 'Grátis';
  const hoursText = (h) => (h >= 24 && h % 24 === 0) ? (h / 24) + (h / 24 === 1 ? ' dia' : ' dias') : h + (h === 1 ? ' hora' : ' horas');

  // ---- menu do site e do painel ----
  $('[data-nav-toggle]')?.addEventListener('click', (e) => {
    const open = $('#menu').classList.toggle('is-open');
    e.currentTarget.setAttribute('aria-expanded', String(open));
  });
  $('[data-admin-menu]')?.addEventListener('click', () => $('.admin-side').classList.toggle('is-open'));

  // ---- mensagens ----
  $$('[data-dismiss]').forEach((b) => b.addEventListener('click', () => b.closest('.flash')?.remove()));

  // ---- confirmação antes de enviar (data-confirm) ----
  $$('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => {
    if (!window.confirm(f.dataset.confirm)) e.preventDefault();
  }));

  // ---- filtros que enviam sozinhos ----
  $$('form[data-autosubmit] select').forEach((s) => s.addEventListener('change', () => s.form.submit()));

  // ---- imprimir / compartilhar / copiar ----
  $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  $$('[data-share]').forEach((b) => b.addEventListener('click', async () => {
    const data = { title: b.dataset.shareTitle || document.title, url: b.dataset.shareUrl || location.href };
    try {
      if (navigator.share) { await navigator.share(data); return; }
      await navigator.clipboard.writeText(data.url);
      const old = b.textContent; b.textContent = 'Link copiado! ✔'; setTimeout(() => { b.textContent = old; }, 2000);
    } catch (_) { /* cancelado pela pessoa */ }
  }));
  $$('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
    const el = $(btn.dataset.copy);
    const text = el ? (el.value ?? el.textContent) : '';
    try { await navigator.clipboard.writeText(text); } catch (_) { el?.select?.(); document.execCommand?.('copy'); }
    const old = btn.textContent; btn.textContent = 'Copiado! ✔'; setTimeout(() => { btn.textContent = old; }, 2000);
  }));

  // ---- QR Code do Pix (gerado aqui; nada é enviado a terceiros) ----
  const drawQr = () => {
    if (typeof qrcode !== 'function') return;
    $$('[data-qr]').forEach((el) => {
      const qr = qrcode(0, 'M');
      qr.addData(el.dataset.qr, 'Byte');
      qr.make();
      const img = new Image();
      img.src = qr.createDataURL(Number(el.dataset.qrCell || 5), 4);
      img.alt = el.dataset.qrAlt || 'QR Code';
      img.width = img.height = Number(el.dataset.qrSize || 220);
      img.className = 'qr-img';
      el.replaceChildren(img);
    });
  };
  if (document.readyState === 'complete') drawQr(); else window.addEventListener('load', drawQr);

  // ---- contador de caracteres ----
  $$('textarea[data-counter]').forEach((ta) => {
    const out = document.createElement('div');
    out.className = 'char-count';
    ta.after(out);
    const update = () => { out.textContent = ta.value.length + ' / ' + ta.maxLength; };
    ta.addEventListener('input', update); update();
  });

  // ---- formulário de vela: pré-visualização, total e aviso de Pix ----
  const form = $('[data-candle-form]');
  if (form) {
    const candle = $('[data-preview-candle] .candle');
    const totalEl = $('[data-total]', form);
    const hoursEl = $('[data-summary-hours]', form);
    const hint = $('[data-pay-hint]', form);
    const btn = $('[data-submit-label]', form);
    const textEl = $('[data-preview-text]', form);
    const nameEl = $('[data-preview-name]', form);
    const update = () => {
      const type = $('input[name="type_id"]:checked', form);
      const plan = $('input[name="plan_id"]:checked', form);
      if (type && candle) {
        candle.className = candle.className.replace(/\bct-\d+\b/g, '').trim() + ' ' + type.dataset.ct;
      }
      const total = Number(type?.dataset.price || 0) + Number(plan?.dataset.price || 0);
      if (totalEl) totalEl.textContent = brl(total);
      if (hoursEl && plan) hoursEl.textContent = hoursText(Number(plan.dataset.hours));
      if (hint) hint.hidden = total <= 0;
      if (btn) btn.textContent = total > 0 ? '🕯️ Continuar para o Pix' : '🕯️ Acender vela';
      if (textEl) textEl.textContent = nameEl?.value.trim() ? 'Vela de ' + nameEl.value.trim() : 'Sua vela';
    };
    form.addEventListener('change', update);
    nameEl?.addEventListener('input', update);
    form.addEventListener('submit', () => { btn?.setAttribute('disabled', 'disabled'); setTimeout(() => btn?.removeAttribute('disabled'), 8000); });
    update();
  }

  // ---- "Rezar por esta intenção" (sem recarregar a página) ----
  const prayForm = $('[data-pray-form]');
  if (prayForm) {
    prayForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = $('[data-pray-btn]', prayForm);
      btn.disabled = true;
      try {
        const res = await fetch(prayForm.action, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
        });
        if (!res.ok) throw new Error('http ' + res.status);
        const data = await res.json();
        const counter = $('[data-pray-count]');
        if (counter) counter.textContent = data.count;
        btn.textContent = data.new ? '🙏 Obrigado por rezar!' : '🙏 Você já rezou hoje';
      } catch (_) {
        btn.disabled = false;
        prayForm.submit(); // sem JavaScript moderno: envia o formulário normal
      }
    });
  }

  // ---- contagem regressiva da vela (atualiza a cada 30 s) ----
  const human = (ms) => {
    if (ms <= 0) return 'apagada';
    const m = Math.floor(ms / 60000), d = Math.floor(m / 1440), h = Math.floor((m % 1440) / 60), mi = m % 60;
    if (d > 0) return d + (d === 1 ? ' dia' : ' dias') + (h ? ' e ' + h + ' h' : '');
    if (h > 0) return h + ' h' + (mi ? ' ' + mi + ' min' : '');
    return Math.max(1, mi) + ' min';
  };
  const tick = () => $$('[data-countdown]').forEach((el) => { el.textContent = human(new Date(el.dataset.countdown) - Date.now()); });
  if ($('[data-countdown]')) setInterval(tick, 30000);

  // ---- contadores ao vivo da página inicial ----
  const live = $$('[data-live-counter]');
  if (live.length) {
    const refresh = async () => {
      try {
        const res = await fetch('/api/resumo', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        live.forEach((el) => { if (data[el.dataset.liveCounter] !== undefined) el.textContent = data[el.dataset.liveCounter]; });
      } catch (_) { /* sem rede: mantém os números */ }
    };
    setInterval(() => { if (!document.hidden) refresh(); }, 30000);
  }
})();
