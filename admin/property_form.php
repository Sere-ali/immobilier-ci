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
    $errors[] = "Les fichiers envoyés sont trop volumineux au total (taille maximale : " . ini_get('post_max_size') . "). Réduisez le nombre ou le poids des photos (ou de la vidéo) et réessayez.";
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
            // Une annonce créée par un administrateur simple reste masquée du site
            // public tant que le super admin ne l'a pas validée. Le super admin,
            // lui, publie directement (il est déjà l'autorité de validation).
            $approvalStatus = $isSuper ? 'approuve' : 'en_attente';
            $insertStmt = $pdo->prepare('INSERT INTO properties (reference, title, slug, description, listing_type, category, city, commune, address, price, surface, bedrooms, bathrooms, status, approval_status, featured, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            // generateReference() lit un compteur puis l'incrémente en PHP : deux créations
            // simultanées peuvent calculer la même référence. La colonne reference est UNIQUE
            // en base, donc un conflit lève une erreur qu'on rattrape ici pour régénérer et
            // retenter, plutôt que de planter la page (important dès qu'il y a plusieurs
            // administrateurs actifs en même temps).
            $maxAttempts = 5;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $reference = generateReference($pdo);
                try {
                    $insertStmt->execute([$reference, $title, $slug, $description, $listingType, $category, $city, $commune, $address, $price, $surface, $bedrooms, $bathrooms, $status, $approvalStatus, $featured, $user['id']]);
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

        // --- Vidéo de l'annonce (optionnelle, une seule : une nouvelle remplace l'ancienne) ---
        $oldVideo = $property['video_path'] ?? null;

        if ($property && !empty($_POST['remove_video']) && $oldVideo) {
            try {
                $pdo->prepare('UPDATE properties SET video_path = NULL WHERE id = ?')->execute([$propertyId]);
                deleteLocalUpload($oldVideo);
                $oldVideo = null;
            } catch (Exception $e) {
                error_log('Suppression vidéo annonce #' . $propertyId . ' : ' . $e->getMessage());
                $uploadWarnings[] = "La vidéo n'a pas pu être supprimée.";
            }
        }

        $videoErr = $_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($videoErr !== UPLOAD_ERR_NO_FILE) {
            $videoName = $_FILES['video']['name'] ?? 'vidéo';
            $videoTmp = $_FILES['video']['tmp_name'] ?? '';
            $videoExt = strtolower(pathinfo($videoName, PATHINFO_EXTENSION));
            $storedVideo = null;

            if ($videoErr !== UPLOAD_ERR_OK) {
                $reason = [
                    UPLOAD_ERR_INI_SIZE   => 'trop volumineuse (50 Mo maximum)',
                    UPLOAD_ERR_FORM_SIZE  => 'trop volumineuse (50 Mo maximum)',
                    UPLOAD_ERR_PARTIAL    => 'envoi interrompu, merci de réessayer',
                    UPLOAD_ERR_NO_TMP_DIR => 'erreur serveur (dossier temporaire manquant)',
                    UPLOAD_ERR_CANT_WRITE => 'erreur serveur (écriture disque impossible)',
                    UPLOAD_ERR_EXTENSION  => 'bloquée par une extension serveur',
                ][$videoErr] ?? 'erreur inconnue';
                $uploadWarnings[] = "La vidéo « $videoName » n'a pas pu être envoyée : $reason.";
            } elseif (!in_array($videoExt, allowedVideoExtensions(), true)) {
                $uploadWarnings[] = "La vidéo « $videoName » a été ignorée : format non autorisé (mp4, mov, webm uniquement).";
            } elseif (($_FILES['video']['size'] ?? 0) > MAX_VIDEO_BYTES) {
                $uploadWarnings[] = "La vidéo « $videoName » a été ignorée : elle dépasse 50 Mo.";
            } elseif (!isRealVideoFile($videoTmp)) {
                $uploadWarnings[] = "La vidéo « $videoName » a été ignorée : le fichier n'est pas une vidéo valide.";
            } else {
                if (cloudinaryConfigured()) {
                    $storedVideo = uploadVideoToCloudinary($videoTmp, $videoName);
                    if ($storedVideo === null) {
                        $uploadWarnings[] = "La vidéo « $videoName » n'a pas pu être envoyée vers le stockage externe (Cloudinary). Vérifiez les identifiants configurés ou la taille du fichier.";
                    }
                } else {
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
                    $newVideoName = uniqid('vid_', true) . '.' . $videoExt;
                    if (move_uploaded_file($videoTmp, UPLOAD_DIR . $newVideoName)) {
                        $storedVideo = $newVideoName;
                    } else {
                        $uploadWarnings[] = "La vidéo « $videoName » n'a pas pu être enregistrée sur le serveur.";
                    }
                }
            }

            if ($storedVideo !== null) {
                try {
                    $pdo->prepare('UPDATE properties SET video_path = ? WHERE id = ?')->execute([$storedVideo, $propertyId]);
                    deleteLocalUpload($oldVideo); // l'ancienne vidéo est remplacée
                } catch (Exception $e) {
                    error_log('Enregistrement vidéo annonce #' . $propertyId . ' : ' . $e->getMessage());
                    deleteLocalUpload($storedVideo);
                    $uploadWarnings[] = "La vidéo a été envoyée mais n'a pas pu être rattachée à l'annonce.";
                }
            }
        }

        $pendingNote = (!$property && !$isSuper) ? ' Elle sera visible sur le site public dès qu\'un super administrateur l\'aura validée.' : '';
        if (!empty($uploadWarnings)) {
            flash('warning', ($property ? 'Annonce mise à jour, mais : ' : 'Annonce créée, mais : ') . implode(' ', $uploadWarnings) . $pendingNote);
        } else {
            flash('success', ($property ? 'Annonce mise à jour avec succès.' : 'Annonce créée avec succès.') . $pendingNote);
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
    <?php if ($property && $property['approval_status'] === 'en_attente'): ?>
      <div class="alert alert-warning">⏳ En attente de validation par un super administrateur — pas encore visible sur le site public.</div>
    <?php elseif ($property && $property['approval_status'] === 'rejete'): ?>
      <div class="alert alert-error">🚫 Cette annonce a été rejetée par un super administrateur et n'est pas visible sur le site public. Modifiez-la puis contactez un super admin pour une nouvelle validation.</div>
    <?php endif; ?>
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
          <div class="hint">Formats acceptés : JPG, PNG, WEBP — Photos : 15 Mo maximum chacune.</div>
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
        <div class="field full">
          <label>Vidéo de l'annonce <?= !empty($property['video_path']) ? '(remplacer la vidéo)' : '(optionnel)' ?></label>
          <!-- Le vrai champ fichier est masqué ; le bouton ci-dessous (label) l'ouvre. -->
          <input type="file" id="video" name="video" accept="video/mp4,video/quicktime,video/webm,.mp4,.m4v,.mov,.webm" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none" tabindex="-1">
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <label for="video" class="btn btn-outline" style="cursor:pointer;margin:0">🎥 <?= !empty($property['video_path']) ? 'Choisir une autre vidéo' : 'Ajouter une vidéo' ?></label>
            <span id="video-name" class="hint" style="margin:0">Aucune vidéo sélectionnée</span>
          </div>
          <div class="hint">Formats acceptés : MP4, MOV, WEBM — 50 Mo maximum. Une seule vidéo par annonce.</div>
          <video id="video-preview" controls playsinline style="display:none;margin-top:10px;width:100%;max-width:420px;border-radius:10px;background:#000"></video>
          <?php if (!empty($property['video_path'])): ?>
            <div class="hint" style="margin-top:10px">Vidéo actuelle :</div>
            <video src="<?= e(imageUrl($property['video_path'], '../')) ?>" controls playsinline preload="metadata" style="margin-top:6px;width:100%;max-width:420px;border-radius:10px;background:#000"></video>
            <label class="checkbox-row" style="margin-top:8px"><input type="checkbox" name="remove_video" value="1"> Supprimer la vidéo actuelle</label>
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
