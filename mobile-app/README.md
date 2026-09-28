# Immobilier CI — Application mobile (Android / iOS)

Coquille [Capacitor](https://capacitorjs.com) qui embarque le site déjà en
ligne (`https://immobilier-ci.onrender.com`) dans une vraie application
native. Aucune duplication de code : l'app affiche le site public **et**
l'espace Admin/Super Admin exactement comme dans un navigateur, avec icône,
écran de démarrage et barre de statut personnalisés.

Le projet est prêt (icônes, splash screen, config Android déjà générés).
Il ne reste que la compilation finale, qui doit se faire **sur votre
ordinateur** (ou via un service de build en ligne) car elle nécessite le SDK
Android / Xcode, non disponibles dans cet environnement cloud restreint.

## 1. Prérequis sur votre ordinateur

- [Node.js](https://nodejs.org) 18 ou plus
- **Pour Android** : [Android Studio](https://developer.android.com/studio) (installe automatiquement le SDK)
- **Pour iOS** : un Mac avec [Xcode](https://apps.apple.com/app/xcode/id497799835) (obligatoire — Apple ne permet pas de compiler une app iOS ailleurs que sur macOS)

## 2. Récupérer le projet

```bash
git clone https://github.com/Sere-ali/immobilier-ci.git
cd immobilier-ci/mobile-app
npm install
```

## 3. Compiler pour Android

```bash
npx cap sync android
npx cap open android
```

Android Studio s'ouvre sur le projet. Menu **Build → Generate Signed Bundle
/ APK** :
1. Choisissez **Android App Bundle** (`.aab`, format demandé par le Play
   Store)
2. Créez une **clé de signature** (keystore) — gardez précieusement ce
   fichier et son mot de passe, vous en aurez besoin pour **toutes** les
   futures mises à jour de l'app (impossible à récupérer si perdu)
3. Lancez le build → vous obtenez `app-release.aab`

## 4. Compiler pour iOS (sur Mac uniquement)

```bash
npx cap add ios      # première fois seulement
npx cap sync ios
npx cap open ios
```

Xcode s'ouvre. Renseignez votre compte développeur Apple dans **Signing &
Capabilities**, puis **Product → Archive** pour générer le fichier à envoyer
sur App Store Connect.

## 5. Publier sur les stores

| | Google Play Store | Apple App Store |
|---|---|---|
| Compte développeur | [play.google.com/console](https://play.google.com/console) — 25 $ (une fois) | [developer.apple.com](https://developer.apple.com) — 99 $/an |
| Fichier à envoyer | `app-release.aab` (étape 3) | Archive Xcode (étape 4) |
| Obligatoire | Fiche descriptive, captures d'écran, icône (déjà générée ici), **politique de confidentialité** (URL publique) | Idem + délai de revue (1 à 3 jours en général) |
| Délai de revue | Quelques heures à 2-3 jours | 1 à 3 jours en général |

### Politique de confidentialité
Les deux stores l'exigent. Elle est déjà en ligne, donnez cette URL dans les
formulaires de soumission :
`https://immobilier-ci.onrender.com/confidentialite`

## 6. Mettre à jour l'app plus tard

Comme l'app charge le site en ligne (`server.url` dans
`capacitor.config.json`), **la plupart des changements du site (nouvelles
annonces, nouvelles pages, corrections) n'ont besoin d'aucune nouvelle
publication sur les stores** : ils apparaissent automatiquement dans l'app
dès que le site est mis à jour. Une nouvelle soumission aux stores n'est
nécessaire que si vous changez l'icône, le nom de l'app, ou la config
Capacitor elle-même.

## Fichiers du projet

- `capacitor.config.json` — configuration (nom de l'app, URL du site, couleurs)
- `resources/` — images sources (icône, splash) utilisées pour générer tous les formats
- `www/` — page de secours affichée uniquement si l'app ne peut pas joindre internet
- `android/` — projet Android natif généré par Capacitor (ouvert dans Android Studio)
