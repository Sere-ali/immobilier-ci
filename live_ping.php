<?php
/**
 * Ping « temps réel » : renvoie une simple empreinte (hash) de l'état des données.
 * Quand elle change, les pages ouvertes rechargent automatiquement leurs zones
 * concernées (voir assets/js/live.js) : plus besoin d'actualiser.
 * Aucune donnée n'est exposée, seulement l'empreinte. La portée « admin »
 * (messages, chat, utilisateurs...) n'est prise en compte que pour un admin connecté.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = getPDO();
$parts = [];

function livePart(PDO $pdo, string $sql): string
{
    try {
        $row = $pdo->query($sql)->fetch(PDO::FETCH_NUM);
        return $row ? implode(',', $row) : '';
    } catch (Exception $e) {
        return '';
    }
}

$scopeAdmin = (($_GET['scope'] ?? '') === 'admin') && isLoggedIn();

// Annonces (visibles du public et des admins)
$parts[] = livePart($pdo, "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', id, status, approval_status, price, title, featured, city, COALESCE(video_path,'')))),0) FROM properties");
$parts[] = livePart($pdo, "SELECT COUNT(*) FROM property_images");

if ($scopeAdmin) {
    $parts[] = livePart($pdo, "SELECT COALESCE(SUM(views),0) FROM properties");
    $parts[] = livePart($pdo, "SELECT COUNT(*), COALESCE(SUM(status='nouveau'),0) FROM messages");
    $parts[] = livePart($pdo, "SELECT COUNT(*), COALESCE(MAX(id),0) FROM chat_messages");
    $parts[] = livePart($pdo, "SELECT COALESCE(SUM(unread_admin),0), COUNT(*) FROM chat_conversations");
    $parts[] = livePart($pdo, "SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', id, status, role))),0) FROM users");
    $parts[] = livePart($pdo, "SELECT COUNT(*), COALESCE(MAX(id),0) FROM activity_log");
}

echo json_encode(['v' => md5(implode(';', $parts))]);
