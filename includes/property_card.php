<?php
/** Fragment attendant une variable $p (ligne properties + colonne image) dans la boucle appelante */
/* Une vraie photo importée est toujours prioritaire ; à défaut (annonce de démonstration,
   ou annonce créée sans photo), on affiche l'illustration de la catégorie du bien plutôt
   qu'une vignette de couleur unie sans repère. */
$hasRealPhoto = !empty($p['image']) && strpos($p['image'], 'placehold.co') === false;
$img = $hasRealPhoto ? imageUrl($p['image']) : categoryPlaceholderImage($p['category']);
?>
<a href="annonce?slug=<?= e($p['slug']) ?>" class="property-card">
  <div class="property-media">
    <img src="<?= e($img) ?>" alt="<?= e($p['title']) ?>" loading="lazy" decoding="async" width="600" height="450">
    <span class="badge <?= $p['listing_type'] === 'location' ? 'location' : '' ?>"><?= $p['listing_type'] === 'location' ? 'À louer' : 'À vendre' ?></span>
    <span class="price-tag"><?= formatPrice($p['price']) ?><?= $p['listing_type'] === 'location' ? ' / mois' : '' ?></span>
  </div>
  <div class="property-body">
    <div class="ref"><?= e($p['reference']) ?></div>
    <h3><?= e($p['title']) ?></h3>
    <div class="location-line">📍 <?= e($p['commune'] ? $p['commune'] . ', ' : '') . e($p['city']) ?></div>
    <div class="property-specs">
      <?php if ($p['bedrooms']): ?><span><strong><?= (int)$p['bedrooms'] ?></strong> ch.</span><?php endif; ?>
      <?php if ($p['bathrooms']): ?><span><strong><?= (int)$p['bathrooms'] ?></strong> sdb</span><?php endif; ?>
      <?php if ($p['surface']): ?><span><strong><?= (int)$p['surface'] ?></strong> m²</span><?php endif; ?>
    </div>
  </div>
</a>
