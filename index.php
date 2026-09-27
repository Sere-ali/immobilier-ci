<?php
$pageTitle = 'Accueil';
require_once __DIR__ . '/includes/header.php';

$featured = $pdo->query("SELECT p.*, (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image
                          FROM properties p WHERE featured = 1 ORDER BY created_at DESC LIMIT 6")->fetchAll();

$totalProperties = (int)$pdo->query("SELECT COUNT(*) AS n FROM properties")->fetch()['n'];
$totalSold = (int)$pdo->query("SELECT COUNT(*) AS n FROM properties WHERE status IN ('vendu','loue')")->fetch()['n'];
$totalCities = (int)$pdo->query("SELECT COUNT(DISTINCT city) AS n FROM properties")->fetch()['n'];

$categories = [
    'villa'       => ['🏡', 'Villas'],
    'appartement' => ['🏢', 'Appartements'],
    'terrain'     => ['🌍', 'Terrains'],
    'bureau'      => ['💼', 'Bureaux'],
    'magasin'     => ['🏬', 'Magasins'],
    'immeuble'    => ['🏗️', 'Immeubles'],
];
?>
<section class="hero">
  <div class="skyline"></div>
  <div class="container hero-inner">
    <span class="eyebrow">Plateforme immobilière ivoirienne</span>
    <h1>Trouvez votre bien <em>en toute confiance</em>, partout en Côte d'Ivoire</h1>
    <p>Villas, appartements, terrains et bureaux à Abidjan et dans les grandes villes du pays, à la vente comme à la location, sans intermédiaire caché.</p>

    <form action="annonces" method="get" class="search-bar">
      <select name="city">
        <option value="">Toutes les villes</option>
        <?php foreach (ivoryCoastCities() as $city): ?>
          <option value="<?= e($city) ?>"><?= e($city) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="category">
        <option value="">Type de bien</option>
        <?php foreach (propertyCategories() as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="listing_type">
        <option value="">Vente ou location</option>
        <option value="vente">Vente</option>
        <option value="location">Location</option>
      </select>
      <button type="submit">Rechercher</button>
    </form>
  </div>
</section>

<div class="cities-strip">
  <div class="container">
    <?php foreach (['Abidjan','Bouaké','Yamoussoukro','San-Pédro','Grand-Bassam','Bingerville','Korhogo','Assinie'] as $city): ?>
      <a href="annonces?city=<?= urlencode($city) ?>"><?= e($city) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="stats-band">
  <div class="container">
    <div class="stat-item"><div class="num mono" data-count-to="<?= (int)$totalProperties ?>" data-suffix="+">0+</div><div class="label">Biens référencés</div></div>
    <div class="stat-item"><div class="num mono" data-count-to="<?= (int)$totalSold ?>">0</div><div class="label">Transactions conclues</div></div>
    <div class="stat-item"><div class="num mono" data-count-to="<?= (int)$totalCities ?>">0</div><div class="label">Villes couvertes</div></div>
    <div class="stat-item"><div class="num mono" data-count-to="100" data-suffix="%">0%</div><div class="label">Annonces vérifiées</div></div>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="eyebrow-label">Catégories</span>
        <h2>Parcourir par type de bien</h2>
      </div>
    </div>
    <div class="category-grid" data-reveal-group>
      <?php foreach ($categories as $key => [$icon, $label]): ?>
        <a href="annonces?category=<?= e($key) ?>" class="category-card">
          <div class="icon"><?= $icon ?></div>
          <div class="name"><?= e($label) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="eyebrow-label">Sélection</span>
        <h2>Biens à la une</h2>
        <p>Une sélection de biens vérifiés par notre équipe, disponibles à la vente ou à la location.</p>
      </div>
      <a href="annonces" class="link-all">Voir toutes les annonces →</a>
    </div>

    <?php if (empty($featured)): ?>
      <div class="empty-state">
        <div class="icon">🏠</div>
        <p>Aucun bien à la une pour le moment. Consultez toutes les annonces disponibles.</p>
      </div>
    <?php else: ?>
      <div class="property-grid" data-reveal-group>
        <?php foreach ($featured as $p): ?>
          <?php include __DIR__ . '/includes/property_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="cta-band" data-reveal>
    <h2>Vous voulez vendre ou louer un bien ?</h2>
    <p>Contactez notre équipe pour référencer votre propriété auprès de milliers d'acheteurs et locataires potentiels.</p>
    <a href="contact" class="btn btn-primary">Nous contacter</a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
