<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pdo = getPDO();
$user = currentUser();

$totalProperties = (int)$pdo->query("SELECT COUNT(*) n FROM properties")->fetch()['n'];
$totalUsers = (int)$pdo->query("SELECT COUNT(*) n FROM users")->fetch()['n'];
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) n FROM users WHERE role='admin'")->fetch()['n'];
$totalMessages = (int)$pdo->query("SELECT COUNT(*) n FROM messages WHERE status='nouveau'")->fetch()['n'];
$totalValue = (float)$pdo->query("SELECT COALESCE(SUM(price),0) n FROM properties WHERE status='disponible'")->fetch()['n'];
$totalPending = (int)$pdo->query("SELECT COUNT(*) n FROM properties WHERE approval_status='en_attente'")->fetch()['n'];

$byAdmin = $pdo->query("SELECT u.full_name, u.email, u.status, COUNT(p.id) AS nb
                         FROM users u LEFT JOIN properties p ON p.created_by = u.id
                         WHERE u.role != 'superadmin'
                         GROUP BY u.id ORDER BY nb DESC")->fetchAll();

$byCity = $pdo->query("SELECT city, COUNT(*) nb FROM properties GROUP BY city ORDER BY nb DESC LIMIT 6")->fetchAll();

$pageTitle = 'Vue globale';
$pageSubtitle = 'Tableau de bord Super Administrateur';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="kpi-grid">
  <div class="kpi-card"><div class="label">Total annonces</div><div class="value"><?= $totalProperties ?></div></div>
  <a href="<?= e($root) ?>admin/properties?approval=en_attente" class="kpi-card" style="display:block;<?= $totalPending > 0 ? 'border-color:var(--warning)' : '' ?>">
    <div class="label">En attente de validation</div>
    <div class="value" style="<?= $totalPending > 0 ? 'color:var(--warning)' : '' ?>"><?= $totalPending ?></div>
  </a>
  <div class="kpi-card"><div class="label">Administrateurs</div><div class="value"><?= $totalAdmins ?></div></div>
  <div class="kpi-card"><div class="label">Messages non lus</div><div class="value"><?= $totalMessages ?></div></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Valeur totale du portefeuille disponible</h2></div>
  <div class="panel-body">
    <div style="font-family:'IBM Plex Mono',monospace;font-size:2rem;font-weight:600;color:var(--lagune)"><?= formatPrice($totalValue) ?></div>
    <p style="color:var(--ink-soft);font-size:.88rem;margin-top:6px">Cumul des prix des biens actuellement disponibles à la vente ou à la location.</p>
  </div>
</div>

<?php if (!empty($user['is_principal'])): ?>
<?php /* Réservé au super administrateur principal : les autres super admins ne voient pas ce bloc. */ ?>
<div class="panel" style="border-color:var(--gold-light)">
  <div class="panel-head"><h2>💰 Bénéfice du super administrateur</h2></div>
  <div class="panel-body">
    <div style="font-family:'IBM Plex Mono',monospace;font-size:2rem;font-weight:600;color:var(--laterite)"><?= formatPrice($totalValue * 0.02) ?></div>
    <p style="color:var(--ink-soft);font-size:.88rem;margin-top:6px">2 % de la valeur totale du portefeuille disponible (<?= formatPrice($totalValue) ?>). Visible uniquement par le super administrateur principal.</p>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h2>Performance par administrateur</h2>
    <a href="users" class="btn btn-outline btn-sm">Gérer les administrateurs</a>
  </div>
  <div class="panel-body" style="padding:0">
    <?php if (empty($byAdmin)): ?>
      <div class="empty-state"><div class="icon">🛡️</div><p>Aucun administrateur créé pour le moment.</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Nom</th><th>E-mail</th><th>Statut</th><th>Annonces publiées</th></tr></thead>
      <tbody>
      <?php foreach ($byAdmin as $a): ?>
        <tr>
          <td><?= e($a['full_name']) ?></td>
          <td><?= e($a['email']) ?></td>
          <td><span class="badge badge-<?= $a['status']==='actif'?'success':'danger' ?>"><?= ucfirst($a['status']) ?></span></td>
          <td class="mono"><?= $a['nb'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Répartition par ville</h2></div>
  <div class="panel-body" style="padding:0">
    <table class="data-table">
      <thead><tr><th>Ville</th><th>Nombre d'annonces</th></tr></thead>
      <tbody>
      <?php foreach ($byCity as $c): ?>
        <tr><td><?= e($c['city']) ?></td><td class="mono"><?= $c['nb'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
