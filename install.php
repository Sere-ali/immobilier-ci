<?php
/**
 * Installation initiale — Immobilier CI
 * Exécute database.sql puis crée le premier compte Super Admin.
 * A SUPPRIMER (ou renommer) du serveur une fois l'installation terminée.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
sendSecurityHeaders();

// Verrou de sécurité supplémentaire : sur un hébergement où l'on ne peut pas
// facilement supprimer ce fichier après coup (ex: déploiement Docker/Render
// basé sur un dépôt Git), on peut désactiver l'installateur en définissant
// la variable d'environnement ALLOW_INSTALL=false une fois l'installation faite.
$installAllowed = getenv('ALLOW_INSTALL') === false || strtolower((string)getenv('ALLOW_INSTALL')) !== 'false';

$errors = [];
$success = false;

$alreadyInstalled = false;
if ($installAllowed) {
    try {
        $pdo = getPDO();
        $check = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($check && $check->rowCount() > 0) {
            $countStmt = $pdo->query("SELECT COUNT(*) AS nb FROM users WHERE role = 'superadmin'");
            $alreadyInstalled = (int)$countStmt->fetch()['nb'] > 0;
        }
    } catch (Exception $e) {
        // La base n'existe peut-être pas encore, on continue vers l'installation
    }
}

if ($installAllowed && $_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled && !csrfVerify()) {
    $errors[] = 'Session expirée, merci de recharger la page et réessayer.';
}

if ($installAllowed && $_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled && csrfVerify()) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $seedDemo = isset($_POST['seed_demo']);

    if ($fullName === '' || $email === '' || $password === '') {
        $errors[] = 'Tous les champs obligatoires doivent être remplis.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse e-mail invalide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (empty($errors)) {
        try {
            $sql = file_get_contents(__DIR__ . '/database.sql');
            $sql = preg_replace('/CREATE DATABASE.*?;/s', '', $sql);
            $sql = preg_replace('/USE\s+\w+;/s', '', $sql);

            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, "superadmin", "actif")');
            $stmt->execute([$fullName, $email, $hash]);
            $superAdminId = (int)$pdo->lastInsertId();

            if ($seedDemo) {
                $demo = [
                    ['Villa moderne avec piscine', 'villa', 'vente', 'Abidjan', 'Cocody', 145000000, 350, 5, 4, 1],
                    ['Appartement 3 pièces vue lagune', 'appartement', 'location', 'Abidjan', 'Marcory', 350000, 90, 2, 2, 1],
                    ['Terrain constructible 600 m²', 'terrain', 'vente', 'Bingerville', 'Bingerville', 25000000, 600, null, null, 0],
                    ['Duplex standing Riviera Golf', 'villa', 'vente', 'Abidjan', 'Riviera', 210000000, 420, 6, 5, 1],
                    ['Bureau climatisé Plateau', 'bureau', 'location', 'Abidjan', 'Plateau', 800000, 120, null, 2, 0],
                    ['Boutique centre commercial', 'magasin', 'location', 'Abidjan', 'Marcory Zone 4', 450000, 45, null, 1, 0],
                ];
                foreach ($demo as $d) {
                    [$title, $cat, $type, $city, $commune, $price, $surface, $bed, $bath, $feat] = $d;
                    $ref = generateReference($pdo);
                    $slug = slugify($title) . '-' . strtolower(substr(md5(uniqid()), 0, 6));
                    $stmt = $pdo->prepare('INSERT INTO properties (reference, title, slug, description, listing_type, category, city, commune, price, surface, bedrooms, bathrooms, status, featured, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$ref, $title, $slug, "Bien de démonstration situé à $commune, $city. Contactez-nous pour plus d'informations et une visite.", $type, $cat, $city, $commune, $price, $surface, $bed, $bath, 'disponible', $feat, $superAdminId]);
                }
            }

            $success = true;
        } catch (Exception $e) {
            $errors[] = "Erreur durant l'installation : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Installation — Immobilier CI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.install-wrap{max-width:560px;margin:60px auto;padding:0 20px}
.install-card{background:#fff;border-radius:14px;padding:36px;box-shadow:0 10px 40px rgba(15,61,62,.1)}
.install-card h1{font-size:1.5rem;margin-bottom:6px}
.install-card p.lead{color:#6b6b63;margin-bottom:24px}
.field{margin-bottom:16px}
.field label{display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:#1b1b18}
.field input[type=text],.field input[type=email],.field input[type=password]{width:100%;padding:11px 13px;border:1px solid #ddd6c8;border-radius:8px;font-size:.95rem}
.checkbox-row{display:flex;align-items:center;gap:8px;margin-bottom:20px}
.btn-install{width:100%;padding:13px;border:none;border-radius:8px;background:#0f3d3e;color:#fff;font-weight:600;font-size:1rem;cursor:pointer}
.btn-install:hover{background:#0a2c2c}
.msg-error{background:#fdecea;color:#a12622;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:.9rem}
.msg-success{background:#eaf6ec;color:#1e6b34;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:.9rem}
@media (max-width:480px){.install-card{padding:24px 20px}}
</style>
</head>
<body style="background:#f6f4ee">
<div class="install-wrap">
  <div class="install-card">
    <h1>Installation — Immobilier CI</h1>
    <p class="lead">Cette page crée les tables de la base de données et votre compte Super Administrateur.</p>

    <?php if (!$installAllowed): ?>
      <div class="msg-error">L'installateur a été désactivé (variable d'environnement <code>ALLOW_INSTALL=false</code>). Retirez ou changez cette variable pour le réactiver temporairement.</div>
    <?php elseif ($alreadyInstalled): ?>
      <div class="msg-success">L'application est déjà installée. Rendez-vous sur <a href="login.php">la page de connexion</a>.<br><strong>Pensez à supprimer install.php du serveur pour des raisons de sécurité.</strong></div>
    <?php elseif ($success): ?>
      <div class="msg-success">Installation réussie ! Vous pouvez maintenant vous <a href="login.php">connecter</a> avec le compte Super Admin créé.<br><strong>Pensez à supprimer install.php du serveur maintenant.</strong></div>
    <?php else: ?>
      <?php foreach ($errors as $err): ?>
        <div class="msg-error"><?= e($err) ?></div>
      <?php endforeach; ?>
      <form method="post">
        <?= csrfField() ?>
        <div class="field">
          <label>Nom complet</label>
          <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Adresse e-mail</label>
          <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Mot de passe (min. 8 caractères)</label>
          <input type="password" name="password" required>
        </div>
        <div class="field">
          <label>Confirmer le mot de passe</label>
          <input type="password" name="password_confirm" required>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" id="seed_demo" name="seed_demo" checked>
          <label for="seed_demo" style="margin:0">Insérer des annonces de démonstration</label>
        </div>
        <button class="btn-install" type="submit">Installer l'application</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
