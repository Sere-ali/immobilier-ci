<?php
/**
 * Import ponctuel de 50 annonces de démonstration, réparties dans plusieurs
 * villes de Côte d'Ivoire, publiées au nom du Super Administrateur qui
 * exécute l'import. Réservé au Super Admin, protégé par CSRF, et ne peut
 * être exécuté qu'une seule fois (verrou dans la table settings) pour
 * éviter les doublons en cas de nouvel essai ou de rechargement de page.
 *
 * Les visuels sont des vignettes génériques (placehold.co) : aucune photo
 * ni annonce réelle d'un tiers n'est utilisée.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pdo = getPDO();
$user = currentUser();

$alreadySeeded = getSetting($pdo, 'demo_listings_seeded', '') === '1';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySeeded) {
    if (!csrfVerify()) {
        flash('error', 'Session expirée, merci de réessayer.');
        redirect('seed_demo_listings');
    }

    $listings = require __DIR__ . '/data/demo_listings.php';
    $insertProperty = $pdo->prepare(
        'INSERT INTO properties (reference, title, slug, description, listing_type, category, city, commune, price, surface, bedrooms, bathrooms, status, approval_status, featured, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insertImage = $pdo->prepare(
        'INSERT INTO property_images (property_id, image_path, is_primary, sort_order) VALUES (?,?,1,0)'
    );

    $created = 0;
    $pdo->beginTransaction();
    try {
        foreach ($listings as $item) {
            $slug = slugify($item['title']) . '-' . strtolower(substr(md5(uniqid('', true)), 0, 6));

            $maxAttempts = 5;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $reference = generateReference($pdo);
                try {
                    $insertProperty->execute([
                        $reference,
                        $item['title'],
                        $slug,
                        $item['description'],
                        $item['listing_type'],
                        $item['category'],
                        $item['city'],
                        $item['commune'],
                        $item['price'],
                        $item['surface'],
                        $item['bedrooms'],
                        $item['bathrooms'],
                        'disponible',
                        'approuve',
                        $item['featured'],
                        $user['id'],
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' && $attempt < $maxAttempts) {
                        continue;
                    }
                    throw $e;
                }
            }

            $propertyId = (int) $pdo->lastInsertId();
            $imageUrl = sprintf(
                'https://placehold.co/1000x625/%s/%s?text=%s',
                $item['img_bg'],
                $item['img_fg'],
                $item['img_label']
            );
            $insertImage->execute([$propertyId, $imageUrl]);
            $created++;
        }

        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('demo_listings_seeded', '1') ON DUPLICATE KEY UPDATE setting_value = '1'")->execute();
        $pdo->commit();

        logActivity($pdo, $user['id'], "Import de $created annonces de démonstration");
        flash('success', "$created annonces ont été ajoutées avec succès.");
        redirect('../admin/properties');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('seed_demo_listings error: ' . $e->getMessage());
        // Le détail exact est affiché ici (réservé au super admin) car les journaux
        // du serveur ne sont pas accessibles depuis l'environnement de développement :
        // c'est le seul moyen de diagnostiquer un échec à distance.
        flash('error', "L'import a échoué, aucune annonce n'a été ajoutée. Détail technique : " . $e->getMessage());
        redirect('seed_demo_listings');
    }
}

$pageTitle = 'Importer des annonces de démonstration';
$pageSubtitle = '50 annonces variées, réparties dans plusieurs villes de Côte d\'Ivoire';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="panel">
  <div class="panel-body">
    <?php if ($alreadySeeded): ?>
      <div class="alert alert-success">Les annonces de démonstration ont déjà été importées. Pour éviter les doublons, cet import ne peut être lancé qu'une seule fois.</div>
      <a href="../admin/properties" class="btn btn-primary">Voir les annonces</a>
    <?php else: ?>
      <p style="color:var(--ink-soft);max-width:640px;margin-bottom:20px">
        Ceci ajoute <strong>50 annonces variées</strong> (villas, appartements, terrains, bureaux, magasins, immeubles)
        réparties dans plusieurs villes de Côte d'Ivoire, publiées immédiatement sous votre compte.
        Les visuels sont des vignettes génériques — aucune photo ni annonce réelle d'un tiers n'est utilisée.
      </p>
      <form method="post">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-primary" data-confirm="Ajouter les 50 annonces de démonstration ?">Lancer l'import</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
