// Registra o service worker e cuida do botão "Instalar app".
(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('sw.js').catch(function () {});
    });
  }

  var botao = document.getElementById('instalar');
  var dicaIos = document.getElementById('dica-ios');
  var instalado = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (!botao || instalado) return;

  var pedido = null;

  // Android / Chrome / Edge: o navegador avisa quando dá para instalar.
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    pedido = e;
    botao.hidden = false;
  });

  window.addEventListener('appinstalled', function () {
    botao.hidden = true;
    if (dicaIos) dicaIos.hidden = true;
  });

  botao.addEventListener('click', function () {
    if (pedido) {
      pedido.prompt();
      pedido.userChoice.finally(function () {
        pedido = null;
        botao.hidden = true;
      });
    } else if (dicaIos) {
      dicaIos.hidden = !dicaIos.hidden;
    }
  });

  // iPhone/iPad: não existe botão automático, então mostramos como fazer.
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (ios && dicaIos) {
    botao.hidden = false;
  }
})();
