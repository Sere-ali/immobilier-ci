<?php
/**
 * Chat avec les visiteurs du site : liste des conversations + réponse
 * (texte, vocal, fichier). Accessible à tous les administrateurs connectés.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();
$webRoot = '../';

$activeId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// ---- Interrogation régulière (JSON) : nouveaux messages d'une conversation
if (($_GET['poll'] ?? '') === '1' && $activeId > 0) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $after = max(0, (int)($_GET['after'] ?? 0));
    $st = $pdo->prepare('SELECT * FROM chat_messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 100');
    $st->execute([$activeId, $after]);
    $rows = $st->fetchAll();
    if ($rows) {
        $pdo->prepare('UPDATE chat_conversations SET unread_admin = 0 WHERE id = ?')->execute([$activeId]);
    }
    echo json_encode(['messages' => array_map(function ($m) use ($webRoot) { return chatFormatMessage($m, '/'); }, $rows)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Actions POST : répondre / supprimer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = ($_POST['ajax'] ?? '') === '1';
    $fail = function (string $msg) use ($isAjax, $activeId) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('error', $msg);
        redirect('chat' . ($activeId ? '?id=' . $activeId : ''));
    };
    if (!csrfVerify()) $fail('Session expirée, rechargez la page.');

    $st = $pdo->prepare('SELECT id FROM chat_conversations WHERE id = ?');
    $st->execute([$activeId]);
    if (!$st->fetch()) $fail('Conversation introuvable.');

    if (($_POST['action'] ?? '') === 'delete') {
        chatDeleteConversationFiles($pdo, $activeId);
        $pdo->prepare('DELETE FROM chat_conversations WHERE id = ?')->execute([$activeId]);
        logActivity($pdo, $user['id'], "Suppression de la conversation de chat #$activeId");
        flash('success', 'Conversation supprimée.');
        redirect('chat');
    }

    if (($_POST['action'] ?? '') === 'reply') {
        $body = trim((string)($_POST['body'] ?? ''));
        if (mb_strlen($body) > 2000) $body = mb_substr($body, 0, 2000);
        $upload = null; $isVoice = false;
        if (!empty($_FILES['voice']) && $_FILES['voice']['error'] !== UPLOAD_ERR_NO_FILE) { $upload = $_FILES['voice']; $isVoice = true; }
        elseif (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) { $upload = $_FILES['file']; }
        if ($body === '' && !$upload) $fail('Message vide.');
        $path = $fname = $kind = null;
        if ($upload) {
            $res = chatStoreAttachment($upload, $isVoice);
            if (is_string($res)) $fail($res);
            [$path, $fname, $kind] = $res;
        }
        $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender, body, attachment_path, attachment_name, attachment_kind) VALUES (?, 'agent', ?, ?, ?, ?)")
            ->execute([$activeId, $body !== '' ? $body : null, $path, $fname, $kind]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE chat_conversations SET last_message_at = NOW(), unread_admin = 0 WHERE id = ?')->execute([$activeId]);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $st = $pdo->prepare('SELECT * FROM chat_messages WHERE id = ?');
            $st->execute([$newId]);
            echo json_encode(['message' => chatFormatMessage($st->fetch(), '/')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        redirect('chat?id=' . $activeId);
    }
    $fail('Action inconnue.');
}

// ---- Affichage
$convs = $pdo->query("SELECT c.*, (SELECT COALESCE(NULLIF(body,''), CONCAT('[', COALESCE(attachment_kind,'pièce jointe'), ']')) FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_text,
                              (SELECT sender FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_sender
                       FROM chat_conversations c ORDER BY c.last_message_at DESC LIMIT 100")->fetchAll();
if (!$activeId && $convs) $activeId = (int)$convs[0]['id'];

$active = null; $messages = [];
if ($activeId) {
    $st = $pdo->prepare('SELECT * FROM chat_conversations WHERE id = ?');
    $st->execute([$activeId]);
    $active = $st->fetch() ?: null;
    if ($active) {
        $pdo->prepare('UPDATE chat_conversations SET unread_admin = 0 WHERE id = ?')->execute([$activeId]);
        $st = $pdo->prepare('SELECT * FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 500');
        $st->execute([$activeId]);
        $messages = $st->fetchAll();
    }
}

$pageTitle = 'Chat';
$pageSubtitle = count($convs) . ' conversation(s) avec les visiteurs';
$liveDisabled = true;
require_once __DIR__ . '/../includes/admin_header.php';

function chatWhen(string $dt): string
{
    $t = strtotime($dt);
    return date('Y-m-d', $t) === date('Y-m-d') ? date('H:i', $t) : date('d/m', $t);
}
?>
<div class="chat-admin">
  <aside class="chat-admin-list">
    <?php if (!$convs): ?>
      <div class="empty-state"><div class="icon">💬</div><p>Aucune conversation pour le moment.<br>Les messages envoyés depuis la bulle de chat du site apparaîtront ici.</p></div>
    <?php endif; ?>
    <?php foreach ($convs as $c): $label = $c['visitor_name'] ?: 'Visiteur #' . $c['id']; ?>
      <a href="chat?id=<?= (int)$c['id'] ?>" class="chat-admin-item <?= (int)$c['id'] === $activeId ? 'active' : '' ?>">
        <span class="chat-admin-avatar"><?= e(mb_strtoupper(mb_substr($label, 0, 1))) ?></span>
        <span class="chat-admin-meta">
          <strong><?= e($label) ?></strong>
          <small><?= $c['last_sender'] === 'agent' ? 'Vous : ' : '' ?><?= e(mb_strimwidth((string)$c['last_text'], 0, 42, '…')) ?></small>
        </span>
        <span class="chat-admin-side">
          <small><?= e(chatWhen($c['last_message_at'])) ?></small>
          <?php if ((int)$c['unread_admin'] > 0 && (int)$c['id'] !== $activeId): ?><b class="nav-badge"><?= (int)$c['unread_admin'] ?></b><?php endif; ?>
        </span>
      </a>
    <?php endforeach; ?>
  </aside>

  <section class="chat-admin-thread">
    <?php if (!$active): ?>
      <div class="empty-state"><div class="icon">💬</div><p>Sélectionnez une conversation.</p></div>
    <?php else: ?>
      <header class="chat-admin-head">
        <div>
          <strong><?= e($active['visitor_name'] ?: 'Visiteur #' . $active['id']) ?></strong>
          <?php if ($active['visitor_phone']): ?><a class="mono" href="tel:<?= e($active['visitor_phone']) ?>"><?= e($active['visitor_phone']) ?></a><?php endif; ?>
        </div>
        <form method="post" action="chat?id=<?= (int)$active['id'] ?>" onsubmit="return confirm('Supprimer cette conversation et ses fichiers ?')">
          <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">🗑️ Supprimer</button>
        </form>
      </header>

      <div class="chat-admin-messages" id="cm-list" data-id="<?= (int)$active['id'] ?>" data-last="<?= $messages ? (int)end($messages)['id'] : 0 ?>">
        <?php foreach ($messages as $m): $f = chatFormatMessage($m, '../'); ?>
          <div class="cm-row <?= $m['sender'] === 'agent' ? 'cm-me' : 'cm-them' ?>" data-mid="<?= (int)$m['id'] ?>">
            <div class="cm-bubble">
              <?php if ($f['file']): ?>
                <?php if ($f['file']['kind'] === 'image'): ?>
                  <a href="<?= e($f['file']['url']) ?>" target="_blank" rel="noopener"><img src="<?= e($f['file']['url']) ?>" alt="<?= e($f['file']['name']) ?>" loading="lazy"></a>
                <?php elseif ($f['file']['kind'] === 'audio'): ?>
                  <audio controls preload="metadata" src="<?= e($f['file']['url']) ?>"></audio>
                <?php else: ?>
                  <a class="cm-file" href="<?= e($f['file']['url']) ?>" target="_blank" rel="noopener">📎 <?= e($f['file']['name']) ?></a>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($f['body'] !== ''): ?><div class="cm-text"><?= e($f['body']) ?></div><?php endif; ?>
              <span class="cm-time"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="chat-admin-error" id="cm-error" hidden></p>
      <div class="chat-admin-rec" id="cm-rec" hidden>
        <span class="cm-rec-dot"></span> Enregistrement… <b id="cm-rec-time">0:00</b>
        <button type="button" class="btn btn-outline btn-sm" id="cm-rec-cancel">Annuler</button>
        <button type="button" class="btn btn-primary btn-sm" id="cm-rec-send">Envoyer</button>
      </div>
      <form class="chat-admin-form" id="cm-form" method="post" action="chat?id=<?= (int)$active['id'] ?>" enctype="multipart/form-data">
        <?= csrfField() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
        <input type="file" name="file" id="cm-file" hidden accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
        <button type="button" class="chat-admin-tool" id="cm-attach" title="Joindre un fichier">📎</button>
        <textarea name="body" id="cm-input" rows="1" maxlength="2000" placeholder="Votre réponse…"></textarea>
        <button type="button" class="chat-admin-tool" id="cm-mic" title="Message vocal">🎤</button>
        <button type="submit" class="btn btn-primary" id="cm-send">Envoyer ➤</button>
      </form>
      <div class="chat-admin-file" id="cm-file-name" hidden></div>
    <?php endif; ?>
  </section>
</div>
<?php if ($active): ?><script src="<?= e($root) ?>assets/js/admin-chat.js?v=<?= assetVersion('assets/js/admin-chat.js') ?>" defer></script><?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
