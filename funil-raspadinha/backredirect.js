/*
  BACK REDIRECT — cole na página de vendas, antes do </body>:

  <script src="https://SEU-DOMINIO.com/raspadinha/backredirect.js"
          data-destino="https://SEU-DOMINIO.com/raspadinha/"></script>

  Ou copie este arquivo inteiro dentro de <script>...</script> e troque o DESTINO abaixo.

  Quando a pessoa aperta "voltar", ela vai para a página da roleta/raspadinha,
  levando junto as UTMs (para a Utmify continuar atribuindo a venda ao anúncio certo).
*/
(function () {
  var script = document.currentScript;
  var DESTINO = (script && script.getAttribute("data-destino")) || "https://SEU-DOMINIO.com/raspadinha/";

  var armado = false;

  function destinoComParams() {
    try {
      var url = new URL(DESTINO, location.href);
      new URLSearchParams(location.search).forEach(function (v, k) {
        if (!url.searchParams.has(k)) url.searchParams.set(k, v);
      });
      return url.toString();
    } catch (e) {
      return DESTINO;
    }
  }

  function armar() {
    if (armado) return;
    armado = true;
    history.pushState({ br: 1 }, "", location.href);
    window.addEventListener("popstate", function () {
      location.replace(destinoComParams());
    });
  }

  // O Chrome ignora o "voltar" falso se a pessoa ainda não interagiu com a página.
  // Por isso o redirect só é armado no primeiro toque, clique ou tecla
  // (dar play na VSL já conta).
  ["pointerdown", "touchend", "click", "keydown"].forEach(function (ev) {
    window.addEventListener(ev, armar, { once: true, passive: true, capture: true });
  });
})();
