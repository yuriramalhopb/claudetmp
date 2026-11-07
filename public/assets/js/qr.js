// Desenha QR Codes a partir de data-qr="..." (gerado localmente; nada é enviado a terceiros).
(() => {
  'use strict';
  if (typeof qrcode !== 'function') return;
  document.querySelectorAll('[data-qr]').forEach((el) => {
    const qr = qrcode(0, 'M');
    qr.addData(el.dataset.qr, 'Byte');
    qr.make();
    const img = new Image();
    img.src = qr.createDataURL(Number(el.dataset.qrCell || 6), 4);
    img.alt = el.dataset.qrAlt || 'QR Code';
    img.width = img.height = Number(el.dataset.qrSize || 220);
    img.className = 'qr-img';
    el.replaceChildren(img);
  });
  // botões "copiar"
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = document.querySelector(btn.dataset.copy)?.value || btn.dataset.copyText || '';
      try { await navigator.clipboard.writeText(text); } catch (_) { window.prompt('Copie:', text); return; }
      const old = btn.textContent; btn.textContent = 'Copiado! ✔'; setTimeout(() => { btn.textContent = old; }, 2000);
    });
  });
})();
