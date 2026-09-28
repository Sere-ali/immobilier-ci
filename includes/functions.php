<?php
/**
 * Fonctions utilitaires — Immobilier CI
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Numéro de version pour un fichier statique (CSS/JS), basé sur sa date de
 * modification. Ajouté en `?v=...` sur les balises <link>/<script> pour que
 * le navigateur télécharge la nouvelle version dès qu'on republie le fichier,
 * même si `.htaccess` demande au navigateur de le garder en cache 1 mois.
 */
function assetVersion(string $relativePath): string
{
    $fullPath = __DIR__ . '/../' . ltrim($relativePath, '/');
    $mtime = @filemtime($fullPath);
    return $mtime ? (string) $mtime : '1';
}

/** Petit logo maison (SVG en ligne) utilisé à côté du nom du site, sur le site public et dans l'espace admin */
function logoMark(int $size = 32): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" class="logo-mark">'
         . '<rect width="34" height="34" rx="9" fill="#0F3D3E"/>'
         . '<polygon points="17,8 27,16.5 7,16.5" fill="#C87F2A"/>'
         . '<rect x="10.5" y="16.5" width="13" height="10" rx="1" fill="#ffffff"/>'
         . '<rect x="15.5" y="20.5" width="4" height="6" fill="#0F3D3E"/>'
         . '</svg>';
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

/** Libellés du statut de validation d'une annonce par le super admin */
function approvalStatuses(): array
{
    return [
        'en_attente' => 'En attente de validation',
        'approuve'   => 'Approuvée',
        'rejete'     => 'Rejetée',
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

/**
 * Limitation générique du nombre de soumissions d'un formulaire public
 * (contact, demande sur une annonce) par IP, pour éviter le spam/flood en
 * production — même principe que login_attempts mais réutilisable pour
 * n'importe quel formulaire via un "bucket" nommé.
 */
function countRecentSubmissions(PDO $pdo, string $bucket, string $identifier, int $windowMinutes = 15): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) n FROM rate_limits WHERE bucket = ? AND identifier = ? AND created_at > (NOW() - INTERVAL {$windowMinutes} MINUTE)");
    $stmt->execute([$bucket, $identifier]);
    return (int)$stmt->fetch()['n'];
}

function recordSubmission(PDO $pdo, string $bucket, string $identifier): void
{
    $pdo->prepare('INSERT INTO rate_limits (bucket, identifier) VALUES (?, ?)')->execute([$bucket, $identifier]);
    // Ménage occasionnel (1 requête sur ~50) pour ne pas laisser grossir la table indéfiniment.
    if (random_int(1, 50) === 1) {
        try { $pdo->exec("DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)"); } catch (Exception $e) {}
    }
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

    // v3 : index de performance (filtres/tris fréquents) + table pour limiter les
    // soumissions de formulaires publics (anti-spam), en vue d'un usage à plus grande échelle.
    if ($version < 3) {
        foreach ([
            "ALTER TABLE properties ADD INDEX idx_city (city)",
            "ALTER TABLE properties ADD INDEX idx_category (category)",
            "ALTER TABLE properties ADD INDEX idx_listing_type (listing_type)",
            "ALTER TABLE properties ADD INDEX idx_status (status)",
            "ALTER TABLE properties ADD INDEX idx_created_by (created_by)",
            "ALTER TABLE properties ADD INDEX idx_featured_created (featured, created_at)",
            "ALTER TABLE messages ADD INDEX idx_property_id (property_id)",
            "ALTER TABLE messages ADD INDEX idx_status (status)",
            "ALTER TABLE activity_log ADD INDEX idx_created_at (created_at)",
        ] as $ddl) {
            try { $pdo->exec($ddl); } catch (Exception $e) {} // déjà présent : on ignore
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bucket VARCHAR(50) NOT NULL,
                identifier VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bucket_identifier_time (bucket, identifier, created_at)
            ) ENGINE=InnoDB");
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', '3') ON DUPLICATE KEY UPDATE setting_value = '3'")->execute();
        } catch (Exception $e) {}
    }

    // v4 : validation des annonces par le super admin. Une annonce créée par un
    // administrateur simple reste masquée du site public tant qu'elle n'est pas
    // approuvée ; les annonces déjà existantes sont considérées approuvées
    // (valeur par défaut) pour ne rien faire disparaître du site.
    if ($version < 4) {
        try { $pdo->exec("ALTER TABLE properties ADD COLUMN approval_status ENUM('en_attente','approuve','rejete') NOT NULL DEFAULT 'approuve' AFTER status"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE properties ADD INDEX idx_approval_status (approval_status)"); } catch (Exception $e) {}
        try {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', '4') ON DUPLICATE KEY UPDATE setting_value = '4'")->execute();
        } catch (Exception $e) {}
    }

    // v5 : demande ponctuelle — création du compte administrateur "Abdul Karim
    // Barreau" et d'une annonce de terrain à Anyama (prix/surface basés sur une
    // annonce réelle du marché local). N'insère rien si déjà présent (relance
    // sans risque du même code à chaque déploiement tant que la version < 5).
    if ($version < 5) {
        try {
            $seedEmail = 'abdulkarim.barreau@immobilier-ci.ci';
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$seedEmail]);
            $adminId = $stmt->fetchColumn();

            if (!$adminId) {
                // Mot de passe temporaire généré à l'exécution : jamais écrit dans le code
                // source, uniquement consultable une fois par un super admin dans le
                // journal d'activité (à changer dès la première connexion).
                $tempPassword = 'AK-' . bin2hex(random_bytes(4)) . '-7';
                $ins = $pdo->prepare('INSERT INTO users (full_name, email, password, role, status) VALUES (?,?,?,?,?)');
                $ins->execute(['Abdul Karim Barreau', $seedEmail, password_hash($tempPassword, PASSWORD_DEFAULT), 'admin', 'actif']);
                $adminId = (int) $pdo->lastInsertId();
                logActivity($pdo, null, "Compte administrateur créé pour Abdul Karim Barreau ($seedEmail) — mot de passe temporaire : $tempPassword — à changer dès la première connexion.");
            } else {
                $adminId = (int) $adminId;
            }

            $seedSlug = 'terrain-500m2-nouveau-quartier-anyama';
            $check = $pdo->prepare('SELECT id FROM properties WHERE slug = ?');
            $check->execute([$seedSlug]);
            $propertyId = $check->fetchColumn();

            if (!$propertyId) {
                $reference = generateReference($pdo);
                $title = 'Terrain de 500 m² — Nouveau quartier, Anyama';
                $description = "Parcelle de 500 m² dans un lotissement en développement du nouveau quartier d'Anyama, accessible et constructible. Idéale pour un projet résidentiel. Prix aligné sur une offre réelle du marché local à Anyama.";
                $ins = $pdo->prepare('INSERT INTO properties (reference, title, slug, description, listing_type, category, city, commune, address, price, surface, bedrooms, bathrooms, status, approval_status, featured, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $ins->execute([
                    $reference, $title, $seedSlug, $description,
                    'vente', 'terrain', 'Anyama', 'Nouveau quartier', null,
                    3800000, 500, null, null,
                    'disponible', 'en_attente', 0, $adminId,
                ]);
                $propertyId = (int) $pdo->lastInsertId();
                logActivity($pdo, $adminId, "Création de l'annonce #$propertyId ($title)");

                // Illustration d'origine (pas une photo du terrain réel, faute de photo
                // sous licence disponible) : passe par le même circuit que les photos
                // envoyées via le formulaire (filigrane, puis Cloudinary ou stockage local).
                $seedImage = __DIR__ . '/../assets/seed/terrain-anyama.jpg';
                if (is_file($seedImage)) {
                    $workCopy = sys_get_temp_dir() . '/seed_terrain_' . uniqid() . '.jpg';
                    if (copy($seedImage, $workCopy)) {
                        applyWatermark($workCopy);
                        $stored = null;
                        if (cloudinaryConfigured()) {
                            $stored = uploadImageToCloudinary($workCopy, 'terrain-anyama.jpg');
                        }
                        if (!$stored) {
                            if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);
                            $newName = 'seed_terrain_anyama_' . substr(md5((string) $propertyId), 0, 8) . '.jpg';
                            if (@copy($workCopy, UPLOAD_DIR . $newName)) $stored = $newName;
                        }
                        if ($stored) {
                            $pdo->prepare('INSERT INTO property_images (property_id, image_path, is_primary, sort_order) VALUES (?,?,1,0)')->execute([$propertyId, $stored]);
                        }
                        @unlink($workCopy);
                    }
                }
            }

            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', '5') ON DUPLICATE KEY UPDATE setting_value = '5'")->execute();
        } catch (Exception $e) {
            error_log('Migration v5 (seed Anyama) error: ' . $e->getMessage());
        }
    }

    // v6 : notion de "super administrateur principal". Un super admin nommé
    // (promu depuis un compte administrateur) reste protégé face aux autres
    // super admins classiques, mais PAS face au super administrateur principal
    // — celui-ci garde toujours le contrôle total et peut gérer/rétrograder
    // n'importe quel super admin qu'il a nommé. Le tout premier compte super
    // admin de l'installation est désigné principal automatiquement s'il n'y
    // en a pas encore.
    if ($version < 6) {
        try {
            try { $pdo->exec("ALTER TABLE users ADD COLUMN is_principal TINYINT(1) NOT NULL DEFAULT 0 AFTER role"); } catch (Exception $e) {}

            $hasPrincipal = (int) $pdo->query("SELECT COUNT(*) n FROM users WHERE is_principal = 1")->fetch()['n'];
            if (!$hasPrincipal) {
                $firstSuperAdminId = $pdo->query("SELECT id FROM users WHERE role = 'superadmin' ORDER BY id ASC LIMIT 1")->fetchColumn();
                if ($firstSuperAdminId) {
                    $pdo->prepare('UPDATE users SET is_principal = 1 WHERE id = ?')->execute([$firstSuperAdminId]);
                }
            }

            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', '6') ON DUPLICATE KEY UPDATE setting_value = '6'")->execute();
        } catch (Exception $e) {
            error_log('Migration v6 (super admin principal) error: ' . $e->getMessage());
        }
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

/** Indicatif téléphonique de la Côte d'Ivoire, ajouté automatiquement — l'utilisateur ne saisit que son numéro local */
const IVORY_COAST_CALLING_CODE = '225';

/**
 * true si la saisie contient exactement 10 chiffres (numéro local ivoirien,
 * sans l'indicatif +225 qui est ajouté automatiquement). Les espaces et tirets
 * éventuellement tapés par l'utilisateur sont ignorés, mais tout le reste
 * (lettres, symboles) rend le numéro invalide.
 */
function isValidLocalIvoryCoastPhone(string $raw): bool
{
    $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
    return strlen($digits) === 10 && $digits === preg_replace('/[\s\-.]/', '', trim($raw));
}

/**
 * Construit le numéro complet à stocker en base (indicatif 225 + les 10
 * chiffres locaux saisis), à partir de l'entrée brute de l'utilisateur.
 * À n'appeler qu'après isValidLocalIvoryCoastPhone().
 */
function formatIvoryCoastPhoneForStorage(string $raw): string
{
    $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
    return IVORY_COAST_CALLING_CODE . substr($digits, 0, 10);
}

/**
 * Retire l'indicatif +225 d'un numéro déjà stocké en base, pour ré-afficher
 * uniquement les 10 chiffres locaux dans un champ de formulaire à modifier.
 * Reste tolérant avec d'anciens numéros enregistrés dans un format différent.
 */
function stripIvoryCoastCountryCode(string $stored): string
{
    $digits = preg_replace('/[^0-9]/', '', $stored) ?? '';
    if (strlen($digits) === 13 && substr($digits, 0, 3) === IVORY_COAST_CALLING_CODE) {
        return substr($digits, 3);
    }
    if (strlen($digits) === 10) {
        return $digits;
    }
    return $digits;
}

/** Formate un numéro stocké (225XXXXXXXXXX) en version lisible : +225 XX XX XX XX XX */
function formatIvoryCoastPhoneDisplay(string $stored): string
{
    $digits = preg_replace('/[^0-9]/', '', $stored) ?? '';
    if (strlen($digits) === 13 && substr($digits, 0, 3) === IVORY_COAST_CALLING_CODE) {
        $local = substr($digits, 3);
        return '+225 ' . implode(' ', str_split($local, 2));
    }
    return $stored;
}

/**
 * Nettoie une chaîne de texte libre avant stockage en base : retire les octets
 * nuls et caractères de contrôle, coupe les espaces superflus, et tronque à
 * une longueur maximale. Important : la validation seule ne suffit pas — un
 * champ peut « passer » une vérification de format tout en contenant des
 * caractères indésirables ; c'est toujours la valeur nettoyée qu'il faut
 * enregistrer, jamais l'entrée brute.
 */
function sanitizeText(string $value, int $maxLength = 255): string
{
    $value = trim($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLength);
    } else {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

/**
 * Nettoie un numéro de téléphone/WhatsApp pour le STOCKAGE en base (distinct
 * de cleanWhatsappNumber, qui prépare un numéro pour un lien wa.me). Ne garde
 * que les chiffres (et un éventuel + en tête), tronqué à 15 chiffres. La
 * validation de format (isValidWhatsappNumber) ne garantit pas que la chaîne
 * d'origine est propre : il faut toujours stocker cette version nettoyée, pas
 * le texte brut envoyé par le visiteur.
 */
function sanitizePhoneForStorage(string $raw): string
{
    $raw = trim($raw);
    $hasPlus = strpos($raw, '+') === 0;
    $digits = preg_replace('/\D/', '', $raw) ?? '';
    $digits = substr($digits, 0, 15);
    return ($hasPlus ? '+' : '') . $digits;
}

/** Vérifie qu'un fichier téléversé est réellement une image (pas seulement son extension) */
function isRealImageFile(string $tmpPath): bool
{
    $info = @getimagesize($tmpPath);
    if ($info === false) return false;
    return in_array($info[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
}

/**
 * Applique un filigrane "IMMOBILIER CI" répété en diagonale sur une image, en
 * remplaçant le fichier sur place. Sert à dissuader la réutilisation des photos
 * d'annonces ailleurs, tout en indiquant clairement leur origine. Échoue
 * silencieusement (retourne false) si GD/FreeType ou la police ne sont pas
 * disponibles : l'appelant doit alors simplement garder l'image d'origine.
 */
function applyWatermark(string $filePath): bool
{
    if (!function_exists('imagettftext') || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    $fontPath = __DIR__ . '/../assets/fonts/DejaVuSans-Bold.ttf';
    if (!is_file($fontPath)) {
        return false;
    }

    $info = @getimagesize($filePath);
    if ($info === false) {
        return false;
    }
    [$width, $height] = $info;
    $mime = $info['mime'] ?? '';

    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($filePath);
            break;
        case 'image/webp':
            $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false;
            break;
        default:
            return false;
    }
    if (!$image) {
        return false;
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $text = 'IMMOBILIER CI';
    $angle = -30;
    $fontSize = max(12, min(30, (int) round($width / 22)));

    // Ombre sombre + texte clair superposés : reste lisible aussi bien sur une
    // photo sombre que sur une photo claire.
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 100);
    $light  = imagecolorallocatealpha($image, 255, 255, 255, 96);

    $box = imagettfbbox($fontSize, $angle, $fontPath, $text);
    $textWidth  = max(1, abs($box[4] - $box[0]));
    $textHeight = max(1, abs($box[5] - $box[1]));
    $stepX = $textWidth + 90;
    $stepY = $textHeight + 70;

    for ($y = -$stepY; $y < $height + $stepY; $y += $stepY) {
        for ($x = -$stepX; $x < $width + $stepX; $x += $stepX) {
            imagettftext($image, $fontSize, $angle, (int) $x + 1, (int) $y + 1, $shadow, $fontPath, $text);
            imagettftext($image, $fontSize, $angle, (int) $x, (int) $y, $light, $fontPath, $text);
        }
    }

    $ok = false;
    switch ($mime) {
        case 'image/jpeg':
            $ok = imagejpeg($image, $filePath, 88);
            break;
        case 'image/png':
            $ok = imagepng($image, $filePath, 6);
            break;
        case 'image/webp':
            $ok = imagewebp($image, $filePath, 88);
            break;
    }
    imagedestroy($image);
    return $ok;
}

/** Valide et convertit une valeur numérique entière bornée ; retourne null si invalide */
function validateBoundedInt($value, int $min, int $max): ?int
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) return null;
    $n = (int)$value;
    if ($n < $min || $n > $max) return null;
    return $n;
}

/** Valide et convertit une valeur numérique décimale bornée ; retourne null si invalide */
function validateBoundedFloat($value, float $min, float $max): ?float
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) return null;
    $n = (float)$value;
    if ($n < $min || $n > $max) return null;
    return $n;
}

/** Lit un paramètre $_GET de façon sûre : renvoie toujours une chaîne (défend contre les valeurs de type tableau) */
function gs(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Calcule les paramètres de pagination (page courante, limite, offset, nombre
 * total de pages) à partir de $_GET['page'] et du nombre total de résultats
 * — pour ne jamais charger une liste entière en mémoire quand elle grossit.
 */
function paginate(int $totalItems, int $perPage = 20): array
{
    $rawPage = gs('page', '1');
    $page = is_numeric($rawPage) ? max(1, (int)$rawPage) : 1;
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $page = min($page, $totalPages);
    return [
        'page' => $page,
        'perPage' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'totalPages' => $totalPages,
        'totalItems' => $totalItems,
    ];
}

/** Génère les liens « Précédent / Page X sur Y / Suivant », en conservant les filtres déjà présents dans l'URL */
function paginationLinks(array $pagination): string
{
    if ($pagination['totalPages'] <= 1) return '';
    $params = $_GET;
    unset($params['page']);

    $buildUrl = function (int $targetPage) use ($params): string {
        $params['page'] = $targetPage;
        return '?' . http_build_query($params);
    };

    $html = '<nav class="pagination">';
    if ($pagination['page'] > 1) {
        $html .= '<a href="' . e($buildUrl($pagination['page'] - 1)) . '" class="page-link">← Précédent</a>';
    } else {
        $html .= '<span class="page-link disabled">← Précédent</span>';
    }
    $html .= '<span class="page-info">Page ' . $pagination['page'] . ' sur ' . $pagination['totalPages'] . '</span>';
    if ($pagination['page'] < $pagination['totalPages']) {
        $html .= '<a href="' . e($buildUrl($pagination['page'] + 1)) . '" class="page-link">Suivant →</a>';
    } else {
        $html .= '<span class="page-link disabled">Suivant →</span>';
    }
    $html .= '</nav>';
    return $html;
}
