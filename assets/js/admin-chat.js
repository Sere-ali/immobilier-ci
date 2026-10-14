// Réponse aux visiteurs depuis l'espace admin : envoi sans recharger, vocal, pièce jointe, actualisation auto.
(function () {
  var list = document.getElementById('cm-list');
  var form = document.getElementById('cm-form');
  if (!list || !form) return;
  var id = list.getAttribute('data-id');
  var lastId = parseInt(list.getAttribute('data-last') || '0', 10) || 0;
  var input = document.getElementById('cm-input'), fileInput = document.getElementById('cm-file');
  var fileName = document.getElementById('cm-file-name'), errBox = document.getElementById('cm-error');
  var sendBtn = document.getElementById('cm-send'), micBtn = document.getElementById('cm-mic');
  var recBox = document.getElementById('cm-rec'), recTime = document.getElementById('cm-rec-time');
  var csrf = form.querySelector('input[name=csrf_token]').value;
  var shown = {};
  list.querySelectorAll('[data-mid]').forEach(function (n) { shown[n.getAttribute('data-mid')] = true; });

  function scrollDown() { list.scrollTop = list.scrollHeight; }
  scrollDown();
  function showError(msg) {
    errBox.textContent = msg || ''; errBox.hidden = !msg;
    if (msg) setTimeout(function () { errBox.hidden = true; }, 6000);
  }
  function hhmm(s) {
    var d = new Date((s || '').replace(' ', 'T'));
    if (isNaN(d)) return '';
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + ' ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }
  function add(m) {
    if (shown[m.id]) return;
    shown[m.id] = true;
    var row = document.createElement('div');
    row.className = 'cm-row ' + (m.sender === 'agent' ? 'cm-me' : 'cm-them');
    row.setAttribute('data-mid', m.id);
    var b = document.createElement('div'); b.className = 'cm-bubble';
    if (m.file) {
      if (m.file.kind === 'image') {
        var a = document.createElement('a'); a.href = m.file.url; a.target = '_blank'; a.rel = 'noopener';
        var img = document.createElement('img'); img.src = m.file.url; img.alt = m.file.name || ''; a.appendChild(img); b.appendChild(a);
      } else if (m.file.kind === 'audio') {
        var au = document.createElement('audio'); au.controls = true; au.preload = 'metadata'; au.src = m.file.url; b.appendChild(au);
      } else {
        var f = document.createElement('a'); f.className = 'cm-file'; f.href = m.file.url; f.target = '_blank'; f.rel = 'noopener';
        f.textContent = '📎 ' + (m.file.name || 'Fichier'); b.appendChild(f);
      }
    }
    if (m.body) { var t = document.createElement('div'); t.className = 'cm-text'; t.textContent = m.body; b.appendChild(t); }
    var tm = document.createElement('span'); tm.className = 'cm-time'; tm.textContent = hhmm(m.at); b.appendChild(tm);
    row.appendChild(b); list.appendChild(row);
    if (m.id > lastId) lastId = m.id;
  }

  function send(fd, done) {
    fd.append('ajax', '1'); fd.append('csrf_token', csrf); fd.append('action', 'reply'); fd.append('id', id);
    sendBtn.disabled = true;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'chat?id=' + id);
    xhr.onload = function () {
      sendBtn.disabled = false;
      var data = {}; try { data = JSON.parse(xhr.responseText); } catch (e) {}
      if (xhr.status === 200 && data.message) { add(data.message); scrollDown(); done(true); }
      else { showError(data.error || 'Envoi impossible.'); done(false); }
    };
    xhr.onerror = function () { sendBtn.disabled = false; showError('Envoi impossible. Vérifiez la connexion.'); done(false); };
    xhr.send(fd);
  }

  document.getElementById('cm-attach').addEventListener('click', function () { fileInput.click(); });
  fileInput.addEventListener('change', function () {
    var f = fileInput.files && fileInput.files[0];
    if (!f) { fileName.hidden = true; return; }
    if (f.size > 10 * 1024 * 1024) { showError('Fichier trop lourd (10 Mo maximum).'); fileInput.value = ''; fileName.hidden = true; return; }
    fileName.textContent = '📎 ' + f.name + ' (cliquer pour retirer)'; fileName.hidden = false;
  });
  fileName.addEventListener('click', function () { fileInput.value = ''; fileName.hidden = true; });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim(), f = fileInput.files && fileInput.files[0];
    if (!text && !f) return;
    var fd = new FormData(); fd.append('body', text);
    if (f) fd.append('file', f, f.name);
    send(fd, function (ok) { if (ok) { input.value = ''; fileInput.value = ''; fileName.hidden = true; input.style.height = 'auto'; } });
  });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : sendBtn.click(); }
  });
  input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; });

  // Vocal
  var recorder = null, chunks = [], stream = null, cancelled = false, timer = null, start = 0;
  if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder)) micBtn.hidden = true;
  function stopAll() { clearInterval(timer); recBox.hidden = true; form.hidden = false; if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } }
  micBtn.addEventListener('click', function () {
    if (recorder) return;
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
      stream = s; chunks = []; cancelled = false;
      var mime = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'].filter(function (m) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(m); })[0] || '';
      try { recorder = mime ? new MediaRecorder(s, { mimeType: mime }) : new MediaRecorder(s); } catch (err) { stopAll(); showError('Vocal non pris en charge.'); return; }
      recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size) chunks.push(ev.data); };
      recorder.onstop = function () {
        var type = (recorder && recorder.mimeType) || mime || 'audio/webm';
        recorder = null; stopAll();
        if (cancelled || !chunks.length) return;
        var ext = type.indexOf('mp4') > -1 ? 'm4a' : (type.indexOf('ogg') > -1 ? 'ogg' : 'webm');
        var fd = new FormData(); fd.append('body', ''); fd.append('voice', new Blob(chunks, { type: type }), 'message-vocal.' + ext);
        send(fd, function () {});
      };
      recorder.start(); start = Date.now(); recTime.textContent = '0:00'; recBox.hidden = false; form.hidden = true;
      timer = setInterval(function () {
        var sec = Math.floor((Date.now() - start) / 1000);
        recTime.textContent = Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
        if (sec >= 120 && recorder) recorder.stop();
      }, 250);
    }).catch(function () { showError('Micro inaccessible : autorisez-le dans le navigateur.'); });
  });
  document.getElementById('cm-rec-send').addEventListener('click', function () { if (recorder) recorder.stop(); });
  document.getElementById('cm-rec-cancel').addEventListener('click', function () { if (recorder) { cancelled = true; recorder.stop(); } else stopAll(); });

  // Actualisation automatique
  setInterval(function () {
    if (document.hidden) return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'chat?poll=1&id=' + id + '&after=' + lastId);
    xhr.onload = function () {
      if (xhr.status !== 200) return;
      var d; try { d = JSON.parse(xhr.responseText); } catch (e) { return; }
      var before = lastId;
      (d.messages || []).forEach(add);
      if (lastId !== before) scrollDown();
    };
    xhr.send();
  }, 4000);
})();
