<?php
$pageTitle = 'Annonces';
require_once __DIR__ . '/includes/header.php';

$where = ['1=1'];
$params = [];

if (!empty($_GET['city'])) { $where[] = 'city = ?'; $params[] = $_GET['city']; }
if (!empty($_GET['category']) && array_key_exists($_GET['category'], propertyCategories())) { $where[] = 'category = ?'; $params[] = $_GET['category']; }
if (!empty($_GET['listing_type']) && in_array($_GET['listing_type'], ['vente','location'])) { $where[] = 'listing_type = ?'; $params[] = $_GET['listing_type']; }
if (!empty($_GET['min_price'])) { $where[] = 'price >= ?'; $params[] = (float)$_GET['min_price']; }
if (!empty($_GET['max_price'])) { $where[] = 'price <= ?'; $params[] = (float)$_GET['max_price']; }
if (!empty($_GET['bedrooms'])) { $where[] = 'bedrooms >= ?'; $params[] = (int)$_GET['bedrooms']; }
$where[] = "status = 'disponible'";

$sql = "SELECT p.*, (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image
        FROM properties p WHERE " . implode(' AND ', $where) . " ORDER BY featured DESC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();
?>
<section class="page-hero">
  <div class="container">
    <div class="breadcrumb"><a href="index.php">Accueil</a> / Annonces</div>
    <h1>Toutes les annonces</h1>
  </div>
</section>

<div class="container">
  <div class="listing-layout">
    <aside class="filters-box">
      <h3>Filtrer</h3>
      <form method="get">
        <div class="filter-group">
          <label>Ville</label>
          <select name="city">
            <option value="">Toutes</option>
            <?php foreach (ivoryCoastCities() as $city): ?>
              <option value="<?= e($city) ?>" <?= ($_GET['city'] ?? '') === $city ? 'selected' : '' ?>><?= e($city) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Type de bien</label>
          <select name="category">
            <option value="">Tous</option>
            <?php foreach (propertyCategories() as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= ($_GET['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Transaction</label>
          <select name="listing_type">
            <option value="">Toutes</option>
            <option value="vente" <?= ($_GET['listing_type'] ?? '') === 'vente' ? 'selected' : '' ?>>Vente</option>
            <option value="location" <?= ($_GET['listing_type'] ?? '') === 'location' ? 'selected' : '' ?>>Location</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Budget (FCFA)</label>
          <div class="row">
            <input type="number" name="min_price" placeholder="Min" value="<?= e($_GET['min_price'] ?? '') ?>">
            <input type="number" name="max_price" placeholder="Max" value="<?= e($_GET['max_price'] ?? '') ?>">
          </div>
        </div>
        <div class="filter-group">
          <label>Chambres min.</label>
          <select name="bedrooms">
            <option value="">Indifférent</option>
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <option value="<?= $i ?>" <?= ($_GET['bedrooms'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?>+</option>
            <?php endfor; ?>
          </select>
        </div>
        <button type="submit">Appliquer les filtres</button>
      </form>
    </aside>

    <div>
      <div class="results-meta"><?= count($properties) ?> bien(s) trouvé(s)</div>
      <?php if (empty($properties)): ?>
        <div class="empty-state">
          <div class="icon">🔍</div>
          <p>Aucune annonce ne correspond à ces critères. Essayez d'élargir votre recherche.</p>
        </div>
      <?php else: ?>
        <div class="property-grid" data-reveal-group>
          <?php foreach ($properties as $p): ?>
            <?php include __DIR__ . '/includes/property_card.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
