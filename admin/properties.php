<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();
$isSuper = isSuperAdmin();

if (isset($_GET['toggle_status'], $_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $prop = $stmt->fetch();
    if ($prop && ($isSuper || $prop['created_by'] == $user['id'])) {
        $map = ['disponible' => 'reserve', 'reserve' => ($prop['listing_type']==='location'?'loue':'vendu'), 'vendu' => 'disponible', 'loue' => 'disponible'];
        $newStatus = $map[$prop['status']] ?? 'disponible';
        $pdo->prepare('UPDATE properties SET status = ? WHERE id = ?')->execute([$newStatus, $prop['id']]);
        flash('success', 'Statut mis à jour.');
    }
    redirect('properties');
}

// Validation des annonces : réservé au super admin. Une annonce approuvée
// devient visible sur le site public ; rejetée, elle reste masquée et
// l'administrateur qui l'a créée voit pourquoi sur sa fiche.
if (isset($_GET['approve'], $_GET['id']) && $isSuper) {
    $stmt = $pdo->prepare('SELECT id, title FROM properties WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    if ($prop = $stmt->fetch()) {
        $pdo->prepare("UPDATE properties SET approval_status = 'approuve' WHERE id = ?")->execute([$prop['id']]);
        logActivity($pdo, $user['id'], "Validation de l'annonce #{$prop['id']} ({$prop['title']})");
        flash('success', 'Annonce approuvée : elle est maintenant visible sur le site public.');
    }
    redirect('properties');
}
if (isset($_GET['reject'], $_GET['id']) && $isSuper) {
    $stmt = $pdo->prepare('SELECT id, title FROM properties WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    if ($prop = $stmt->fetch()) {
        $pdo->prepare("UPDATE properties SET approval_status = 'rejete' WHERE id = ?")->execute([$prop['id']]);
        logActivity($pdo, $user['id'], "Rejet de l'annonce #{$prop['id']} ({$prop['title']})");
        flash('success', 'Annonce rejetée : elle reste masquée du site public.');
    }
    redirect('properties');
}

$filterStatus = $_GET['status'] ?? '';
$filterApproval = $_GET['approval'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if (!$isSuper) { $where[] = 'created_by = ?'; $params[] = $user['id']; }
if ($filterStatus && array_key_exists($filterStatus, propertyStatuses())) { $where[] = 'p.status = ?'; $params[] = $filterStatus; }
if ($filterApproval && array_key_exists($filterApproval, approvalStatuses())) { $where[] = 'p.approval_status = ?'; $params[] = $filterApproval; }
if ($search !== '') { $where[] = '(title LIKE ? OR reference LIKE ? OR city LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) n FROM properties p$whereSql");
$countStmt->execute($params);
$pagination = paginate((int)$countStmt->fetch()['n'], 20);

$sql = "SELECT p.*, (SELECT image_path FROM property_images WHERE property_id=p.id ORDER BY is_primary DESC LIMIT 1) image, u.full_name AS owner_name
        FROM properties p LEFT JOIN users u ON u.id = p.created_by$whereSql
        ORDER BY created_at DESC LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$pageTitle = 'Annonces';
$pageSubtitle = $pagination['totalItems'] . ' bien(s)';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel">
  <div class="panel-head">
    <h2>Gestion des annonces</h2>
    <a href="property_form" class="btn btn-accent btn-sm">+ Nouvelle annonce</a>
  </div>
  <div class="panel-body">
    <form method="get" style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap">
      <input type="text" name="q" placeholder="Rechercher un titre, une référence, une ville..." value="<?= e($search) ?>" style="flex:1;min-width:220px;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
      <select name="status" style="padding:9px 12px;border:1px solid var(--border);border-radius:8px">
        <option value="">Tous statuts</option>
        <?php foreach (propertyStatuses() as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="approval" style="padding:9px 12px;border:1px solid var(--border);border-radius:8px">
        <option value="">Toute validation</option>
        <?php foreach (approvalStatuses() as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $filterApproval === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit">Filtrer</button>
    </form>

    <?php if (empty($list)): ?>
      <div class="empty-state"><div class="icon">🏠</div><p>Aucune annonce trouvée.</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th></th><th>Titre / Réf.</th><th>Ville</th><th>Type</th><th>Prix</th>
        <?php if ($isSuper): ?><th>Créé par</th><?php endif; ?>
        <th>Statut</th><th>Validation</th><th>Vues</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($list as $p): ?>
        <tr>
          <td><div class="thumb-cell" style="background-image:url('<?= e($p['image'] ? imageUrl($p['image'], '../') : 'https://placehold.co/100x80/E8DCC8/0F3D3E') ?>')"></div></td>
          <td><?= e($p['title']) ?><br><span class="mono" style="font-size:.72rem;color:var(--ink-soft)"><?= e($p['reference']) ?></span></td>
          <td><?= e($p['city']) ?></td>
          <td><span class="badge badge-<?= $p['listing_type']==='vente'?'lagune':'laterite' ?>"><?= ucfirst($p['listing_type']) ?></span></td>
          <td class="mono"><?= formatPrice($p['price']) ?></td>
          <?php if ($isSuper): ?><td style="font-size:.82rem"><?= e($p['owner_name'] ?? '—') ?></td><?php endif; ?>
          <td>
            <a href="?toggle_status=1&id=<?= $p['id'] ?>" class="badge badge-<?= $p['status']==='disponible'?'success':($p['status']==='reserve'?'warning':'neutral') ?>" title="Cliquer pour changer le statut" style="cursor:pointer">
              <?= propertyStatuses()[$p['status']] ?>
            </a>
          </td>
          <td>
            <span class="badge badge-<?= $p['approval_status']==='approuve'?'success':($p['approval_status']==='en_attente'?'warning':'neutral') ?>">
              <?= e(approvalStatuses()[$p['approval_status']] ?? $p['approval_status']) ?>
            </span>
          </td>
          <td class="mono"><?= (int)$p['views'] ?></td>
          <td class="actions-cell" style="justify-content:flex-end">
            <?php if ($isSuper && $p['approval_status'] !== 'approuve'): ?>
              <a href="?approve=1&id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" data-confirm="Approuver cette annonce et la publier sur le site public ?">Approuver</a>
            <?php endif; ?>
            <?php if ($isSuper && $p['approval_status'] !== 'rejete'): ?>
              <a href="?reject=1&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" data-confirm="Rejeter cette annonce ? Elle restera masquée du site public.">Rejeter</a>
            <?php endif; ?>
            <a href="../annonce?slug=<?= e($p['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">Voir</a>
            <a href="property_form?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Modifier</a>
            <a href="property_delete?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Supprimer définitivement cette annonce ?">Suppr.</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?= paginationLinks($pagination) ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
