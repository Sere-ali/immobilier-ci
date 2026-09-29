/**
 * Service worker — Immobilier CI (PWA)
 *
 * Principe de prudence : on ne met JAMAIS en cache les pages HTML (annonces,
 * accueil, admin...). Elles passent toujours par le réseau en priorité, pour
 * ne jamais risquer d'afficher un contenu périmé (prix, disponibilité...) —
 * on a eu assez de mal avec des annonces qui ne s'affichaient pas pour ne
 * pas aller créer sciemment un nouveau cas de "contenu figé" côté PWA.
 * Seuls les fichiers statiques versionnés (CSS/JS/images) sont mis en cache,
 * avec une revalidation en arrière-plan.
 */
const CACHE_NAME = 'immobilier-ci-static-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [
  OFFLINE_URL,
  '/assets/img/logo.png',
  '/assets/img/icons/icon-192.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // laisse passer les ressources externes (polices Google, etc.)

  // Navigation (pages HTML) : toujours le réseau en priorité. Si hors ligne,
  // on retombe sur la page de repli plutôt que sur une page mise en cache.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // Fichiers statiques (CSS, JS, images, icônes) : cache d'abord pour la
  // rapidité, avec mise à jour silencieuse en arrière-plan (les fichiers
  // CSS/JS du site sont déjà versionnés par date de modification, donc un
  // changement produit une nouvelle URL et n'est jamais bloqué par le cache).
  if (/\/assets\//.test(url.pathname)) {
    event.respondWith(
      caches.open(CACHE_NAME).then((cache) =>
        cache.match(req).then((cached) => {
          const network = fetch(req).then((res) => {
            if (res && res.ok) cache.put(req, res.clone());
            return res;
          }).catch(() => cached);
          return cached || network;
        })
      )
    );
  }
});
