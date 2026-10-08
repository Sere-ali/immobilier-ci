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

  // Vidéo de l'annonce : affiche le nom/poids du fichier choisi + un aperçu, et
  // refuse tout de suite (avant l'envoi) une vidéo de plus de 50 Mo.
  var videoInput = document.querySelector('#video');
  var videoName = document.querySelector('#video-name');
  var videoPreview = document.querySelector('#video-preview');
  if (videoInput) {
    var videoPreviewUrl = null;
    videoInput.addEventListener('change', function () {
      if (videoPreviewUrl) { URL.revokeObjectURL(videoPreviewUrl); videoPreviewUrl = null; }
      var file = videoInput.files && videoInput.files[0];
      if (!file) {
        if (videoName) videoName.textContent = 'Aucune vidéo sélectionnée';
        if (videoPreview) { videoPreview.style.display = 'none'; videoPreview.removeAttribute('src'); }
        return;
      }
      var mb = file.size / (1024 * 1024);
      if (mb > 50) {
        alert('Cette vidéo fait ' + mb.toFixed(1).replace('.', ',') + ' Mo : la limite est de 50 Mo. Choisissez une vidéo plus légère.');
        videoInput.value = '';
        if (videoName) videoName.textContent = 'Aucune vidéo sélectionnée';
        if (videoPreview) { videoPreview.style.display = 'none'; videoPreview.removeAttribute('src'); }
        return;
      }
      if (videoName) videoName.textContent = file.name + ' (' + mb.toFixed(1).replace('.', ',') + ' Mo)';
      if (videoPreview) {
        videoPreviewUrl = URL.createObjectURL(file);
        videoPreview.src = videoPreviewUrl;
        videoPreview.style.display = 'block';
      }
    });
  }

  // Suppression multiple d'annonces : cases à cocher + "tout sélectionner".
  var bulkForm = document.querySelector('#bulk-form');
  if (bulkForm) {
    var rowChecks = Array.prototype.slice.call(bulkForm.querySelectorAll('.row-check'));
    var selectAll = bulkForm.querySelector('#select-all');
    var bulkBtn = bulkForm.querySelector('#bulk-delete-btn');
    var bulkCount = bulkForm.querySelector('#bulk-count');
    var updateBulk = function () {
      var n = rowChecks.filter(function (c) { return c.checked; }).length;
      rowChecks.forEach(function (c) {
        var tr = c.closest('tr');
        if (tr) tr.classList.toggle('row-selected', c.checked);
      });
      if (bulkBtn) bulkBtn.disabled = n === 0;
      if (bulkCount) bulkCount.textContent = n === 0 ? 'Aucune annonce sélectionnée' : (n + (n > 1 ? ' annonces sélectionnées' : ' annonce sélectionnée'));
      if (selectAll) {
        selectAll.checked = n > 0 && n === rowChecks.length;
        selectAll.indeterminate = n > 0 && n < rowChecks.length;
      }
    };
    rowChecks.forEach(function (c) { c.addEventListener('change', updateBulk); });
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        rowChecks.forEach(function (c) { c.checked = selectAll.checked; });
        updateBulk();
      });
    }
    bulkForm.addEventListener('submit', function (e) {
      var n = rowChecks.filter(function (c) { return c.checked; }).length;
      if (n === 0) { e.preventDefault(); return; }
      var msg = 'Supprimer définitivement ' + n + (n > 1 ? ' annonces' : ' annonce') + ' ? Cette action est irréversible.';
      if (!confirm(msg)) e.preventDefault();
    });
    updateBulk();
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
