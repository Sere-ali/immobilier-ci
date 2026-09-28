<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pdo = getPDO();
$user = currentUser();

if (isset($_GET['toggle'], $_GET['id'])) {
    $targetId = (int)$_GET['id'];
    if ($targetId !== (int)$user['id']) {
        $stmt = $pdo->prepare('SELECT status, role FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $row = $stmt->fetch();
        if ($row && $row['role'] === 'superadmin' && empty($user['is_principal'])) {
            // Un super admin ne peut pas désactiver le compte d'un autre super
            // admin, sauf le super administrateur principal qui garde le contrôle
            // total sur les super admins qu'il a nommés.
            flash('error', "Vous ne pouvez pas modifier le compte d'un autre super administrateur.");
        } elseif ($row) {
            $newStatus = $row['status'] === 'actif' ? 'inactif' : 'actif';
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $targetId]);
            logActivity($pdo, $user['id'], "Statut de l'utilisateur #$targetId changé en $newStatus");
            flash('success', 'Statut du compte mis à jour.');
        }
    } else {
        flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
    }
    redirect('users');
}

$list = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM properties WHERE created_by = u.id) AS nb_properties
                      FROM users u ORDER BY FIELD(role,'superadmin','admin'), created_at DESC")->fetchAll();

$pageTitle = 'Administrateurs';
$pageSubtitle = count($list) . ' compte(s)';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel">
  <div class="panel-head">
    <h2>Comptes Admin &amp; Super Admin</h2>
    <a href="user_form" class="btn btn-accent btn-sm">+ Nouvel administrateur</a>
  </div>
  <div class="panel-body" style="padding:0">
    <table class="data-table">
      <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Annonces</th><th>Statut</th><th>Dernière connexion</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($list as $u): ?>
        <tr>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="badge badge-<?= $u['role']==='superadmin'?'lagune':'neutral' ?>"><?= $u['role']==='superadmin'?'Super Admin':'Admin' ?></span><?= !empty($u['is_principal']) ? ' <span class="badge badge-lagune" title="Super administrateur principal">★ Principal</span>' : '' ?></td>
          <td class="mono"><?= (int)$u['nb_properties'] ?></td>
          <td><span class="badge badge-<?= $u['status']==='actif'?'success':'danger' ?>"><?= ucfirst($u['status']) ?></span></td>
          <td class="mono" style="font-size:.8rem"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?></td>
          <td class="actions-cell" style="justify-content:flex-end">
            <?php if ($u['role'] === 'superadmin' && $u['id'] != $user['id'] && empty($user['is_principal'])): ?>
              <span class="badge badge-neutral" title="Un super admin ne peut pas modifier le compte d'un autre super admin">🔒 Protégé</span>
            <?php else: ?>
              <a href="user_form?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Modifier</a>
              <?php if ($u['id'] != $user['id']): ?>
                <a href="?toggle=1&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm" data-confirm="<?= $u['status']==='actif' ? 'Désactiver' : 'Activer' ?> ce compte ?"><?= $u['status']==='actif' ? 'Désactiver' : 'Activer' ?></a>
                <a href="user_delete?id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Supprimer définitivement ce compte ? Ses annonces resteront visibles mais sans propriétaire.">Suppr.</a>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
