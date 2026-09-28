<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
sendSecurityHeaders();
$pdo = getPDO();

if (isLoggedIn()) {
    redirect(isSuperAdmin() ? 'superadmin/dashboard' : 'admin/dashboard');
}

$error = null;
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email !== '' && countRecentLoginFailures($pdo, $email) >= 5) {
            $locked = true;
            $error = 'Trop de tentatives échouées. Merci de réessayer dans 15 minutes.';
        } else {
            $user = attemptLogin($pdo, $email, $password);
            if ($user) {
                clearLoginFailures($pdo, $email);
                logActivity($pdo, $user['id'], 'Connexion réussie');
                redirect($user['role'] === 'superadmin' ? 'superadmin/dashboard' : 'admin/dashboard');
            }
            if ($email !== '') {
                recordLoginFailure($pdo, $email);
            }
            $error = 'Identifiants incorrects ou compte désactivé.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Connexion — Immobilier CI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/css/admin.css?v=<?= assetVersion('assets/css/admin.css') ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand"><img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img-lg"></div>
    <p class="sub">Espace Admin &amp; Super Admin</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <?php if (!$locked): ?>
    <form method="post">
      <?= csrfField() ?>
      <div class="field">
        <label>Adresse e-mail</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label>Mot de passe</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit">Se connecter</button>
    </form>
    <?php endif; ?>
    <a href="index" class="back-link">← Retour au site public</a>
  </div>
</div>
</body>
</html>
