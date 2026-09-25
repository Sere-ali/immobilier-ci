<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();
$isSuper = isSuperAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$property = null;
$images = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    $property = $stmt->fetch();
    if (!$property || (!$isSuper && $property['created_by'] != $user['id'])) {
        flash('error', "Vous n'avez pas accès à cette annonce.");
        redirect('properties.php');
    }
    $imgStmt = $pdo->prepare('SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, sort_order ASC');
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
}

$errors = [];

// Si la requête dépasse post_max_size, PHP vide $_POST et $_FILES avant même
// que le script ne s'exécute : on distingue ce cas d'une simple session expirée.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $errors[] = "Les fichiers envoyés sont trop volumineux au total (taille maximale : " . ini_get('post_max_size') . "). Réduisez le nombre ou le poids des photos et réessayez.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfVerify()) {
    $errors[] = 'Session expirée, merci de réessayer.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfVerify() && empty($errors)) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $listingType = $_POST['listing_type'] ?? 'vente';
    $category = $_POST['category'] ?? 'villa';
    $city = trim($_POST['city'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $surface = $_POST['surface'] !== '' ? (float)$_POST['surface'] : null;
    $bedrooms = $_POST['bedrooms'] !== '' ? (int)$_POST['bedrooms'] : null;
    $bathrooms = $_POST['bathrooms'] !== '' ? (int)$_POST['bathrooms'] : null;
    $status = $_POST['status'] ?? 'disponible';
    $featured = isset($_POST['featured']) ? 1 : 0;

    if ($title === '' || $city === '' || $price <= 0) {
        $errors[] = 'Le titre, la ville et le prix sont obligatoires.';
    }
    if (!array_key_exists($category, propertyCategories())) $errors[] = 'Catégorie invalide.';
    if (!in_array($listingType, ['vente', 'location'])) $errors[] = 'Type de transaction invalide.';

    if (empty($errors)) {
        if ($property) {
            $stmt = $pdo->prepare('UPDATE properties SET title=?, description=?, listing_type=?, category=?, city=?, commune=?, address=?, price=?, surface=?, bedrooms=?, bathrooms=?, status=?, featured=? WHERE id=?');
            $stmt->execute([$title, $description, $listingType, $category, $city, $commune, $address, $price, $surface, $bedrooms, $bathrooms, $status, $featured, $property['id']]);
            $propertyId = $property['id'];
            logActivity($pdo, $user['id'], "Modification de l'annonce #$propertyId");
        } else {
            $reference = generateReference($pdo);
            $slug = slugify($title) . '-' . strtolower(substr(md5(uniqid()), 0, 6));
            $stmt = $pdo->prepare('INSERT INTO properties (reference, title, slug, description, listing_type, category, city, commune, address, price, surface, bedrooms, bathrooms, status, featured, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$reference, $title, $slug, $description, $listingType, $category, $city, $commune, $address, $price, $surface, $bedrooms, $bathrooms, $status, $featured, $user['id']]);
            $propertyId = (int)$pdo->lastInsertId();
            logActivity($pdo, $user['id'], "Création de l'annonce #$propertyId ($title)");
        }

        $uploadWarnings = [];
        if (!empty($_FILES['images']['name'][0])) {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
            $hasPrimary = $pdo->prepare('SELECT COUNT(*) n FROM property_images WHERE property_id = ? AND is_primary = 1');
            $hasPrimary->execute([$propertyId]);
            $primaryExists = (int)$hasPrimary->fetch()['n'] > 0;

            $uploadErrorLabels = [
                UPLOAD_ERR_INI_SIZE   => 'trop volumineuse (limite serveur dépassée)',
                UPLOAD_ERR_FORM_SIZE  => 'trop volumineuse (limite du formulaire dépassée)',
                UPLOAD_ERR_PARTIAL    => 'envoi interrompu, merci de réessayer',
                UPLOAD_ERR_NO_TMP_DIR => 'erreur serveur (dossier temporaire manquant)',
                UPLOAD_ERR_CANT_WRITE => 'erreur serveur (écriture disque impossible)',
                UPLOAD_ERR_EXTENSION  => 'bloquée par une extension serveur',
            ];

            foreach ($_FILES['images']['name'] as $i => $name) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                    $reason = $uploadErrorLabels[$_FILES['images']['error'][$i]] ?? 'erreur inconnue';
                    $uploadWarnings[] = "« $name » n'a pas pu être envoyée : $reason.";
                    continue;
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $uploadWarnings[] = "« $name » a été ignorée : format non autorisé (jpg, jpeg, png, webp uniquement).";
                    continue;
                }

                $storedPath = null;
                if (cloudinaryConfigured()) {
                    $storedPath = uploadImageToCloudinary($_FILES['images']['tmp_name'][$i], $name);
                    if ($storedPath === null) {
                        $uploadWarnings[] = "« $name » n'a pas pu être envoyée vers le stockage externe (Cloudinary). Vérifiez les identifiants configurés.";
                        continue;
                    }
                } else {
                    $newName = uniqid('prop_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], UPLOAD_DIR . $newName)) {
                        $storedPath = $newName;
                    } else {
                        $uploadWarnings[] = "« $name » n'a pas pu être enregistrée sur le serveur.";
                        continue;
                    }
                }

                $isPrimary = (!$primaryExists && $i === 0) ? 1 : 0;
                $ins = $pdo->prepare('INSERT INTO property_images (property_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)');
                $ins->execute([$propertyId, $storedPath, $isPrimary, $i]);
                if ($isPrimary) $primaryExists = true;
            }
        }

        if (!empty($uploadWarnings)) {
            flash('warning', ($property ? 'Annonce mise à jour, mais : ' : 'Annonce créée, mais : ') . implode(' ', $uploadWarnings));
        } else {
            flash('success', $property ? 'Annonce mise à jour avec succès.' : 'Annonce créée avec succès.');
        }
        redirect('properties.php');
    }
}

$pageTitle = $property ? "Modifier l'annonce" : 'Nouvelle annonce';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel">
  <div class="panel-head"><h2><?= e($pageTitle) ?></h2></div>
  <div class="panel-body">
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="field full">
          <label>Titre de l'annonce *</label>
          <input type="text" name="title" value="<?= e($property['title'] ?? $_POST['title'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Type de transaction *</label>
          <select name="listing_type" required>
            <option value="vente" <?= (($property['listing_type'] ?? '') === 'vente') ? 'selected' : '' ?>>Vente</option>
            <option value="location" <?= (($property['listing_type'] ?? '') === 'location') ? 'selected' : '' ?>>Location</option>
          </select>
        </div>
        <div class="field">
          <label>Catégorie *</label>
          <select name="category" required>
            <?php foreach (propertyCategories() as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= (($property['category'] ?? '') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Ville *</label>
          <select name="city" required>
            <option value="">— Choisir —</option>
            <?php foreach (ivoryCoastCities() as $c): ?>
              <option value="<?= e($c) ?>" <?= (($property['city'] ?? '') === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Commune / Quartier</label>
          <input type="text" name="commune" value="<?= e($property['commune'] ?? '') ?>">
        </div>
        <div class="field full">
          <label>Adresse précise</label>
          <input type="text" name="address" value="<?= e($property['address'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Prix (FCFA) *</label>
          <input type="number" name="price" step="1" min="0" value="<?= e($property['price'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Surface (m²)</label>
          <input type="number" name="surface" step="0.01" min="0" value="<?= e($property['surface'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Chambres</label>
          <input type="number" name="bedrooms" min="0" value="<?= e($property['bedrooms'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Salles de bain</label>
          <input type="number" name="bathrooms" min="0" value="<?= e($property['bathrooms'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Statut</label>
          <select name="status">
            <?php foreach (propertyStatuses() as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= (($property['status'] ?? 'disponible') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end">
          <label class="checkbox-row"><input type="checkbox" name="featured" <?= !empty($property['featured']) ? 'checked' : '' ?>> Mettre à la une sur la page d'accueil</label>
        </div>
        <div class="field full">
          <label>Description</label>
          <textarea name="description" rows="6"><?= e($property['description'] ?? '') ?></textarea>
        </div>
        <div class="field full">
          <label>Photos <?= $images ? '(ajouter d\'autres photos)' : '' ?></label>
          <input type="file" id="images" name="images[]" multiple accept="image/png,image/jpeg,image/webp">
          <div class="hint">Formats acceptés : JPG, PNG, WEBP — 15 Mo maximum par photo.</div>
          <div id="images-preview" style="margin-top:8px"></div>
          <?php if ($images): ?>
            <div class="hint">Photos actuelles :</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
              <?php foreach ($images as $img): ?>
                <div style="width:70px;height:56px;border-radius:6px;background:center/cover no-repeat url('<?= e(imageUrl($img['image_path'], '../')) ?>');border:1px solid var(--border)"></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $property ? 'Enregistrer les modifications' : 'Créer l\'annonce' ?></button>
        <a href="properties.php" class="btn btn-outline">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
