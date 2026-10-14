// Mises à jour en temps réel : toutes les ~3 s on demande une empreinte des données
// (live_ping). Si elle change, la page recharge en arrière-plan ses zones
// [data-live] et les remplace sans recharger la page ni perdre la saisie en cours.
(function () {
  var regions = document.querySelectorAll('[data-live]');
  if (!regions.length) return;
  var scope = document.querySelector('.admin-shell') ? 'admin' : 'public';
  var INTERVAL = 3000;
  var last = null, busy = false, timer = null;

  function typing(region) {
    var a = document.activeElement;
    if (!a || !region.contains(a)) return false;
    var tag = a.tagName;
    if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
    if (tag === 'INPUT') {
      var t = (a.type || 'text').toLowerCase();
      return ['checkbox', 'radio', 'button', 'submit'].indexOf(t) === -1;
    }
    return false;
  }

  function swap(region, fresh) {
    if (region.innerHTML === fresh.innerHTML) return false;
    if (typing(region)) return false;
    // Mémorise cases cochées et messages d'alerte avant le remplacement
    var checked = {};
    region.querySelectorAll('input[type=checkbox]:checked').forEach(function (c) { checked[c.name + '|' + c.value] = true; });
    var alerts = Array.prototype.slice.call(region.querySelectorAll('.alert'));
    var scrollY = window.scrollY;

    region.innerHTML = fresh.innerHTML;
    alerts.slice().reverse().forEach(function (a) { region.insertBefore(a, region.firstChild); });
    region.querySelectorAll('input[type=checkbox]').forEach(function (c) {
      if (checked[c.name + '|' + c.value]) c.checked = true;
    });
    // Compteurs animés de la page d'accueil : affiche directement la valeur finale
    region.querySelectorAll('[data-count-to]').forEach(function (el) {
      el.textContent = (el.getAttribute('data-count-to') || '0') + (el.getAttribute('data-suffix') || '');
    });
    region.classList.add('live-swap', 'live-flash');
    setTimeout(function () { region.classList.remove('live-flash'); }, 1200);
    window.scrollTo(0, scrollY);
    region.dispatchEvent(new CustomEvent('live:updated', { bubbles: true }));
    return true;
  }

  function refresh() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', location.href);
    xhr.setRequestHeader('X-Live-Refresh', '1');
    xhr.onload = function () {
      busy = false;
      if (xhr.status === 404) { location.reload(); return; }          // annonce supprimée
      if (xhr.status !== 200) return;
      var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
      document.querySelectorAll('[data-live]').forEach(function (region) {
        var key = region.getAttribute('data-live');
        var fresh = doc.querySelector('[data-live="' + key + '"]');
        if (fresh) swap(region, fresh);
      });
    };
    xhr.onerror = function () { busy = false; };
    xhr.send();
  }

  function ping() {
    if (busy || document.hidden) return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/live_ping?scope=' + scope + '&t=' + Date.now());
    xhr.onload = function () {
      if (xhr.status !== 200) return;
      var d; try { d = JSON.parse(xhr.responseText); } catch (e) { return; }
      if (last !== null && d.v !== last) { busy = true; last = d.v; refresh(); }
      else { last = d.v; }
    };
    xhr.send();
  }

  function loop() { ping(); timer = setTimeout(loop, INTERVAL); }
  loop();
  document.addEventListener('visibilitychange', function () { if (!document.hidden) ping(); });
  window.addEventListener('online', ping);
})();
