# Immobilier CI — Plateforme de gestion immobilière (Côte d'Ivoire)

Site complet en **HTML / CSS / JavaScript / PHP** avec base de données **MySQL**, comprenant :
- un site public (recherche, annonces, fiches détaillées, contact) — **responsive** (mobile, tablette, desktop) ;
- un espace **Admin** (gestion de ses propres annonces, messages reçus) ;
- un espace **Super Admin** (vue globale, gestion des comptes admin, paramètres du site, journal d'activité).

---

## 1. Installation en local (WampServer)

1. Copiez le dossier `immobilier-ci` dans `C:\wamp64\www\`
2. Démarrez WampServer (icône verte)
3. Créez une base `immobilier_ci` dans phpMyAdmin (`http://localhost/phpmyadmin`)
4. `config/db.php` fonctionne tel quel avec les valeurs par défaut de Wamp (`root` / mot de passe vide)
5. Allez sur `http://localhost/immobilier-ci/install.php` pour créer les tables et votre compte Super Admin
6. Connectez-vous sur `http://localhost/immobilier-ci/login.php`
7. **Supprimez `install.php`** une fois l'installation terminée

## 2. Déploiement sur Render

Render ne propose pas de runtime PHP natif ni de base MySQL managée officielle : ce projet est donc préconfiguré pour tourner **via Docker**, avec MySQL hébergé comme second service privé sur Render.

### Fichiers de déploiement inclus
| Fichier | Rôle |
|---|---|
| `Dockerfile` | Construit l'image de l'application (PHP 8.2 + Apache + extensions PDO MySQL) |
| `docker/entrypoint.sh` | Adapte Apache au port dynamique `$PORT` fourni par Render au démarrage |
| `mysql.Dockerfile` | Image MySQL 8 qui importe automatiquement `database.sql` au premier démarrage |
| `render.yaml` | Blueprint Render : crée et relie les deux services en un clic |
| `.dockerignore` | Exclut les fichiers inutiles de l'image Docker |

### ⚠️ Important : la base de données n'est pas gratuite sur Render

Render interdit le plan `free` pour les **Private Services** (le type utilisé pour héberger MySQL). Ce projet configure donc la base sur le plan `starter` (environ 7 $/mois), tandis que le **service web reste gratuit**. Une carte bancaire doit être enregistrée sur votre compte Render avant de déployer un Private Service, même sur son plan le moins cher.

### Option A — Déploiement en un clic avec le Blueprint (recommandé)

1. Poussez ce projet sur un dépôt GitHub/GitLab
2. Sur Render : ajoutez d'abord un moyen de paiement dans **Account Settings → Billing** (obligatoire pour tout Private Service, même sur le plan le moins cher)
3. **New** → **Blueprint** → connectez votre dépôt
4. Donnez un nom au Blueprint, choisissez la branche (`main`), laissez le chemin par défaut (`render.yaml` à la racine)
5. Render détecte `render.yaml` et affiche un écran de revue avec les deux services à créer :
   - `immobilier-ci` (service web, plan gratuit)
   - `immobilier-ci-db` (base MySQL privée, plan `starter`, disque persistant)
   - Si cet écran affiche une erreur au lieu de la liste des services, corrigez-la avant de continuer — le bouton de déploiement n'apparaît pas tant qu'il reste une erreur
6. Cliquez sur **Deploy Blueprint** (le bouton peut aussi s'appeler **Apply** selon la version de l'interface) — Render construit les deux images et les relie automatiquement (le mot de passe MySQL est généré et injecté tout seul dans le service web)
7. Une fois déployé, ouvrez `https://votre-service.onrender.com/install.php` pour créer votre compte Super Admin (les tables existent déjà, importées automatiquement par `mysql.Dockerfile`)
8. **Supprimez `install.php`**, committez et repoussez pour que le changement soit redéployé

### Option B — Configuration manuelle (sans render.yaml)

1. **Créer la base** : New → Private Service → Docker → pointez sur `mysql.Dockerfile` → ajoutez un disque persistant monté sur `/var/lib/mysql` → définissez `MYSQL_ROOT_PASSWORD` et `MYSQL_DATABASE=immobilier_ci`
2. **Créer le service web** : New → Web Service → Docker → pointez sur `Dockerfile`
3. Dans les variables d'environnement du service web, ajoutez :
   - `DB_HOST` = nom interne du service MySQL (ex: `immobilier-ci-db`)
   - `DB_PORT` = `3306`
   - `DB_NAME` = `immobilier_ci`
   - `DB_USER` = `root`
   - `DB_PASS` = le mot de passe défini pour le service MySQL
   - `SITE_URL` = l'URL publique de votre service (ex: `https://immobilier-ci.onrender.com`)
4. Déployez, puis lancez `install.php` comme dans l'option A

### Pourquoi ces variables d'environnement ?

`config/db.php` lit désormais **en priorité les variables d'environnement** (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SITE_URL`), avec les valeurs de Wamp en repli si elles ne sont pas définies. Résultat : **le même code tourne sans modification en local (Wamp) et sur Render** — seule la configuration change, jamais le code.

### Limites à connaître sur le plan gratuit Render
- Le service web gratuit **s'endort après 15 minutes d'inactivité** et met quelques secondes à se réveiller au prochain visiteur
- Le disque persistant du plan gratuit est limité en taille — suffisant pour démarrer, à surveiller si le volume de photos grossit
- Pour un usage commercial réel, un plan payant (pas de mise en veille, sauvegardes automatiques) est recommandé

## 3. Photos persistantes (Cloudinary)

Sur Render, le plan gratuit ne fournit pas de disque persistant pour le service web : les photos uploadées localement disparaîtraient à chaque redémarrage du conteneur (mise en veille après inactivité, redéploiement...).

**Solution intégrée** : si les variables d'environnement suivantes sont définies, toutes les nouvelles photos sont automatiquement envoyées vers **Cloudinary** (stockage gratuit jusqu'à 25 Go, ne disparaît jamais) au lieu du disque local :

| Variable | Où la trouver |
|---|---|
| `CLOUDINARY_CLOUD_NAME` | Tableau de bord Cloudinary → "Cloud name" |
| `CLOUDINARY_API_KEY` | Tableau de bord Cloudinary → "API Key" |
| `CLOUDINARY_API_SECRET` | Tableau de bord Cloudinary → "API Secret" |

**Pour les obtenir** : créez un compte gratuit sur `cloudinary.com` (aucune carte bancaire requise pour le plan gratuit), les trois valeurs sont affichées directement sur le tableau de bord après inscription.

**Sans ces variables** (par exemple en local sur WampServer), l'application continue d'utiliser le stockage local classique dans `uploads/properties/`, qui fonctionne très bien puisque Wamp ne redémarre pas le disque entre deux visites.

## 4. Contact WhatsApp automatique

Chaque demande sur une annonce (ou message général) exige désormais un **numéro WhatsApp** (format international, ex: `+225 07 00 00 00 00`). Après l'envoi :
- Le **visiteur** reçoit un bouton "Continuer sur WhatsApp" qui ouvre directement une conversation avec l'administrateur propriétaire de l'annonce (ou le numéro général du site à défaut), message déjà rédigé.
- L'**administrateur** dispose d'un bouton "Répondre sur WhatsApp" dans le détail de chaque message, qui ouvre directement une conversation avec le visiteur.

**Configuration nécessaire :**
- Chaque administrateur peut renseigner son propre numéro WhatsApp dans **Mon profil**.
- Le Super Admin peut définir un numéro WhatsApp général de secours dans **Paramètres du site**, utilisé quand l'administrateur d'une annonce n'a pas encore renseigné le sien.

⚠️ Il ne s'agit pas d'envoi automatique et silencieux de message (ce qui nécessiterait l'API WhatsApp Business, payante et soumise à validation) : chaque partie doit cliquer une fois sur son bouton pour ouvrir WhatsApp avec le message déjà prêt à envoyer — la conversation démarre ensuite normalement dans WhatsApp.

## 5. Rôles et permissions

| Fonction | Admin | Super Admin |
|---|:---:|:---:|
| Créer / modifier / supprimer ses annonces | ✅ | ✅ |
| Voir / gérer les annonces de tous les admins | ❌ | ✅ |
| Consulter et traiter les messages reçus | ✅ | ✅ |
| Créer / désactiver / supprimer des comptes admin | ❌ | ✅ |
| Modifier les paramètres du site | ❌ | ✅ |
| Consulter le journal d'activité | ❌ | ✅ |

## 6. Responsive

Le site s'adapte à toutes les tailles d'écran :
- **Site public** : menu mobile en tiroir, grilles d'annonces qui passent de 3 à 2 puis 1 colonne, barre de recherche qui s'empile, galerie photo qui réduit son nombre de colonnes
- **Espace Admin/Super Admin** : la barre latérale devient une barre horizontale défilante sur mobile (avec le lien "Voir le site public" et "Déconnexion" toujours visibles), les tableaux de données défilent horizontalement sans casser la mise en page, les formulaires passent en une seule colonne

## 6bis. URLs propres et performance

- **URLs sans `.php`** : `annonces.php` s'affiche désormais `annonces`, `admin/dashboard.php` devient `admin/dashboard`, etc. Géré par le fichier `.htaccess` à la racine (réécriture Apache) — toute ancienne URL en `.php` redirige automatiquement (301) vers sa version propre. Nécessite `mod_rewrite` + `AllowOverride All`, déjà activés dans le `Dockerfile` (`docker/apache-overrides.conf`) ; sous WampServer, `mod_rewrite` est actif par défaut.
- **Temps de chargement** : la police Google Fonts était importée via `@import` dans le CSS, ce qui bloque l'affichage de la page le temps que le navigateur découvre et télécharge cet import en cascade. Elle est maintenant chargée via une balise `<link>` (+ `preconnect`) dans le `<head>`, en parallèle du reste. Compression Gzip et mise en cache navigateur des fichiers statiques activées (`mod_deflate` / `mod_expires`). Les photos des annonces utilisent maintenant de vraies balises `<img loading="lazy">` (au lieu d'images de fond CSS) pour ne charger que les photos visibles à l'écran.
- Le premier chargement après une période d'inactivité peut rester plus lent sur le plan gratuit de Render (le service se met en veille et redémarre à la demande) — ce n'est pas lié au code du site.

## 7. Sécurité déjà en place

- Mots de passe hachés (`password_hash` / `password_verify`)
- Requêtes SQL exclusivement préparées (PDO) — pas d'injection SQL
- Échappement systématique des sorties HTML (`e()` = `htmlspecialchars`)
- Contrôle d'accès par rôle sur chaque page sensible
- Upload d'images limité aux extensions `jpg, jpeg, png, webp`, avec vérification du contenu réel du fichier (`getimagesize()`), pas seulement de son extension
- Toutes les entrées de formulaire sont nettoyées avant stockage (`sanitizeText()`, `sanitizePhoneForStorage()`), pas seulement validées — un endpoint appelé directement (hors formulaire) ne peut pas injecter de donnée brute en base

### À faire avant une mise en production réelle
- Supprimer `install.php` du serveur après usage
- Ajouter une limitation des tentatives de connexion sur `login.php`
- Sauvegardes régulières de la base de données et du dossier `uploads/`

## 7bis. Scalabilité — fondations

Ce projet fonctionne très bien en l'état pour un usage personnel ou un lancement modeste. Avant une commercialisation avec plus de trafic, voici ce qui a déjà été mis en place et ce qui restera à faire.

**Déjà fait :**
- **Pagination** sur les listes qui peuvent grossir sans limite : annonces publiques (`annonces.php`), biens en admin (`admin/properties.php`), messages (`admin/messages.php`), journal d'activité (`superadmin/activity_log.php`). Plus de `SELECT *` ou `LIMIT 200` figé qui ralentirait avec des milliers de lignes.
- **Index de base de données** sur les colonnes utilisées pour filtrer/trier (ville, catégorie, type de transaction, statut, biens en vedette, propriété liée à un message, date des actions du journal). Les requêtes restent rapides même avec beaucoup de données.
- **Correction d'une situation de concurrence** : si deux annonces étaient créées exactement en même temps, elles pouvaient recevoir la même référence. La création réessaie maintenant automatiquement avec une nouvelle référence en cas de collision.
- **Limitation anti-spam** sur les formulaires publics (contact général et contact par annonce) : 5 messages maximum par connexion sur 15 minutes, via une nouvelle table `rate_limits`.

**Hors périmètre pour cette étape (à revoir si le trafic augmente fortement)** :
- Stockage de session partagé entre plusieurs instances du serveur (actuellement en fichiers locaux — ne fonctionne que sur un seul serveur à la fois)
- File d'attente pour les tâches en arrière-plan (envois groupés, traitements longs)
- Couche de cache (Redis ou équivalent) pour réduire la charge sur la base de données

## 8. Structure du projet

```
immobilier-ci/
├── Dockerfile                → image PHP/Apache pour Render
├── mysql.Dockerfile          → image MySQL avec import auto du schéma
├── docker/entrypoint.sh      → gestion du port dynamique Render
├── render.yaml                → blueprint de déploiement Render
├── config/db.php             → connexion base de données (env vars ou Wamp)
├── database.sql               → schéma complet
├── install.php                 → installateur guidé (à retirer après usage)
├── includes/                   → fonctions, auth, gabarits header/footer
├── assets/css/ , assets/js/   → styles et scripts (site public + back-office)
├── uploads/properties/        → photos des annonces
├── index.php, annonces.php, annonce.php, contact.php, login.php
├── admin/                      → back-office Admin
└── superadmin/                 → back-office Super Admin
```
