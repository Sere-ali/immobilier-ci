// Application installable (PWA), aussi côté admin.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  });
}

document.addEventListener('DOMContentLoaded', function () {
  // Menu de la sidebar sur mobile : tiroir plein écran (ouverture/fermeture
  // au clic sur le bouton hamburger, sur l'arrière-plan, ou sur un lien du menu).
  var sidebarToggle = document.querySelector('.sidebar-toggle');
  var sidebar = document.querySelector('.sidebar');
  var sidebarOverlay = document.querySelector('.sidebar-overlay');
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarOverlay) sidebarOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }
  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (sidebarOverlay) sidebarOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('nav a').forEach(function (link) {
      link.addEventListener('click', closeSidebar);
    });
  }

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      var msg = el.getAttribute('data-confirm') || 'Confirmer cette action ?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  var fileInput = document.querySelector('#images');
  var preview = document.querySelector('#images-preview');
  if (fileInput && preview) {
    fileInput.addEventListener('change', function () {
      preview.innerHTML = '';
      Array.from(fileInput.files).forEach(function (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
          var div = document.createElement('div');
          div.style.cssText = 'width:70px;height:56px;border-radius:6px;background:center/cover no-repeat;background-image:url(' + e.target.result + ');display:inline-block;margin:4px';
          preview.appendChild(div);
        };
        reader.readAsDataURL(file);
      });
    });
  }

  document.querySelectorAll('.alert').forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity .4s';
      alert.style.opacity = '0';
      setTimeout(function () { alert.remove(); }, 400);
    }, 4500);
  });

  // Champs téléphone : n'accepte que des chiffres, limité à 10 (l'indicatif +225 est ajouté automatiquement)
  document.querySelectorAll('[data-phone-digits]').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.replace(/\D/g, '').slice(0, 10);
    });
  });
});
