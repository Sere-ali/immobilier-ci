<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pdo = getPDO();

$logs = $pdo->query("SELECT l.*, u.full_name, u.email FROM activity_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 200")->fetchAll();

$pageTitle = "Journal d'activité";
$pageSubtitle = 'Les 200 dernières actions';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel">
  <div class="panel-body" style="padding:0">
    <?php if (empty($logs)): ?>
      <div class="empty-state"><div class="icon">📜</div><p>Aucune activité enregistrée pour le moment.</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Utilisateur</th><th>Action</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= e($log['full_name'] ?? 'Utilisateur supprimé') ?><br><span style="font-size:.78rem;color:var(--ink-soft)"><?= e($log['email'] ?? '') ?></span></td>
          <td><?= e($log['action']) ?></td>
          <td class="mono" style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
