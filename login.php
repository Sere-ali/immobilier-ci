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
$flashMsg = getFlash();

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
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1D4ED8">
<link rel="icon" href="/assets/img/icons/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/icons/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,500&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/css/admin.css?v=<?= assetVersion('assets/css/admin.css') ?>">
</head>
<body>
<div class="login-wrap">

  <aside class="login-visual">
    <div class="login-blob login-blob-1"></div>
    <div class="login-blob login-blob-2"></div>
    <div class="login-visual-content">
      <img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img-lg login-visual-logo">
      <h1>Bienvenue dans<br>votre espace</h1>
      <p>Gérez vos annonces, vos messages et votre équipe en toute simplicité, où que vous soyez.</p>
      <ul class="login-perks">
        <li><span>🏠</span> Publiez et suivez vos annonces</li>
        <li><span>💬</span> Répondez à vos messages en un clic</li>
        <li><span>🛡️</span> Accès sécurisé par rôle</li>
      </ul>
    </div>
  </aside>

  <main class="login-panel">
    <div class="login-card">
      <div class="login-card-head login-visual-logo-mobile">
        <img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img-lg">
      </div>
      <h2 class="login-card-title">Connexion</h2>
      <p class="sub">Espace Admin &amp; Super Admin</p>

      <?php if ($flashMsg): ?><div class="alert alert-<?= $flashMsg['type'] === 'success' ? 'success' : 'error' ?>"><?= e($flashMsg['message']) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <?php if (!$locked): ?>
      <form method="post">
        <?= csrfField() ?>
        <div class="field field-anim input-icon input-icon-mail">
          <label>Adresse e-mail</label>
          <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="vous@immobilier-ci.ci" required autofocus>
        </div>
        <div class="field field-anim input-icon input-icon-lock">
          <label>Mot de passe</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="login-submit field-anim">Se connecter <span class="arrow">→</span></button>
      </form>
      <?php endif; ?>
      <a href="index" class="back-link">← Retour au site public</a>
    </div>
  </main>

</div>
<script src="assets/js/password-toggle.js?v=<?= assetVersion('assets/js/password-toggle.js') ?>" defer></script>
</body>
</html>
