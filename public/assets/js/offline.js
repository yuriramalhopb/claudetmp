// Página offline: recarrega sozinha quando a conexão volta.
(() => {
  const retry = () => window.location.reload();
  window.addEventListener('online', retry);
  document.querySelector('[data-retry]')?.addEventListener('click', (ev) => { ev.preventDefault(); retry(); });
})();
