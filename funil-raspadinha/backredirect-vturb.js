/*
  BACK REDIRECT PÓS-PITCH PARA VSL NA VTURB (com suporte a teste A/B)

  O "voltar" só leva para a raspadinha depois que a pessoa chegou no pitch
  da VSL que ela está assistindo. Antes disso, o voltar funciona normal.

  Cole na página de vendas, antes do </body> (ou num widget HTML no fim da página):

  <script>
  window.BR_CONFIG = {
    destino: "https://SEU-DOMINIO.com/raspadinha/",
    vsls: [
      { pitch: "45:00", duracao: "" },   // VSL A
      { pitch: "54:55", duracao: "" },   // VSL B
    ],
  };
  </script>
  <script src="https://SEU-DOMINIO.com/raspadinha/backredirect-vturb.js"></script>

  Como ele sabe qual VSL está tocando: pela duração total do vídeo.
  - Se a VSL A for mais curta que o pitch da B (ex.: termina antes de 54:55), não precisa preencher "duracao".
  - Se as duas forem mais longas que 54:55, preencha "duracao" de cada uma (ex.: "58:30").
  - Se não der pra saber qual é, ele usa o pitch mais tarde (nunca redireciona antes do preço).

  Testar sem assistir 45 minutos:
    sua-pagina.com/?debugbr=1&pitchteste=10
  → mostra uma caixinha com o que o script está vendo e considera o pitch em 10 segundos.
*/
(function () {
  "use strict";

  var CFG = window.BR_CONFIG || {};
  var DESTINO = CFG.destino || "https://SEU-DOMINIO.com/raspadinha/";
  var params = new URLSearchParams(location.search);
  var DEBUG = params.get("debugbr") === "1";
  var PITCH_TESTE = params.has("pitchteste") ? Number(params.get("pitchteste")) : null;
  var CHAVE = "br_pitch_visto_v1";

  function seg(t) {
    if (t === undefined || t === null || t === "") return 0;
    var s = 0;
    String(t).split(":").forEach(function (n) { s = s * 60 + (Number(n) || 0); });
    return s;
  }
  function mmss(s) {
    if (!isFinite(s)) return "--:--";
    s = Math.floor(s);
    var m = Math.floor(s / 60), r = s % 60;
    return (m < 10 ? "0" : "") + m + ":" + (r < 10 ? "0" : "") + r;
  }

  var VSLS = (CFG.vsls && CFG.vsls.length ? CFG.vsls : [{ pitch: "45:00" }]).map(function (v) {
    return { pitch: seg(v.pitch), duracao: seg(v.duracao) };
  });
  var PITCH_MAIS_TARDE = Math.max.apply(null, VSLS.map(function (v) { return v.pitch; }));

  var storage = {
    visto: function () { try { return localStorage.getItem(CHAVE) === "1"; } catch (e) { return false; } },
    marcar: function () { if (PITCH_TESTE !== null) return; try { localStorage.setItem(CHAVE, "1"); } catch (e) {} },
  };
  if (params.get("resetbr") === "1") { try { localStorage.removeItem(CHAVE); } catch (e) {} }

  // ---------- Leva as UTMs junto ----------
  function destinoComParams() {
    try {
      var url = new URL(DESTINO, location.href);
      params.forEach(function (v, k) {
        if (k === "debugbr" || k === "pitchteste" || k === "resetbr") return;
        if (!url.searchParams.has(k)) url.searchParams.set(k, v);
      });
      return url.toString();
    } catch (e) { return DESTINO; }
  }

  // ---------- Arma o redirect ----------
  var armado = false;
  function armar() {
    if (armado) return;
    armado = true;
    storage.marcar();
    history.pushState({ br: 1 }, "", location.href);
    window.addEventListener("popstate", function () { location.replace(destinoComParams()); });
  }

  // ---------- Acha o vídeo da VTurb ----------
  function videos() {
    var lista = [];
    try {
      if (window.smartplayer && smartplayer.instances) {
        smartplayer.instances.forEach(function (i) { if (i && i.video) lista.push(i.video); });
      }
    } catch (e) {}
    Array.prototype.forEach.call(document.querySelectorAll("video"), function (v) { lista.push(v); });
    Array.prototype.forEach.call(document.querySelectorAll("vturb-smartplayer"), function (h) {
      try {
        if (h.shadowRoot) Array.prototype.forEach.call(h.shadowRoot.querySelectorAll("video"), function (v) { lista.push(v); });
      } catch (e) {}
    });
    return lista;
  }
  function videoPrincipal() {
    var vs = videos(), melhor = null;
    vs.forEach(function (v) {
      var d = Number(v.duration) || 0;
      if (!melhor || d > (Number(melhor.duration) || 0)) melhor = v;
    });
    return melhor;
  }

  // ---------- Descobre o pitch da VSL que está tocando ----------
  function pitchDoVideo(duracao) {
    if (PITCH_TESTE !== null) return PITCH_TESTE;
    if (duracao && isFinite(duracao)) {
      var comDuracao = VSLS.filter(function (v) { return v.duracao > 0; });
      if (comDuracao.length) {
        var maisPerto = comDuracao.reduce(function (a, b) {
          return Math.abs(b.duracao - duracao) < Math.abs(a.duracao - duracao) ? b : a;
        });
        if (Math.abs(maisPerto.duracao - duracao) <= 90) return maisPerto.pitch;
      }
      // Sem duração cadastrada: o pitch tem que caber dentro do vídeo
      var cabem = VSLS.filter(function (v) { return v.pitch < duracao; }).map(function (v) { return v.pitch; });
      if (cabem.length) return Math.max.apply(null, cabem);
    }
    return PITCH_MAIS_TARDE;
  }

  // ---------- Caixinha de debug ----------
  var caixa = null;
  function mostrarDebug(info) {
    if (!DEBUG) return;
    if (!caixa) {
      caixa = document.createElement("div");
      caixa.style.cssText = "position:fixed;left:8px;bottom:8px;z-index:2147483647;background:rgba(0,0,0,.85);color:#0f0;" +
        "font:12px/1.5 monospace;padding:8px 10px;border-radius:8px;pointer-events:none;white-space:pre";
      document.body.appendChild(caixa);
    }
    caixa.textContent =
      "BACK REDIRECT (debug)\n" +
      "vídeo achado: " + (info.video ? "sim" : "não") + "\n" +
      "duração:      " + mmss(info.duracao) + "\n" +
      "tempo atual:  " + mmss(info.tempo) + "\n" +
      "pitch usado:  " + mmss(info.pitch) + (PITCH_TESTE !== null ? " (teste)" : "") + "\n" +
      "redirect:     " + (armado ? "LIGADO ✔" : "esperando o pitch");
  }

  // ---------- Loop: confere o vídeo a cada segundo ----------
  function checar() {
    var v = videoPrincipal();
    var duracao = v ? Number(v.duration) : NaN;
    var tempo = v ? Number(v.currentTime) : NaN;
    var pitch = pitchDoVideo(duracao);
    if (v && tempo >= pitch) armar();
    mostrarDebug({ video: v, duracao: duracao, tempo: tempo, pitch: pitch });
    if (armado && !DEBUG) clearInterval(intervalo);
  }
  var intervalo = setInterval(checar, 1000);

  // Quem já viu o pitch numa visita anterior: arma no primeiro toque na página
  if (storage.visto() && PITCH_TESTE === null) {
    ["pointerdown", "touchend", "click", "keydown"].forEach(function (ev) {
      window.addEventListener(ev, armar, { once: true, passive: true, capture: true });
    });
  }
})();
