document.documentElement.classList.add('js');

// Application installable (PWA) : enregistre le service worker (mise en cache
// des fichiers statiques + page de repli hors ligne). N'échoue jamais
// silencieusement une fonctionnalité du site si l'enregistrement échoue.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  });
}

// Bouton "Installer l'app" à côté du logo : reste TOUJOURS affiché, sans
// aucune condition (aucun test "déjà installée" qui pourrait le faire
// disparaître à tort selon le navigateur) — pas besoin d'attendre un
// événement du navigateur pour apparaître.
// - Android/Chrome/Edge : si le navigateur a proposé l'installation native
//   (beforeinstallprompt), on l'utilise directement au clic.
// - iPhone/iPad (Safari, pas d'installation programmable) et tout navigateur
//   sans ce support : on affiche de simples instructions.
(function () {
  var btn = document.getElementById('pwa-install-link');
  var tip = document.getElementById('pwa-install-tip');
  var tipClose = document.getElementById('pwa-tip-close');
  var tipIOS = document.getElementById('pwa-tip-ios');
  var tipAndroid = document.getElementById('pwa-tip-android');
  var toast = document.getElementById('pwa-install-toast');
  var toastText = document.getElementById('pwa-install-toast-text');

  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  var deferredPrompt = null;

  function showToast(text, autoHideMs) {
    if (!toast) return;
    if (toastText) toastText.textContent = text;
    toast.hidden = false;
    if (autoHideMs) window.setTimeout(function () { toast.hidden = true; }, autoHideMs);
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
  });

  if (btn) {
    btn.addEventListener('click', function () {
      if (deferredPrompt) {
        // Message affiché pendant que la fenêtre d'installation native est ouverte
        // et que l'application se télécharge/s'installe sur l'appareil.
        showToast('Téléchargement en cours. Veuillez patienter…');
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
          if (toast) toast.hidden = true;
          if (choice && choice.outcome === 'accepted') {
            showToast('Installation réussie ✓', 2500);
          }
        }).finally(function () { deferredPrompt = null; });
        return;
      }
      // Pas de prompt natif disponible (iOS, ou Chrome/Android qui ne l'a
      // pas encore proposé) : on affiche les instructions adaptées.
      if (tip) {
        if (tipIOS) tipIOS.hidden = !isIOS;
        if (tipAndroid) tipAndroid.hidden = isIOS;
        tip.hidden = false;
      }
    });
  }
  if (tipClose) tipClose.addEventListener('click', function () { tip.hidden = true; });
})();

document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.querySelector('.main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
    });
    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { nav.classList.remove('open'); });
    });
  }

  // Galerie de la page détail : fondu enchaîné au changement de photo
  var thumbs = document.querySelectorAll('.gallery-thumbs .thumb');
  var mainImg = document.querySelector('.gallery-main');
  if (thumbs.length && mainImg) {
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var url = thumb.getAttribute('data-full');
        mainImg.classList.add('is-swapping');
        window.setTimeout(function () {
          mainImg.style.backgroundImage = "url('" + url + "')";
          mainImg.classList.remove('is-swapping');
        }, 180);
        thumbs.forEach(function (t) { t.classList.remove('active'); });
        thumb.classList.add('active');
      });
    });
  }

  var contactForm = document.querySelector('form[data-contact-form]');
  if (contactForm) {
    contactForm.addEventListener('submit', function () {
      var btn = contactForm.querySelector('button[type=submit]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Envoi en cours...';
      }
    });
  }

  // Respecte la préférence "mouvement réduit" : pas d'animation de compteur dans ce cas
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Note : les apparitions au chargement (hero, grilles de catégories/annonces, bandeau
  // "à la une") sont désormais gérées entièrement en CSS (voir style.css), sans passer par
  // IntersectionObserver — ce mécanisme JS s'est révélé peu fiable sur certains navigateurs
  // mobiles, laissant le contenu invisible. Le CSS pur garantit que le contenu apparaît
  // toujours, quel que soit l'appareil.

  // Compteurs animés (bandeau de chiffres clés)
  var counters = document.querySelectorAll('[data-count-to]');
  if (counters.length) {
    var animateCounter = function (el) {
      if (el.dataset.counted === '1') return;
      el.dataset.counted = '1';
      var target = parseFloat(el.getAttribute('data-count-to'), 10) || 0;
      var suffix = el.getAttribute('data-suffix') || '';
      if (reduceMotion) {
        el.textContent = target + suffix;
        return;
      }
      var duration = 1100;
      var start = null;
      function step(timestamp) {
        if (!start) start = timestamp;
        var progress = Math.min((timestamp - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        var value = Math.round(eased * target);
        el.textContent = value + suffix;
        if (progress < 1) {
          window.requestAnimationFrame(step);
        } else {
          el.textContent = target + suffix;
        }
      }
      window.requestAnimationFrame(step);
    };

    if ('IntersectionObserver' in window) {
      var counterObserver = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            obs.unobserve(entry.target);
          }
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { counterObserver.observe(el); });
      // Filet de sécurité : si l'observateur ne se déclenche jamais (même souci potentiel
      // que pour les grilles d'annonces), on force l'affichage des vrais chiffres après un
      // court délai plutôt que de laisser "0+" affiché indéfiniment.
      window.setTimeout(function () {
        counters.forEach(animateCounter);
      }, 1500);
    } else {
      counters.forEach(animateCounter);
    }
  }

  // Indicateur de chargement sur les formulaires de recherche/filtres (utile sur connexion
  // lente, pour confirmer immédiatement que le clic a bien été pris en compte).
  document.querySelectorAll('.search-bar, .filters-bar').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type=submit], button:not([type])');
      if (btn) btn.classList.add('is-loading');
    });
  });

  // Champs téléphone : n'accepte que des chiffres, limité à 10 (l'indicatif +225 est ajouté automatiquement)
  document.querySelectorAll('[data-phone-digits]').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.replace(/\D/g, '').slice(0, 10);
    });
  });
});
