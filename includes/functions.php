<?php
/**
 * Fonctions utilitaires — Immobilier CI
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function truncateText(string $text, int $length = 60, string $suffix = '…'): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . $suffix : $text;
    }
    return strlen($text) > $length ? substr($text, 0, $length) . $suffix : $text;
}

function formatPrice($price): string
{
    return number_format((float)$price, 0, ',', ' ') . ' FCFA';
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'bien-' . time();
}

function generateReference(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) AS nb FROM properties WHERE YEAR(created_at) = $year");
    $count = (int)$stmt->fetch()['nb'] + 1;
    return 'CI-' . $year . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}

function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function logActivity(PDO $pdo, ?int $userId, string $action): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action) VALUES (?, ?)');
    $stmt->execute([$userId, $action]);
}

function ivoryCoastCities(): array
{
    return [
        'Abidjan', 'Bouaké', 'Yamoussoukro', 'San-Pédro', 'Korhogo',
        'Daloa', 'Man', 'Gagnoa', 'Abengourou', 'Grand-Bassam', 'Bingerville', 'Assinie',
    ];
}

function propertyCategories(): array
{
    return [
        'villa'       => 'Villa',
        'appartement' => 'Appartement',
        'terrain'     => 'Terrain',
        'bureau'      => 'Bureau',
        'magasin'     => 'Magasin / Boutique',
        'immeuble'    => 'Immeuble',
    ];
}

function propertyStatuses(): array
{
    return [
        'disponible' => 'Disponible',
        'reserve'    => 'Réservé',
        'vendu'      => 'Vendu',
        'loue'       => 'Loué',
    ];
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerify(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/** Renvoie true si la requête est servie en HTTPS, y compris derrière un proxy comme celui de Render */
function isHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

/** Récupère l'IP réelle du visiteur, y compris derrière le proxy de Render */
function clientIp(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Nombre de tentatives de connexion échouées pour cet e-mail durant les 15 dernières minutes */
function countRecentLoginFailures(PDO $pdo, string $email): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) n FROM login_attempts WHERE email = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$email]);
    return (int)$stmt->fetch()['n'];
}

function recordLoginFailure(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
    $stmt->execute([$email, clientIp()]);
}

function clearLoginFailures(PDO $pdo, string $email): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
}

/** Envoie les en-têtes de sécurité HTTP recommandés. À appeler le plus tôt possible. */
function sendSecurityHeaders(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Migration automatique et légère du schéma : ajoute les colonnes/réglages
 * manquants sans intervention manuelle (utile sur Render où il n'y a pas de
 * phpMyAdmin facilement accessible pour la base privée).
 */
function ensureSchemaUpToDate(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'schema_version'");
        $version = $stmt ? (int)($stmt->fetchColumn() ?: 0) : 0;
    } catch (Exception $e) {
        return; // La table settings n'existe pas encore (avant install.php) : rien à faire
    }

    if ($version < 2) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN whatsapp VARCHAR(30) DEFAULT NULL AFTER phone"); } catch (Exception $e) {}
        try {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_whatsapp', '') ON DUPLICATE KEY UPDATE setting_key = setting_key")->execute();
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', '2') ON DUPLICATE KEY UPDATE setting_value = '2'")->execute();
        } catch (Exception $e) {}
    }
}

/**
 * Construit l'URL d'affichage d'une image de bien, qu'elle soit stockée
 * localement (nom de fichier) ou sur un stockage externe comme Cloudinary
 * (URL complète déjà stockée telle quelle en base).
 */
function imageUrl(?string $path, string $webPrefix = ''): string
{
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return $webPrefix . UPLOAD_URL . $path;
}

/**
 * Envoie une image vers Cloudinary (stockage persistant, gratuit jusqu'à
 * 25 Go). Retourne l'URL sécurisée du fichier hébergé, ou null si Cloudinary
 * n'est pas configuré (variables d'environnement absentes) ou en cas d'échec
 * — dans ce cas l'appelant doit se rabattre sur le stockage local classique.
 */
function uploadImageToCloudinary(string $tmpPath, string $originalName): ?string
{
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey    = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');

    if (!$cloudName || !$apiKey || !$apiSecret || !function_exists('curl_init')) {
        return null;
    }

    $timestamp = time();
    $paramsToSign = ['folder' => 'immobilier-ci', 'timestamp' => $timestamp];
    ksort($paramsToSign);
    $toSign = '';
    foreach ($paramsToSign as $k => $v) {
        $toSign .= ($toSign !== '' ? '&' : '') . $k . '=' . $v;
    }
    $signature = sha1($toSign . $apiSecret);

    $mime = function_exists('mime_content_type') ? (mime_content_type($tmpPath) ?: 'image/jpeg') : 'image/jpeg';

    $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file'      => new CURLFile($tmpPath, $mime, $originalName),
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'folder'    => 'immobilier-ci',
            'signature' => $signature,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }
    $data = json_decode($response, true);
    return $data['secure_url'] ?? null;
}

/** true si le stockage externe (Cloudinary) est configuré et utilisable */
function cloudinaryConfigured(): bool
{
    return (bool) (getenv('CLOUDINARY_CLOUD_NAME') && getenv('CLOUDINARY_API_KEY') && getenv('CLOUDINARY_API_SECRET'));
}

/** Nettoie un numéro de téléphone pour un lien wa.me (chiffres uniquement, + optionnel en tête ignoré) */
function cleanWhatsappNumber(string $number): string
{
    $number = trim($number);
    $number = preg_replace('/[^0-9+]/', '', $number);
    return ltrim($number, '+');
}

/** true si la chaîne ressemble à un numéro de téléphone WhatsApp valide (8 à 15 chiffres) */
function isValidWhatsappNumber(string $number): bool
{
    $digits = cleanWhatsappNumber($number);
    return (bool) preg_match('/^\d{8,15}$/', $digits);
}

/** Construit un lien wa.me prêt à l'emploi avec message pré-rempli */
function waLink(string $number, string $message): string
{
    $digits = cleanWhatsappNumber($number);
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}
