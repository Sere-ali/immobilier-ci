<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();

$isSuper = isSuperAdmin();
$scopeSql = $isSuper ? '' : 'WHERE created_by = ' . (int)$user['id'];

$totalMine = (int)$pdo->query("SELECT COUNT(*) n FROM properties $scopeSql")->fetch()['n'];
$dispoMine = (int)$pdo->query("SELECT COUNT(*) n FROM properties " . ($scopeSql ? $scopeSql . " AND status='disponible'" : "WHERE status='disponible'"))->fetch()['n'];
$soldMine  = (int)$pdo->query("SELECT COUNT(*) n FROM properties " . ($scopeSql ? $scopeSql . " AND status IN ('vendu','loue')" : "WHERE status IN ('vendu','loue')"))->fetch()['n'];

// Cloisonnement : un admin ne voit que les messages liés à ses propres annonces
// (les messages généraux, non liés à une annonce, sont réservés au Super Admin).
if ($isSuper) {
    $newMsg = (int)$pdo->query("SELECT COUNT(*) n FROM messages WHERE status='nouveau'")->fetch()['n'];
    $recentMessages = $pdo->query("SELECT m.*, p.title AS property_title FROM messages m LEFT JOIN properties p ON p.id = m.property_id ORDER BY m.created_at DESC LIMIT 5")->fetchAll();
} else {
    $stmtMsg = $pdo->prepare("SELECT COUNT(*) n FROM messages m JOIN properties p ON p.id = m.property_id WHERE p.created_by = ? AND m.status = 'nouveau'");
    $stmtMsg->execute([$user['id']]);
    $newMsg = (int)$stmtMsg->fetch()['n'];

    $stmtRecent = $pdo->prepare("SELECT m.*, p.title AS property_title FROM messages m JOIN properties p ON p.id = m.property_id WHERE p.created_by = ? ORDER BY m.created_at DESC LIMIT 5");
    $stmtRecent->execute([$user['id']]);
    $recentMessages = $stmtRecent->fetchAll();
}

$recentProperties = $pdo->query("SELECT p.*, (SELECT image_path FROM property_images WHERE property_id=p.id ORDER BY is_primary DESC LIMIT 1) image
                                  FROM properties p " . $scopeSql . " ORDER BY created_at DESC LIMIT 5")->fetchAll();

$pageTitle = 'Tableau de bord';
$pageSubtitle = 'Bienvenue, ' . $user['full_name'];
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="kpi-grid">
  <div class="kpi-card"><div class="label">Mes annonces</div><div class="value"><?= $totalMine ?></div></div>
  <div class="kpi-card"><div class="label">Disponibles</div><div class="value"><?= $dispoMine ?></div></div>
  <div class="kpi-card"><div class="label">Vendues / Louées</div><div class="value"><?= $soldMine ?></div></div>
  <div class="kpi-card"><div class="label">Messages non lus</div><div class="value"><?= $newMsg ?></div></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Dernières annonces</h2>
    <a href="property_form.php" class="btn btn-accent btn-sm">+ Nouvelle annonce</a>
  </div>
  <div class="panel-body" style="padding:0">
    <?php if (empty($recentProperties)): ?>
      <div class="empty-state"><div class="icon">🏠</div><p>Aucune annonce pour le moment.</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th></th><th>Titre</th><th>Ville</th><th>Prix</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recentProperties as $p): ?>
        <tr>
          <td><div class="thumb-cell" style="background-image:url('<?= e($p['image'] ? imageUrl($p['image'], '../') : 'https://placehold.co/100x80/E8DCC8/0F3D3E') ?>')"></div></td>
          <td><?= e($p['title']) ?><br><span class="mono" style="font-size:.72rem;color:var(--ink-soft)"><?= e($p['reference']) ?></span></td>
          <td><?= e($p['city']) ?></td>
          <td class="mono"><?= formatPrice($p['price']) ?></td>
          <td><span class="badge badge-<?= $p['status']==='disponible'?'success':($p['status']==='reserve'?'warning':'neutral') ?>"><?= propertyStatuses()[$p['status']] ?></span></td>
          <td class="actions-cell"><a href="property_form.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Derniers messages</h2><a href="messages.php" class="btn btn-outline btn-sm">Voir tout</a></div>
  <div class="panel-body" style="padding:0">
    <?php if (empty($recentMessages)): ?>
      <div class="empty-state"><div class="icon">✉️</div><p>Aucun message reçu.</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>De</th><th>Concerne</th><th>Reçu le</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recentMessages as $m): ?>
        <tr>
          <td><?= e($m['full_name']) ?><br><span style="font-size:.78rem;color:var(--ink-soft)"><?= e($m['email']) ?></span></td>
          <td><?= e($m['property_title'] ?? 'Message général') ?></td>
          <td class="mono" style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
          <td><span class="badge badge-<?= $m['status']==='nouveau'?'warning':($m['status']==='lu'?'neutral':'success') ?>"><?= ucfirst($m['status']) ?></span></td>
          <td class="actions-cell"><a href="messages.php?id=<?= $m['id'] ?>" class="btn btn-outline btn-sm">Voir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
