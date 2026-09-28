<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();
$isSuper = isSuperAdmin();

if (isset($_GET['id'])) {
    $mid = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT m.*, p.title AS property_title, p.slug, p.created_by AS property_owner FROM messages m LEFT JOIN properties p ON p.id = m.property_id WHERE m.id = ?');
    $stmt->execute([$mid]);
    $message = $stmt->fetch();

    // Cloisonnement : un admin ne voit que les messages liés à ses propres annonces.
    // Les messages généraux (non liés à une annonce) sont réservés au Super Admin.
    $canView = $message && ($isSuper || (int)($message['property_owner'] ?? 0) === (int)$user['id']);

    if (!$canView) { flash('error', 'Message introuvable.'); redirect('messages'); }

    if (isset($_GET['delete'])) {
        // Seul le Super Admin peut supprimer un message reçu.
        if (!$isSuper) {
            flash('error', "Seul le Super Administrateur peut supprimer un message.");
            redirect('messages?id=' . $mid);
        }
        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$mid]);
        logActivity($pdo, $user['id'], "Suppression du message #$mid de {$message['full_name']}");
        flash('success', 'Message supprimé.');
        redirect('messages');
    }

    if (isset($_GET['mark'])) {
        $pdo->prepare('UPDATE messages SET status = ? WHERE id = ?')->execute([$_GET['mark'], $mid]);
        redirect('messages?id=' . $mid);
    }
    if ($message['status'] === 'nouveau') {
        $pdo->prepare("UPDATE messages SET status = 'lu' WHERE id = ?")->execute([$mid]);
        $message['status'] = 'lu';
    }

    $pageTitle = 'Message reçu';
    require_once __DIR__ . '/../includes/admin_header.php';
    ?>
    <div class="panel">
      <div class="panel-head">
        <h2><?= e($message['subject'] ?: 'Message') ?></h2>
        <a href="messages" class="btn btn-outline btn-sm">← Retour à la liste</a>
      </div>
      <div class="panel-body">
        <p><strong>De :</strong> <?= e($message['full_name']) ?> — <a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a><?= $message['phone'] ? ' — WhatsApp : ' . e(formatIvoryCoastPhoneDisplay($message['phone'])) : '' ?></p>
        <p><strong>Reçu le :</strong> <?= date('d/m/Y à H:i', strtotime($message['created_at'])) ?></p>
        <?php if ($message['property_title']): ?>
          <p><strong>Concerne :</strong> <a href="../annonce?slug=<?= e($message['slug']) ?>" target="_blank"><?= e($message['property_title']) ?></a></p>
        <?php endif; ?>
        <div class="panel" style="margin-top:16px;background:#faf9f6">
          <div class="panel-body"><?= nl2br(e($message['message'])) ?></div>
        </div>
        <div class="form-actions">
          <?php if (!empty($message['phone']) && isValidWhatsappNumber($message['phone'])): ?>
            <?php $replyMsg = "Bonjour {$message['full_name']}, merci pour votre message" . ($message['property_title'] ? " concernant « {$message['property_title']} »" : '') . ". "; ?>
            <a href="<?= e(waLink($message['phone'], $replyMsg)) ?>" target="_blank" rel="noopener" class="btn-whatsapp" style="width:auto">
              <span class="btn-whatsapp-icon">💬</span> Répondre sur WhatsApp
            </a>
          <?php endif; ?>
          <a href="mailto:<?= e($message['email']) ?>" class="btn btn-outline">Répondre par e-mail</a>
          <a href="?id=<?= $mid ?>&mark=traite" class="btn btn-outline">Marquer comme traité</a>
          <?php if ($isSuper): ?>
            <a href="?id=<?= $mid ?>&delete=1" class="btn btn-danger" data-confirm="Supprimer définitivement ce message ? Cette action est irréversible.">🗑️ Supprimer</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/admin_footer.php';
    exit;
}

$filterStatus = $_GET['status'] ?? '';
$where = [];
$params = [];
if (!$isSuper) { $where[] = 'p.created_by = ?'; $params[] = $user['id']; }
if ($filterStatus && in_array($filterStatus, ['nouveau','lu','traite'])) { $where[] = 'm.status = ?'; $params[] = $filterStatus; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) n FROM messages m LEFT JOIN properties p ON p.id = m.property_id$whereSql");
$countStmt->execute($params);
$pagination = paginate((int)$countStmt->fetch()['n'], 20);

$sql = "SELECT m.*, p.title AS property_title FROM messages m LEFT JOIN properties p ON p.id = m.property_id$whereSql
        ORDER BY m.created_at DESC LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$pageTitle = 'Messages';
$pageSubtitle = $pagination['totalItems'] . ' message(s)' . (!$isSuper ? ' — liés à vos annonces' : '');
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="pill-filters">
  <a href="messages" class="<?= $filterStatus==='' ? 'active' : '' ?>">Tous</a>
  <a href="?status=nouveau" class="<?= $filterStatus==='nouveau' ? 'active' : '' ?>">Nouveaux</a>
  <a href="?status=lu" class="<?= $filterStatus==='lu' ? 'active' : '' ?>">Lus</a>
  <a href="?status=traite" class="<?= $filterStatus==='traite' ? 'active' : '' ?>">Traités</a>
</div>

<div class="panel">
  <div class="panel-body" style="padding:0">
    <?php if (empty($messages)): ?>
      <div class="empty-state"><div class="icon">✉️</div><p>Aucun message.</p></div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>De</th><th>Concerne</th><th>Message</th><th>Reçu le</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($messages as $m): ?>
        <tr>
          <td><?= e($m['full_name']) ?><br><span style="font-size:.78rem;color:var(--ink-soft)"><?= e($m['email']) ?></span></td>
          <td><?= e($m['property_title'] ?? 'Message général') ?></td>
          <td style="max-width:260px;font-size:.85rem;color:var(--ink-soft)"><?= e(truncateText($m['message'], 60)) ?></td>
          <td class="mono" style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
          <td><span class="badge badge-<?= $m['status']==='nouveau'?'warning':($m['status']==='lu'?'neutral':'success') ?>"><?= ucfirst($m['status']) ?></span></td>
          <td class="actions-cell">
            <a href="?id=<?= $m['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
            <?php if (!empty($m['phone']) && isValidWhatsappNumber($m['phone'])): ?>
              <a href="<?= e(waLink($m['phone'], "Bonjour {$m['full_name']}, merci pour votre message.")) ?>" target="_blank" rel="noopener" class="btn-whatsapp" style="padding:6px 10px;font-size:.78rem">💬</a>
            <?php endif; ?>
            <?php if ($isSuper): ?>
              <a href="?id=<?= $m['id'] ?>&delete=1" class="btn btn-danger btn-sm" data-confirm="Supprimer définitivement ce message de <?= e($m['full_name']) ?> ?">🗑️</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="padding:16px 22px"><?= paginationLinks($pagination) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
