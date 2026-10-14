<?php
/**
 * API du chat public (JSON). Le visiteur est identifié par un jeton aléatoire
 * gardé dans son navigateur : c'est ce jeton qui donne accès à SA conversation.
 *
 *  GET  chat_api?action=poll&token=...&after=ID  -> nouveaux messages
 *  POST chat_api  action=send  (token, body, name, phone, file, voice)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chatOut(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Requêtes du site uniquement (même origine) : limite les envois depuis d'autres domaines.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $host = parse_url($origin, PHP_URL_HOST);
    if ($host !== ($_SERVER['HTTP_HOST'] ?? '') && $host !== preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')) {
        chatOut(['error' => 'Origine refusée.'], 403);
    }
}

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';
$token = (string)($_REQUEST['token'] ?? '');
$validToken = (bool) preg_match('/^[a-f0-9]{40}$/', $token);

$conv = null;
if ($validToken) {
    $st = $pdo->prepare('SELECT * FROM chat_conversations WHERE token = ?');
    $st->execute([$token]);
    $conv = $st->fetch() ?: null;
}

if ($action === 'poll') {
    if (!$conv) chatOut(['messages' => []]);
    $after = max(0, (int)($_GET['after'] ?? 0));
    $st = $pdo->prepare('SELECT * FROM chat_messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 100');
    $st->execute([$conv['id'], $after]);
    chatOut(['messages' => array_map(function ($m) { return chatFormatMessage($m, '/'); }, $st->fetchAll())]);
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim((string)($_POST['body'] ?? ''));
    if (mb_strlen($body) > 2000) $body = mb_substr($body, 0, 2000);

    $upload = null;
    $isVoice = false;
    if (!empty($_FILES['voice']) && $_FILES['voice']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['voice']; $isVoice = true;
    } elseif (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['file'];
    }
    if ($body === '' && !$upload) chatOut(['error' => 'Message vide.'], 422);

    if (!$conv) {
        // Limite : 5 nouvelles conversations par heure et par adresse IP
        $ip = substr(clientIp(), 0, 45);
        $st = $pdo->prepare('SELECT COUNT(*) FROM chat_conversations WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() >= 5) chatOut(['error' => 'Trop de demandes. Réessayez plus tard.'], 429);
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $phone = preg_replace('/[^0-9+ ]/', '', (string)($_POST['phone'] ?? ''));
        $token = bin2hex(random_bytes(20));
        $pdo->prepare('INSERT INTO chat_conversations (token, visitor_name, visitor_phone, ip) VALUES (?,?,?,?)')
            ->execute([$token, $name ?: null, substr($phone, 0, 30) ?: null, $ip]);
        $conv = ['id' => (int)$pdo->lastInsertId(), 'token' => $token];
    } else {
        // Limite : 20 messages par minute dans une même conversation
        $st = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE conversation_id = ? AND sender = 'visitor' AND created_at > (NOW() - INTERVAL 1 MINUTE)");
        $st->execute([$conv['id']]);
        if ((int)$st->fetchColumn() >= 20) chatOut(['error' => 'Doucement, trop de messages à la suite.'], 429);
    }

    $path = $fname = $kind = null;
    if ($upload) {
        $res = chatStoreAttachment($upload, $isVoice);
        if (is_string($res)) chatOut(['error' => $res], 422);
        [$path, $fname, $kind] = $res;
    }

    $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender, body, attachment_path, attachment_name, attachment_kind) VALUES (?, 'visitor', ?, ?, ?, ?)")
        ->execute([$conv['id'], $body !== '' ? $body : null, $path, $fname, $kind]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE chat_conversations SET unread_admin = unread_admin + 1, last_message_at = NOW() WHERE id = ?')->execute([$conv['id']]);

    $st = $pdo->prepare('SELECT * FROM chat_messages WHERE id = ?');
    $st->execute([$id]);
    chatOut(['token' => $conv['token'], 'message' => chatFormatMessage($st->fetch(), '/')]);
}

chatOut(['error' => 'Requête invalide.'], 400);
