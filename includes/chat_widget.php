<?php /* Bulle de chat public : visiteurs <-> équipe Immobilier CI (voir assets/js/chat.js et chat_api.php) */ ?>
<div class="chat-widget" id="chat-widget" data-api="/chat_api">
  <button type="button" class="chat-fab" id="chat-fab" aria-label="Discuter avec l'équipe Immobilier CI" aria-expanded="false">
    <svg class="chat-fab-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.6-.8L3 21l1.9-5.3A8.4 8.4 0 1 1 21 11.5z"/></svg>
    <span class="chat-fab-label">Discutons</span>
    <span class="chat-unread" id="chat-unread" hidden>0</span>
  </button>

  <section class="chat-panel" id="chat-panel" role="dialog" aria-label="Chat avec Immobilier CI" hidden>
    <header class="chat-head">
      <div class="chat-head-avatar" aria-hidden="true">🏠</div>
      <div class="chat-head-text">
        <strong>Immobilier CI</strong>
        <span><i class="chat-online-dot"></i> Nous répondons dès que possible</span>
      </div>
      <button type="button" class="chat-close" id="chat-close" aria-label="Fermer le chat">×</button>
    </header>

    <div class="chat-intro" id="chat-intro">
      <input type="text" id="chat-name" maxlength="80" placeholder="Votre nom (facultatif)" autocomplete="name">
      <input type="tel" id="chat-phone" maxlength="20" placeholder="Téléphone (facultatif)" autocomplete="tel">
    </div>

    <div class="chat-messages" id="chat-messages" aria-live="polite">
      <div class="chat-msg chat-msg-agent chat-welcome">
        <div class="chat-bubble">Bonjour 👋 Posez votre question à l'équipe <strong>Immobilier CI</strong> : vous pouvez écrire, envoyer un message vocal ou joindre un fichier.</div>
      </div>
    </div>

    <div class="chat-attachment" id="chat-attachment" hidden>
      <span class="chat-attachment-name" id="chat-attachment-name"></span>
      <button type="button" id="chat-attachment-remove" aria-label="Retirer la pièce jointe">×</button>
    </div>

    <div class="chat-recording" id="chat-recording" hidden>
      <span class="chat-rec-dot"></span>
      <span>Enregistrement… <b id="chat-rec-time">0:00</b></span>
      <button type="button" id="chat-rec-cancel" class="chat-rec-cancel">Annuler</button>
      <button type="button" id="chat-rec-send" class="chat-rec-send">Envoyer</button>
    </div>

    <p class="chat-error" id="chat-error" role="alert" hidden></p>

    <form class="chat-form" id="chat-form" autocomplete="off">
      <input type="file" id="chat-file" hidden accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
      <button type="button" class="chat-tool" id="chat-attach" aria-label="Joindre un fichier" title="Joindre un fichier">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.4 11.1l-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/></svg>
      </button>
      <textarea id="chat-input" rows="1" maxlength="2000" placeholder="Écrivez votre message…" aria-label="Votre message"></textarea>
      <button type="button" class="chat-tool" id="chat-mic" aria-label="Message vocal" title="Message vocal">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/></svg>
      </button>
      <button type="submit" class="chat-send" id="chat-send" aria-label="Envoyer">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2.5 21.5 23 12 2.5 2.5l.01 7.4L16 12 2.51 14.1z"/></svg>
      </button>
    </form>
  </section>
</div>
