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
        redirect('properties');
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
    $title = sanitizeText(trim($_POST['title'] ?? ''), 190);
    $description = sanitizeText(trim($_POST['description'] ?? ''), 5000);
    $listingType = $_POST['listing_type'] ?? 'vente';
    $category = $_POST['category'] ?? 'villa';
    $city = sanitizeText(trim($_POST['city'] ?? ''), 100);
    $commune = sanitizeText(trim($_POST['commune'] ?? ''), 100);
    $address = sanitizeText(trim($_POST['address'] ?? ''), 255);
    $status = $_POST['status'] ?? 'disponible';
    $featured = isset($_POST['featured']) ? 1 : 0;

    $price = validateBoundedFloat($_POST['price'] ?? null, 0, 999999999999);
    $surface = ($_POST['surface'] ?? '') !== '' ? validateBoundedFloat($_POST['surface'], 0, 999999) : null;
    $bedrooms = ($_POST['bedrooms'] ?? '') !== '' ? validateBoundedInt($_POST['bedrooms'], 0, 999) : null;
    $bathrooms = ($_POST['bathrooms'] ?? '') !== '' ? validateBoundedInt($_POST['bathrooms'], 0, 999) : null;

    if ($title === '' || $city === '' || $price === null || $price <= 0) {
        $errors[] = 'Le titre, la ville et le prix sont obligatoires.';
    }
    if (($_POST['surface'] ?? '') !== '' && $surface === null) $errors[] = 'Surface invalide.';
    if (($_POST['bedrooms'] ?? '') !== '' && $bedrooms === null) $errors[] = 'Nombre de chambres invalide.';
    if (($_POST['bathrooms'] ?? '') !== '' && $bathrooms === null) $errors[] = 'Nombre de salles de bain invalide.';
    if (!array_key_exists($category, propertyCategories())) $errors[] = 'Catégorie invalide.';
    if (!in_array($listingType, ['vente', 'location'])) $errors[] = 'Type de transaction invalide.';
    if (!array_key_exists($status, propertyStatuses())) $errors[] = 'Statut invalide.';

    if (empty($errors)) {
        if ($property) {
            $stmt = $pdo->prepare('UPDATE properties SET title=?, description=?, listing_type=?, category=?, city=?, commune=?, address=?, price=?, surface=?, bedrooms=?, bathrooms=?, status=?, featured=? WHERE id=?');
            $stmt->execute([$title, $description, $listingType, $category, $city, $commune, $address, $price, $surface, $bedrooms, $bathrooms, $status, $featured, $property['id']]);
            $propertyId = $property['id'];
            logActivity($pdo, $user['id'], "Modification de l'annonce #$propertyId");
        } else {
            $slug = slugify($title) . '-' . strtolower(substr(md5(uniqid()), 0, 6));
            $insertStmt = $pdo->prepare('INSERT INTO properties (reference, title, slug, description, listing_type, category, city, commune, address, price, surface, bedrooms, bathrooms, status, featured, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            // generateReference() lit un compteur puis l'incrémente en PHP : deux créations
            // simultanées peuvent calculer la même référence. La colonne reference est UNIQUE
            // en base, donc un conflit lève une erreur qu'on rattrape ici pour régénérer et
            // retenter, plutôt que de planter la page (important dès qu'il y a plusieurs
            // administrateurs actifs en même temps).
            $maxAttempts = 5;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $reference = generateReference($pdo);
                try {
                    $insertStmt->execute([$reference, $title, $slug, $description, $listingType, $category, $city, $commune, $address, $price, $surface, $bedrooms, $bathrooms, $status, $featured, $user['id']]);
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' && $attempt < $maxAttempts) {
                        continue;
                    }
                    throw $e;
                }
            }
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
                // L'extension seule ne prouve rien : on vérifie le contenu réel du fichier
                // pour rejeter un fichier malveillant déguisé en image (ex. un script PHP renommé .jpg).
                if (!isRealImageFile($_FILES['images']['tmp_name'][$i])) {
                    $uploadWarnings[] = "« $name » a été ignorée : le fichier n'est pas une image valide.";
                    continue;
                }
                // Filigrane appliqué avant l'envoi (local ou Cloudinary) pour marquer
                // toutes les photos publiées, quel que soit le stockage utilisé.
                applyWatermark($_FILES['images']['tmp_name'][$i]);

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
        redirect('properties');
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
        <a href="properties" class="btn btn-outline">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
