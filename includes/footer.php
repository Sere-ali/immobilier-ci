<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <h4 style="text-transform:none;letter-spacing:0;padding:0"><img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img"></h4>
        <p style="font-size:.88rem;max-width:280px;margin-top:10px"><?= e(getSetting($pdo, 'site_about', '')) ?></p>
      </div>
      <div>
        <h4>Navigation</h4>
        <a href="index">Accueil</a>
        <a href="annonces">Toutes les annonces</a>
        <a href="contact">Contact</a>
        <a href="confidentialite">Politique de confidentialité</a>
      </div>
      <div>
        <h4>Catégories</h4>
        <a href="annonces?category=villa">Villas</a>
        <a href="annonces?category=appartement">Appartements</a>
        <a href="annonces?category=terrain">Terrains</a>
      </div>
      <div>
        <h4>Contact</h4>
        <a href="tel:<?= e(getSetting($pdo, 'site_phone', '')) ?>"><?= e(getSetting($pdo, 'site_phone', '')) ?></a>
        <a href="mailto:<?= e(getSetting($pdo, 'site_email', '')) ?>"><?= e(getSetting($pdo, 'site_email', '')) ?></a>
        <a href="#"><?= e(getSetting($pdo, 'site_address', '')) ?></a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> Immobilier CI. Tous droits réservés.</span>
      <span>Fait avec soin en Côte d'Ivoire 🇨🇮</span>
    </div>
  </div>
</footer>
<script src="assets/js/main.js?v=<?= assetVersion('assets/js/main.js') ?>" defer></script>
</body>
</html>
