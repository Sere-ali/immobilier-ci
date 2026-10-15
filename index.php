<?php
$pageTitle = 'Accueil';
require_once __DIR__ . '/includes/header.php';

$featured = $pdo->query("SELECT p.*, (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image
                          FROM properties p WHERE featured = 1 AND approval_status = 'approuve' ORDER BY created_at DESC LIMIT 6")->fetchAll();

// Dernières annonces approuvées (hors "à la une") : l'accueil n'est jamais vide dès qu'il existe une annonce.
$featuredIds = array_map('intval', array_column($featured, 'id'));
$latestSql = "SELECT p.*, (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image
              FROM properties p WHERE approval_status = 'approuve' AND status = 'disponible'";
if ($featuredIds) { $latestSql .= ' AND p.id NOT IN (' . implode(',', $featuredIds) . ')'; }
$latestSql .= ' ORDER BY created_at DESC LIMIT 12';
$latest = $pdo->query($latestSql)->fetchAll();

$totalProperties = (int)$pdo->query("SELECT COUNT(*) AS n FROM properties WHERE approval_status = 'approuve'")->fetch()['n'];
$totalCities = (int)$pdo->query("SELECT COUNT(DISTINCT city) AS n FROM properties WHERE approval_status = 'approuve'")->fetch()['n'];

$categories = [
    'villa'       => 'Villas',
    'appartement' => 'Appartements',
    'terrain'     => 'Terrains',
    'bureau'      => 'Bureaux',
    'magasin'     => 'Magasins',
    'immeuble'    => 'Immeubles',
];
?>
<div class="home-bg" aria-hidden="true"><span style="background-image:url(https://res.cloudinary.com/epxlbn9z/image/upload/w_1600,q_auto,f_auto/v1792066154/immobilier-ci/bg/villa-piscine.jpg)"></span><span style="background-image:url(https://res.cloudinary.com/epxlbn9z/image/upload/w_1600,q_auto,f_auto/v1792066170/immobilier-ci/bg/maison-tropicale.jpg)"></span><span style="background-image:url(https://res.cloudinary.com/epxlbn9z/image/upload/w_1600,q_auto,f_auto/v1790706695/r9tbfmdgcsjhneoec1zp.jpg)"></span><span style="background-image:url(https://res.cloudinary.com/epxlbn9z/image/upload/w_1600,q_auto,f_auto/v1790577148/chaptarif/immo-3.jpg)"></span><span style="background-image:url(https://res.cloudinary.com/epxlbn9z/image/upload/w_1600,q_auto,f_auto/v1790577145/chaptarif/immo-2.jpg)"></span><span style="background-image:url(https://res.cloudinary.com/epxlbn9z/image/upload/w_1600,q_auto,f_auto/v1790577153/chaptarif/immo-4.jpg)"></span></div>
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

<div class="stats-band" data-live="stats">
  <div class="container">
    <div class="stat-item"><div class="num mono" data-count-to="<?= (int)$totalProperties ?>" data-suffix="+">0+</div><div class="label">Biens référencés</div></div>
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
      <?php foreach ($categories as $key => $label): ?>
        <a href="annonces?category=<?= e($key) ?>" class="category-card">
          <div class="icon"><?= categoryIcon($key) ?></div>
          <div class="name"><?= e($label) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0" data-live="featured">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="eyebrow-label">Sélection</span>
        <h2>Biens à la une</h2>
        <p>Une sélection de biens vérifiés par notre équipe, disponibles à la vente ou à la location.</p>
      </div>
      <a href="annonces" class="link-all">Voir toutes les annonces →</a>
    </div>

    <?php if (empty($featured) && !empty($latest)): ?>
      <?php /* rien à la une : la section suivante affiche les annonces */ ?>
    <?php elseif (empty($featured)): ?>
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

<?php if (!empty($latest)): ?>
<section class="section" style="padding-top:0" data-live="latest">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="eyebrow-label">Nouveautés</span>
        <h2>Dernières annonces</h2>
        <p>Les biens les plus récemment publiés.</p>
      </div>
      <a href="annonces" class="link-all">Voir toutes les annonces →</a>
    </div>
    <div class="property-grid" data-reveal-group>
      <?php foreach ($latest as $p): ?>
        <?php include __DIR__ . '/includes/property_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0">
  <div class="cta-band" data-reveal>
    <h2>Vous voulez vendre ou louer un bien ?</h2>
    <p>Contactez notre équipe pour référencer votre propriété auprès de milliers d'acheteurs et locataires potentiels.</p>
    <a href="contact" class="btn btn-primary">Nous contacter</a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
