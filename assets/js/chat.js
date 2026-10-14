// Chat public : texte, message vocal (MediaRecorder) et pièce jointe.
// La conversation est retrouvée grâce à un jeton gardé dans le navigateur ;
// les nouvelles réponses de l'équipe arrivent par interrogation régulière.
(function () {
  var root = document.getElementById('chat-widget');
  if (!root) return;
  var API = root.getAttribute('data-api') || '/chat_api';
  var $ = function (id) { return document.getElementById(id); };
  var fab = $('chat-fab'), panel = $('chat-panel'), closeBtn = $('chat-close');
  var list = $('chat-messages'), form = $('chat-form'), input = $('chat-input');
  var fileInput = $('chat-file'), attachBtn = $('chat-attach'), micBtn = $('chat-mic');
  var attBox = $('chat-attachment'), attName = $('chat-attachment-name'), attRemove = $('chat-attachment-remove');
  var recBox = $('chat-recording'), recTime = $('chat-rec-time');
  var errBox = $('chat-error'), unreadBadge = $('chat-unread'), intro = $('chat-intro');
  var nameInput = $('chat-name'), phoneInput = $('chat-phone'), sendBtn = $('chat-send');

  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
  };
  var token = store.get('chat_token') || '';
  var lastId = parseInt(store.get('chat_last_id') || '0', 10) || 0;
  var seenId = parseInt(store.get('chat_seen_id') || '0', 10) || 0;
  var open = false, sending = false, pollTimer = null, pendingFile = null;
  var rendered = {};

  if (token) { intro.hidden = true; }
  nameInput.value = store.get('chat_name') || '';
  phoneInput.value = store.get('chat_phone') || '';

  function showError(msg) {
    errBox.textContent = msg || '';
    errBox.hidden = !msg;
    if (msg) setTimeout(function () { if (errBox.textContent === msg) errBox.hidden = true; }, 6000);
  }
  function scrollDown() { list.scrollTop = list.scrollHeight; }
  function hhmm(s) {
    var d = new Date((s || '').replace(' ', 'T'));
    if (isNaN(d)) return '';
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  function renderMessage(m) {
    if (rendered[m.id]) return;
    rendered[m.id] = true;
    var row = document.createElement('div');
    row.className = 'chat-msg ' + (m.sender === 'visitor' ? 'chat-msg-me' : 'chat-msg-agent');
    var bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    if (m.file) {
      if (m.file.kind === 'image') {
        var a = document.createElement('a');
        a.href = m.file.url; a.target = '_blank'; a.rel = 'noopener';
        var img = document.createElement('img');
        img.src = m.file.url; img.alt = m.file.name || 'Image'; img.loading = 'lazy';
        a.appendChild(img); bubble.appendChild(a);
      } else if (m.file.kind === 'audio') {
        var au = document.createElement('audio');
        au.controls = true; au.preload = 'metadata'; au.src = m.file.url;
        bubble.appendChild(au);
      } else {
        var f = document.createElement('a');
        f.className = 'chat-file'; f.href = m.file.url; f.target = '_blank'; f.rel = 'noopener';
        f.textContent = '📎 ' + (m.file.name || 'Fichier');
        bubble.appendChild(f);
      }
    }
    if (m.body) {
      var t = document.createElement('div');
      t.className = 'chat-text'; t.textContent = m.body;
      bubble.appendChild(t);
    }
    var time = document.createElement('span');
    time.className = 'chat-time'; time.textContent = hhmm(m.at);
    bubble.appendChild(time);
    row.appendChild(bubble);
    list.appendChild(row);
    if (m.id > lastId) { lastId = m.id; store.set('chat_last_id', String(lastId)); }
  }

  function updateUnread(agentIds) {
    var n = 0;
    agentIds.forEach(function (id) { if (id > seenId) n++; });
    if (open) { n = 0; }
    unreadBadge.textContent = n > 9 ? '9+' : String(n);
    unreadBadge.hidden = n === 0;
  }

  function poll() {
    if (!token) return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', API + '?action=poll&token=' + encodeURIComponent(token) + '&after=' + lastId);
    xhr.onload = function () {
      if (xhr.status !== 200) return;
      var data; try { data = JSON.parse(xhr.responseText); } catch (e) { return; }
      var agentIds = [], added = false;
      (data.messages || []).forEach(function (m) {
        if (!rendered[m.id]) added = true;
        renderMessage(m);
        if (m.sender === 'agent') agentIds.push(m.id);
      });
      if (added) {
        if (open) { scrollDown(); markSeen(); } else { updateUnread(agentIds); }
      }
    };
    xhr.send();
  }
  function markSeen() {
    seenId = lastId; store.set('chat_seen_id', String(seenId));
    unreadBadge.hidden = true;
  }
  function startPolling() {
    stopPolling();
    var tick = function () {
      if (!document.hidden) poll();
      pollTimer = setTimeout(tick, open ? 4000 : 20000);
    };
    tick();
  }
  function stopPolling() { if (pollTimer) clearTimeout(pollTimer); pollTimer = null; }

  function setOpen(v) {
    open = v;
    panel.hidden = !v;
    root.classList.toggle('is-open', v);
    fab.setAttribute('aria-expanded', v ? 'true' : 'false');
    if (v) {
      markSeen(); scrollDown();
      setTimeout(function () { input.focus(); }, 120);
      if (token) startPolling();
    } else if (token) { startPolling(); }
  }
  fab.addEventListener('click', function () { setOpen(!open); });
  closeBtn.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) setOpen(false); });

  // Rechargement : récupère l'historique de la conversation existante
  if (token) { lastId = 0; startPolling(); }

  // Zone de texte qui grandit avec le contenu
  input.addEventListener('input', function () {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 110) + 'px';
  });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : sendBtn.click(); }
  });

  // Pièce jointe
  attachBtn.addEventListener('click', function () { fileInput.click(); });
  fileInput.addEventListener('change', function () {
    var f = fileInput.files && fileInput.files[0];
    if (!f) return;
    if (f.size > 10 * 1024 * 1024) { showError('Fichier trop lourd (10 Mo maximum).'); fileInput.value = ''; return; }
    pendingFile = f; attName.textContent = '📎 ' + f.name; attBox.hidden = false;
  });
  function clearAttachment() { pendingFile = null; fileInput.value = ''; attBox.hidden = true; }
  attRemove.addEventListener('click', clearAttachment);

  function post(fd, done) {
    sending = true; sendBtn.disabled = true; root.classList.add('is-sending');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', API);
    xhr.onload = function () {
      sending = false; sendBtn.disabled = false; root.classList.remove('is-sending');
      var data = {}; try { data = JSON.parse(xhr.responseText); } catch (e) {}
      if (xhr.status === 200 && data.message) {
        if (!token && data.token) {
          token = data.token; store.set('chat_token', token); intro.hidden = true;
          startPolling();
        }
        renderMessage(data.message); scrollDown(); markSeen();
        done(true);
      } else {
        showError(data.error || 'Envoi impossible. Vérifiez votre connexion.');
        done(false);
      }
    };
    xhr.onerror = function () {
      sending = false; sendBtn.disabled = false; root.classList.remove('is-sending');
      showError('Envoi impossible. Vérifiez votre connexion.'); done(false);
    };
    xhr.send(fd);
  }
  function baseData() {
    var fd = new FormData();
    fd.append('action', 'send');
    if (token) { fd.append('token', token); }
    else {
      fd.append('name', nameInput.value.trim()); fd.append('phone', phoneInput.value.trim());
      store.set('chat_name', nameInput.value.trim()); store.set('chat_phone', phoneInput.value.trim());
    }
    return fd;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending) return;
    var text = input.value.trim();
    if (!text && !pendingFile) return;
    var fd = baseData();
    fd.append('body', text);
    if (pendingFile) fd.append('file', pendingFile, pendingFile.name);
    post(fd, function (ok) {
      if (ok) { input.value = ''; input.style.height = 'auto'; clearAttachment(); }
    });
  });

  // Message vocal
  var recorder = null, chunks = [], recStart = 0, recTimer = null, recMime = '', cancelled = false, stream = null;
  var canRecord = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  if (!canRecord) { micBtn.hidden = true; }

  function pickMime() {
    var opts = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
    for (var i = 0; i < opts.length; i++) {
      if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(opts[i])) return opts[i];
    }
    return '';
  }
  function fmt(s) { return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
  function stopStream() { if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } }
  function endRecordingUI() {
    clearInterval(recTimer); recBox.hidden = true; form.hidden = false; micBtn.classList.remove('is-recording');
  }

  micBtn.addEventListener('click', function () {
    if (recorder || sending) return;
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
      stream = s; chunks = []; cancelled = false; recMime = pickMime();
      try { recorder = recMime ? new MediaRecorder(s, { mimeType: recMime }) : new MediaRecorder(s); }
      catch (err) { stopStream(); showError('Enregistrement vocal non pris en charge sur cet appareil.'); return; }
      recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size) chunks.push(ev.data); };
      recorder.onstop = function () {
        var type = (recorder && recorder.mimeType) || recMime || 'audio/webm';
        recorder = null; stopStream(); endRecordingUI();
        if (cancelled || !chunks.length) return;
        var blob = new Blob(chunks, { type: type });
        var ext = type.indexOf('mp4') > -1 ? 'm4a' : (type.indexOf('ogg') > -1 ? 'ogg' : 'webm');
        var fd = baseData();
        fd.append('body', '');
        fd.append('voice', blob, 'message-vocal.' + ext);
        post(fd, function () {});
      };
      recorder.start();
      recStart = Date.now(); recTime.textContent = '0:00';
      recBox.hidden = false; form.hidden = true; micBtn.classList.add('is-recording');
      recTimer = setInterval(function () {
        var sec = Math.floor((Date.now() - recStart) / 1000);
        recTime.textContent = fmt(sec);
        if (sec >= 120 && recorder) recorder.stop(); // 2 minutes maximum
      }, 250);
    }).catch(function () {
      showError('Micro inaccessible : autorisez-le dans votre navigateur pour envoyer un vocal.');
    });
  });
  $('chat-rec-send').addEventListener('click', function () { if (recorder) { cancelled = false; recorder.stop(); } });
  $('chat-rec-cancel').addEventListener('click', function () {
    if (recorder) { cancelled = true; recorder.stop(); } else { endRecordingUI(); }
  });
})();
