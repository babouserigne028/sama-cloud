# Format `datacloud.yaml` — Spécification

> Sprint 1 · Responsable : Babou · À valider avec Souleymane (moteur) et Moussa (API)
> Version du format : **1** · Version du document : 0.1 — 4 octobre 2026

## 1. À quoi sert ce fichier

`datacloud.yaml` décrit **ce dont le projet d'un client a besoin pour tourner** sur
SamaCloud : ses services, leur framework, leur taille, leurs commandes, leurs bases de
données et les variables attendues. Il se place à la racine du dépôt Git du client.

- **Facultatif pour le client** : sans fichier, le moteur détecte le framework et
  génère une configuration par défaut, que le client peut ensuite consulter et modifier.
- **Rédigé par l'IA** : l'outil MCP `analyser_depot` propose ce fichier ; le développeur
  le valide.
- **Partagé en template** : un template de la communauté est un `datacloud.yaml` plus
  un README. Le fichier ne contient **jamais de secret**.
- **Base du devis** : les tailles déclarées servent à l'API pour calculer le prix.

Le client décrit **ce qu'il veut** ; le moteur construit l'image avec **Nixpacks** (ou avec
le `Dockerfile` du projet s'il en fournit un), puis lance le conteneur dans un réseau
isolé, et **Traefik** lui donne son sous-domaine en HTTPS. Le client n'écrit jamais de
Docker, sauf s'il fournit son propre `Dockerfile`.

```
datacloud.yaml  →  moteur (Souleymane)  →  Nixpacks + docker run + Traefik (HTTPS)
```

## 2. Exemple minimal

Deux lignes suffisent ; tout le reste prend sa valeur par défaut.

```yaml
version: 1
services:
  web:
    framework: laravel
```

## 3. Structure complète

```yaml
version: 1                      # obligatoire — version du format
nom: mon-blog                   # facultatif — défaut : nom du dépôt

services:                       # obligatoire — 1 à 5 services
  <nom-du-service>:
    type: web                   # web | worker — défaut : web
    framework: laravel          # voir § 5 — obligatoire sauf si `dockerfile`
    version: "8.3"              # version du langage — défaut : selon le framework
    taille: petite              # petite | moyenne | grande — défaut : petite
    dossier: .                  # dossier du service dans le dépôt — défaut : racine
    dockerfile: Dockerfile      # facultatif — si présent, le moteur l'utilise tel quel
    build: <commande>           # facultatif — défaut : selon le framework
    release: <commande>         # facultatif — exécutée une fois avant la mise en ligne
    demarrage: <commande>       # facultatif — défaut : selon le framework
    port: 8080                  # web uniquement — défaut : selon le framework
    sortie: dist                # framework `static` uniquement — dossier publié
    sante:                      # web uniquement
      chemin: /                 # défaut : /
      delai: 60                 # secondes pour devenir sain — défaut : 60, max 300
    bases: [principale]         # bases auxquelles le service se connecte
    variables:                  # variables d'environnement attendues (voir § 6)
      APP_ENV: production
      MAIL_PASSWORD: { secret: true, description: "Mot de passe SMTP" }
      APP_KEY: { generer: laravel-app-key }

bases:                          # facultatif — 0 à 2 bases
  <nom-de-la-base>:
    type: postgresql            # seul type au lancement
    version: "16"               # défaut : 16
    taille: petite              # petite | moyenne — défaut : petite
```

## 4. Champs

### 4.1 Racine

| Champ | Obligatoire | Type | Défaut | Règles |
|---|---|---|---|---|
| `version` | oui | entier | — | Vaut `1`. Permet de faire évoluer le format sans casser les anciens fichiers |
| `nom` | non | texte | nom du dépôt | Minuscules, chiffres, tirets ; 3 à 30 caractères ; commence par une lettre. Sert au sous-domaine |
| `services` | oui | objet | — | 1 à 5 services. Au moins un service `web` |
| `bases` | non | objet | aucune | 0 à 2 bases, dans la limite des quotas du compte |

### 4.2 Service

Le **nom du service** (la clé, ex. `web`, `api`, `worker`) suit la même règle que `nom`.

| Champ | Obligatoire | Type | Défaut | Règles |
|---|---|---|---|---|
| `type` | non | `web` \| `worker` | `web` | `web` : reçoit du trafic HTTP et un sous-domaine. `worker` : tâche de fond (file d'attente, planificateur), sans port ni URL |
| `framework` | oui, sauf si `dockerfile` | énuméré | — | Voir § 5 |
| `version` | non | texte | selon le framework | Version du **langage** (PHP, Node, Python…), entre guillemets |
| `taille` | non | `petite` \| `moyenne` \| `grande` | `petite` | Voir § 7. `grande` est une option payante de la formule personnelle |
| `dossier` | non | chemin relatif | `.` | Pour un dépôt contenant plusieurs applications (ex. `frontend/`). Ne peut pas sortir du dépôt (`..` interdit) |
| `dockerfile` | non | chemin relatif | — | Si présent, le moteur construit avec ce Dockerfile et ignore `build` ; `framework` devient informatif |
| `build` | non | commande | selon le framework | Exécutée pendant la construction de l'image. 500 caractères max |
| `release` | non | commande | aucune | Exécutée **une fois** par déploiement, après le build et avant la mise en ligne (ex. migrations). Si elle échoue, l'ancienne version reste en ligne |
| `demarrage` | non | commande | selon le framework | Commande qui lance l'application |
| `port` | non | entier 1024–65535 | selon le framework | Port sur lequel l'application écoute dans le conteneur. Interdit pour un `worker` |
| `sortie` | non | chemin relatif | selon le framework | Uniquement pour `static` : dossier produit par le build et publié |
| `sante.chemin` | non | chemin URL | `/` | Le moteur attend une réponse HTTP 2xx ou 3xx avant de mettre en ligne. Interdit pour un `worker` |
| `sante.delai` | non | entier | `60` | Secondes accordées pour devenir sain ; 300 max. Au-delà : déploiement échoué, ancienne version conservée |
| `bases` | non | liste de noms | aucune | Chaque nom doit exister dans `bases` à la racine |
| `variables` | non | objet | aucune | Voir § 6 |

### 4.3 Base de données

| Champ | Obligatoire | Type | Défaut | Règles |
|---|---|---|---|---|
| `type` | oui | `postgresql` | — | Seul type pris en charge au lancement |
| `version` | non | texte | `"16"` | Versions proposées par le catalogue |
| `taille` | non | `petite` \| `moyenne` | `petite` | Voir § 7 |

Une base n'est **jamais exposée sur internet** : seuls les services du même projet,
sur le réseau isolé du projet, peuvent s'y connecter.

## 5. Frameworks pris en charge

Les valeurs par défaut sont une **proposition à confirmer par Souleymane** selon ce
que le moteur sait construire (base : la détection automatique de Nixpacks).

| `framework` | Statut | Langage, version par défaut | `build` par défaut | `demarrage` par défaut | `port` | `sante.chemin` |
|---|---|---|---|---|---|---|
| `laravel` | prioritaire | PHP `8.3` | `composer install --no-dev --optimize-autoloader` (+ `npm ci && npm run build` si `package.json`) | serveur PHP du moteur | `8080` | `/up` |
| `node` | prioritaire | Node `22` | `npm ci && npm run build --if-present` | `npm start` | `3000` | `/` |
| `static` | prioritaire | Node `22` (build) | `npm ci && npm run build` | serveur statique du moteur | `8080` | `/` |
| `django` | bêta | Python `3.12` | `pip install -r requirements.txt` | `gunicorn <projet>.wsgi` | `8000` | `/` |
| `fastapi` | bêta | Python `3.12` | `pip install -r requirements.txt` | `uvicorn main:app --host 0.0.0.0` | `8000` | `/` |
| `flask` | bêta | Python `3.12` | `pip install -r requirements.txt` | `gunicorn app:app` | `8000` | `/` |
| `symfony` | bêta | PHP `8.3` | `composer install --no-dev --optimize-autoloader` | serveur PHP du moteur | `8080` | `/` |
| `php` | bêta | PHP `8.3` | `composer install --no-dev` si `composer.json` | serveur PHP du moteur | `8080` | `/` |

- **prioritaire** : testé pour la démo et le jury.
- **bêta** : accepté, étiqueté « bêta » dans l'interface.
- `static` couvre les sites HTML et les applications compilées (Angular, React, Vue) ;
  `sortie` par défaut : `dist` (Angular : `dist/<nom>/browser`, détecté par le moteur).
- Un projet dans un autre langage passe par son propre `dockerfile`.

## 6. Variables d'environnement

Le fichier déclare **quelles** variables le projet attend, jamais la valeur d'un secret.
Chaque entrée de `variables` prend l'une de ces trois formes :

| Forme | Exemple | Signification |
|---|---|---|
| Valeur publique | `APP_ENV: production` | Valeur non sensible, écrite en clair. Partagée telle quelle dans les templates |
| Secret à fournir | `MAIL_PASSWORD: { secret: true, description: "Mot de passe SMTP" }` | Le client saisit la valeur dans le back-office ou via `modifier_variables`. Stockée chiffrée. Le déploiement est bloqué tant qu'elle manque |
| Secret généré | `APP_KEY: { generer: laravel-app-key }` | L'API génère la valeur au premier déploiement et la conserve |

Générateurs disponibles pour `generer` :

| Générateur | Produit |
|---|---|
| `laravel-app-key` | `base64:` + 32 octets aléatoires |
| `hex32` | 32 octets aléatoires en hexadécimal |
| `motdepasse` | 24 caractères alphanumériques |

### Variables injectées automatiquement

Le client ne les déclare pas ; il ne peut pas les redéfinir.

| Variable | Valeur |
|---|---|
| `PORT` | Le `port` du service |
| `SAMACLOUD_URL` | URL publique HTTPS du service (`web` uniquement) |
| `DATABASE_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Connexion à la **première** base listée dans `bases` du service |
| `<NOM>_DATABASE_URL` | Connexion aux bases suivantes (`<NOM>` = nom de la base en majuscules, tirets remplacés par `_`) |

### Règles de sécurité appliquées par l'API

- Refus d'une **valeur en clair** pour une variable dont le nom contient `KEY`,
  `SECRET`, `PASSWORD`, `PASS`, `TOKEN`, `PRIVATE` ou `CREDENTIAL` : le client doit
  utiliser `{ secret: true }` ou `{ generer: … }`.
- Refus d'une valeur en clair qui **ressemble à un secret** (clé `sk_…`, jeton JWT, URL
  contenant un mot de passe, longue chaîne aléatoire).
- Refus des noms réservés : `PORT`, `SAMACLOUD_*`, `DATABASE_URL`, `DB_*` sur un service
  relié à une base.
- Nom de variable : majuscules, chiffres et `_`, commence par une lettre.

## 7. Tailles

Les ressources exactes et les prix sont fixés par le **catalogue** (API, console
d'administration). Valeurs proposées, **à confirmer avec Amadou et la grille Systalink** :

| Service | Processeur | Mémoire |
|---|---|---|
| `petite` | 0,5 vCPU | 512 Mo |
| `moyenne` | 1 vCPU | 1 Go |
| `grande` (option) | 2 vCPU | 2 Go |

| Base | Processeur | Mémoire | Disque |
|---|---|---|---|
| `petite` | 0,5 vCPU | 512 Mo | 2 Go |
| `moyenne` | 1 vCPU | 1 Go | 10 Go |

La mémoire est un **plafond strict** par conteneur (`--memory`) : un dépassement
redémarre le conteneur et déclenche l'alerte « mémoire saturée ».

## 8. Adresses publiques

Validé avec Souleymane (domaine `samacloud.piitech.dev`, DNS générique `*.samacloud.piitech.dev`) :

- Projet avec un seul service `web` : `https://<nom>.samacloud.piitech.dev`
- Projet avec plusieurs services `web` : `https://<service>-<nom>.samacloud.piitech.dev`
- En cas de conflit de nom entre comptes, l'API ajoute un suffixe court
  (`mon-blog-4f2a`).
- Noms réservés, refusés pour un projet ou un service : `www`, `api`, `mcp`, `pay`,
  `admin`.

## 9. Où le fichier est lu, et lequel gagne

| Situation | Configuration utilisée |
|---|---|
| Plan créé via le MCP ou le back-office avec un contenu `datacloud_yaml` | Le contenu envoyé au plan |
| Déploiement depuis Git, fichier présent dans le dépôt | Le fichier du dépôt |
| Déploiement depuis Git, pas de fichier | La dernière configuration enregistrée du projet ; à défaut, la détection automatique |
| Déploiement depuis un template | Le `datacloud.yaml` du template, appliqué au dépôt du client |

L'API **enregistre la configuration effectivement utilisée** à chaque déploiement :
on sait toujours avec quoi une version a été construite, et l'IA peut la relire pour
diagnostiquer un échec.

Changer une **taille** ou ajouter un **service ou une base** modifie le prix : l'API
exige un nouveau devis (plan/apply) avant d'appliquer la configuration.

## 10. Validation par l'API

L'API valide chaque fichier avec le schéma `datacloud.schema.json` puis applique les
règles métier. Les erreurs sont renvoyées en français, avec le chemin du champ fautif,
pour que l'IA puisse les corriger :

```json
{
  "code": "datacloud_invalide",
  "message": "Le fichier datacloud.yaml contient 2 erreurs.",
  "details": [
    { "champ": "services.web.taille", "message": "Valeur « enorme » inconnue. Valeurs possibles : petite, moyenne, grande." },
    { "champ": "services.web.variables.STRIPE_KEY", "message": "Valeur en clair interdite pour un secret. Utilisez { secret: true }." }
  ]
}
```

Règles :

1. `version` vaut `1`.
2. **Champs inconnus refusés** (une faute de frappe comme `taile` est signalée au lieu
   d'être ignorée en silence).
3. Au moins un service `web` ; 5 services et 2 bases au maximum, et dans la limite des
   quotas du compte.
4. Chaque service a un `framework` ou un `dockerfile`.
5. `port`, `sante` interdits sur un `worker` ; `sortie` réservé à `static`.
6. Chaque nom de `bases` d'un service existe à la racine.
7. Règles de sécurité des variables (§ 6).
8. Chemins relatifs sans `..` ni chemin absolu.
9. `grande` refusée si l'option n'est pas disponible pour la formule du compte.

### Autocomplétion dans l'éditeur

En ajoutant cette ligne en tête du fichier, VS Code et Cursor (extension YAML de Red
Hat) proposent l'autocomplétion et soulignent les erreurs avant tout déploiement :

```yaml
# yaml-language-server: $schema=https://samacloud.piitech.dev/schemas/datacloud.schema.json
```

## 11. Exemples

### 11.1 Laravel + PostgreSQL + file d'attente

```yaml
version: 1
nom: mon-blog

services:
  web:
    framework: laravel
    version: "8.3"
    taille: petite
    release: php artisan migrate --force
    sante:
      chemin: /up
    bases: [principale]
    variables:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: { generer: laravel-app-key }
      MAIL_PASSWORD: { secret: true, description: "Mot de passe SMTP" }

  file-attente:
    type: worker
    framework: laravel
    demarrage: php artisan queue:work --tries=3
    bases: [principale]
    variables:
      APP_KEY: { generer: laravel-app-key }

bases:
  principale:
    type: postgresql
    taille: petite
```

> Les deux services déclarent `APP_KEY` avec le même générateur : l'API génère **une
> seule** valeur par projet et par nom de variable, partagée entre les services.

### 11.2 API Node (Express) + PostgreSQL

```yaml
version: 1
nom: api-boutique

services:
  api:
    framework: node
    version: "22"
    taille: moyenne
    build: npm ci && npm run build
    release: npx prisma migrate deploy
    demarrage: node dist/server.js
    port: 3000
    sante:
      chemin: /health
    bases: [principale]
    variables:
      NODE_ENV: production
      JWT_SECRET: { generer: hex32 }

bases:
  principale:
    type: postgresql
    taille: petite
```

### 11.3 Monorepo : front Angular + API Laravel

```yaml
version: 1
nom: gestion-stock

services:
  front:
    framework: static
    dossier: frontend
    build: npm ci && npm run build
    sortie: dist/gestion-stock/browser

  api:
    framework: laravel
    dossier: backend
    release: php artisan migrate --force
    bases: [principale]
    variables:
      APP_KEY: { generer: laravel-app-key }

bases:
  principale:
    type: postgresql
```

Adresses : `https://front-gestion-stock.samacloud.piitech.dev` et `https://api-gestion-stock.samacloud.piitech.dev`.

### 11.4 Projet avec son propre Dockerfile

```yaml
version: 1
nom: service-go

services:
  web:
    dockerfile: Dockerfile
    port: 8080
    sante:
      chemin: /healthz
```

## 12. Questions ouvertes

- **Souleymane** : les commandes et ports par défaut du § 5 correspondent-ils au moteur ?
  Quel serveur PHP (FrankenPHP, Nginx + PHP-FPM…) ? Format exact des sous-domaines (§ 8) ?
- **Moussa** : stockage de la configuration par déploiement (§ 9) ; validation avec
  `datacloud.schema.json` (bibliothèque `opis/json-schema` ou équivalent).
- **Amadou** : ressources et prix des tailles (§ 7) ; `grande` disponible en option ?
- **Thioro** : affichage d'un `datacloud.yaml` dans le catalogue de templates (résumé
  lisible : services, bases, coût estimé).
- **Tous** : limite de 5 services et 2 bases par projet pour la formule personnelle ?
