# API SamaCloud — Ce qui a été reporté

Ce fichier liste tout ce qui a été **volontairement repoussé** pendant le développement de
l'API, pour ne rien perdre de vue. Il est mis à jour à la fin de chaque partie.

- `[ ]` à faire · `[x]` fait (la ligne reste pour garder l'historique)
- **Quand** : la partie ou le sprint où l'élément doit être repris.

## Avancement des parties

| Partie | Contenu | État |
|---|---|---|
| 1 | Socle : Laravel, PostgreSQL, format d'erreur commun, limite de débit, tests d'architecture | Fait |
| 2 | Schéma de base : comptes, projets, ressources, déploiements, commandes, variables, audit | Fait |
| 3 | Authentification Sanctum et jetons IA | Fait |
| 4 | Spécification OpenAPI publiée | Fait |

**Sprint 1 terminé et vérifié** (cartes de Moussa : OpenAPI, Sanctum, schéma PostgreSQL), avec en plus
le rôle administrateur, la correction de la limite de débit et 5 routes du sprint 2 faites en avance.
Prochaine étape : sprint 2 — endpoints projets et déploiements, journal d'audit, quotas par compte.

## Base de données

- [x] **Colonnes des jetons IA** (type, plafond mensuel, préfixe affiché). Fait en partie 3.
- [ ] **Table `plans`** (plan/apply du serveur MCP).
  Pourquoi reporté : Babou demande encore s'il faut une ressource `plans` séparée des `commandes`.
  Recommandation : oui, table séparée (un plan n'engage rien, une commande engage un paiement).
  Quand : sprint 3. À décider : Moussa et Babou.
- [ ] **Catalogue tarifaire** (tailles, processeur, mémoire, prix en FCFA sur 30 jours).
  Pourquoi reporté : les prix sont « à confirmer avec Amadou et la grille Systalink ».
  Quand : sprint 3 (calcul du devis). À décider : Amadou.
- [ ] **Table d'idempotence** (en-tête `Idempotency-Key` sur les écritures).
  Pourquoi reporté : elle va avec les premiers endpoints d'écriture.
  Quand : sprint 2 (endpoints projets et déploiements).
- [ ] **Tables de l'échange** : templates, incidents partagés, étoiles, classement, profils publics.
  Pourquoi reporté : c'est le sprint 4, et le contenu exact d'un template et d'un incident n'est pas fixé.
  Quand : sprint 4.
- [ ] **Colonnes du moteur** sur `project_services` et `project_databases` (état de santé, métriques, conteneur).
  Pourquoi reporté : module de Souleymane ; l'API ne peut que deviner ses besoins.
  À faire : lui montrer le schéma avant qu'il commence.
- [ ] **Colonnes du paiement** sur `orders` (détails du fournisseur, webhooks reçus).
  Pourquoi reporté : module d'Amadou.
  À faire : lui montrer le schéma avant qu'il commence.
- [ ] **Contraintes sur les colonnes d'état** (`status`, `type`, `actor`) au niveau de PostgreSQL.
  Pourquoi reporté : les états vont encore évoluer avec les modules des autres membres ;
  une contrainte figée obligerait chacun à écrire une migration pour ajouter un état.
  Aujourd'hui, c'est l'application qui refuse un état inconnu.
  Quand : revue de sécurité du sprint 4, une fois les états stabilisés.

## Authentification

- [ ] **Application du plafond de dépense mensuel d'un jeton IA.**
  Le plafond est enregistré et renvoyé par `/api/moi`, mais rien ne le fait encore respecter.
  Pourquoi reporté : il se vérifie au moment de `creer_commande`, qui n'existe pas encore.
  Quand : sprint 3 (plan/apply et commandes).
- [ ] **Journal d'audit des actions de compte** (inscription, connexion, création et révocation de jeton).
  Pourquoi reporté : l'enregistrement dans le journal est la carte « Journal d'audit » du sprint 2 ;
  il sera branché sur toutes les actions en une fois.
  Quand : sprint 2.
- [ ] **Vérification de l'adresse e-mail** à l'inscription.
  Pourquoi reporté : le cahier des charges SamaCloud demande seulement « inscription par e-mail et
  mot de passe », et l'envoi d'e-mails dépend du module Alertes d'Amadou.
  Quand : à décider en équipe ; utile contre les faux comptes avant le vote public.
- [ ] **Mot de passe oublié** (lien de réinitialisation par e-mail).
  Pourquoi reporté : même dépendance à l'envoi d'e-mails.
  Quand : après le module Alertes.
- [ ] **Routes d'administration** (rôle administrateur + jeton de session obligatoire).
  Pourquoi reporté : la console d'administration est le sprint 4 (Amadou). Le rôle existe déjà en base.
  Quand : sprint 4.
- [ ] **Restrictions du compte de démonstration** (quotas réduits, paiement simulé).
  Seul le préfixe `sc_test_` des jetons est en place.
  Quand : sprint 2 (quotas) et sprint 3 (paiement simulé, Amadou).
- [ ] **CORS** : autoriser le domaine du back-office Angular à appeler l'API.
  Pourquoi reporté : le domaine n'est pas encore connu (Souleymane).
  Quand : dès que le domaine est choisi.
- [ ] **Gestion des sessions** : lister ses sessions ouvertes, « se déconnecter partout ».
  Pourquoi reporté : confort, pas demandé par le cahier des charges.
- [ ] **Tables `sessions` et `password_reset_tokens`** créées par Laravel et inutilisées
  (l'API n'utilise que des jetons). À supprimer, ou à garder pour le mot de passe oublié.
- [ ] **Nettoyage des jetons expirés** : commande planifiée `sanctum:prune-expired`.
  Pourquoi reporté : dépend du planificateur mis en place avec le moteur (contrôle de santé chaque minute).

## Spécification OpenAPI

- [ ] **Les routes futures ne sont pas dans la spécification.**
  `openapi.json` est généré à partir du code : il décrit les 12 routes qui existent (les 9 que Babou
  attend au sprint 2 sont toutes là), pas les 19 autres prévues dans `outils-mcp.md`. Elles y entreront au fur et à mesure.
  Pourquoi : une spécification écrite à la main pour du code qui n'existe pas finit par mentir.
  Conséquence : Thioro et Babou s'appuient sur `outils-mcp.md` pour les routes à venir, et
  régénèrent leur client à chaque sprint.
- [ ] **Exemples de requêtes et de réponses** dans la spécification.
  Pourquoi reporté : confort de lecture ; les schémas et les codes d'erreur y sont déjà.
  Quand : avant la soumission, pour la démonstration au jury.
- [ ] **Accès à la documentation en ligne** (`/docs/api`) : elle est publique.
  À décider : la laisser publique (argument « proposition d'API pour Datacloud » pour le jury)
  ou la réserver. Recommandation : la laisser publique.
- [ ] **Vérification en intégration continue** : le test « openapi.json est à jour » échoue si quelqu'un
  modifie l'API sans lancer `composer openapi`. À signaler à Amadou pour sa CI.

## Constats de la vérification du sprint 1 (3 octobre)

- [x] **Rôle administrateur inutilisable.** Corrigé : commande `php artisan samacloud:admin <email>`
  (et `--retirer`), et contrôle `admin` sur les routes (rôle administrateur + jeton de session).
  Texte d'origine : Le rôle existe en base, mais il n'y a ni moyen de créer un
  administrateur, ni protection « réservé aux administrateurs ». La carte « Authentification Sanctum »
  cite pourtant les rôles client, administrateur et agent IA.
  À faire : une commande pour créer ou promouvoir un administrateur, et un contrôle de rôle sur les routes.
  Quand : à finir avant de clore le sprint 1 (petit), ou au plus tard avec la console d'administration.
- [x] **Limite de débit contournée par les appels sans jeton sur les routes protégées.** Corrigé : la
  limite est vérifiée avant l'authentification, avec un plafond par adresse IP (300/min) contre
  les essais de jetons au hasard. Texte d'origine :
  Laravel vérifie le jeton avant la limite de débit : un appel sans jeton valide est refusé (401)
  sans être compté. Risque faible (le refus coûte une seule lecture en base), mais à corriger.
  Quand : revue de sécurité, ou plus tôt.
- [ ] **PHP 8.3 et PostgreSQL 16 non vérifiés.** Le README de l'équipe annonce ces versions ; le
  développement s'est fait sous PHP 8.4 et PostgreSQL 17. Le code n'utilise rien de propre à ces
  versions à ma connaissance, mais aucun test n'a tourné dessous. À vérifier dans la CI d'Amadou.
- [ ] **CORS désormais faisable** : le domaine est fixé (`samacloud.piitech.dev`).
- [ ] **Noms réservés** (`www`, `api`, `mcp`, `pay`, `admin`) à refuser pour un projet ou un service :
  règle ajoutée par Babou dans `datacloud-yaml.md`. Quand : sprint 2 (création de projet).
- [ ] **Accents dans le JSON** : ils sortent sous forme échappée (`é`). C'est du JSON valide, lu
  correctement par tous les clients ; seulement moins lisible à l'œil nu.

## Projets et déploiements (routes faites en avance pour Babou)

Faites le 3 octobre, à la clôture du sprint 1 : `GET /api/projets`, `GET /api/projets/{projet}`,
`GET /api/projets/{projet}/deploiements`, `POST /api/projets/{projet}/deploiements`,
`GET /api/operations/{id}`. Ce qui n'est PAS encore fait autour de ces routes :

- [ ] **Branchement du moteur.** `POST …/deploiements` enregistre la demande et émet l'événement
  `App\Application\Deployment\Events\DeploymentRequested`. Rien n'écoute encore cet événement :
  le déploiement reste « en_attente ». À faire par Souleymane : un écouteur qui lance son job.
- [ ] **Création d'un projet.** Aucune route ne crée de projet : il naît d'un plan appliqué puis payé
  (sprint 3). Les tests utilisent des projets créés par les fabriques.
- [ ] **`Idempotency-Key`** sur `POST …/deploiements`. En attendant, la règle « un seul déploiement à la
  fois par projet » empêche déjà les doublons (réponse 409 avec l'identifiant du déploiement en cours).
  Quand : sprint 2, avec la table d'idempotence.
- [ ] **Journal d'audit** de `POST …/deploiements`. Quand : sprint 2 (carte « Journal d'audit »).
- [ ] **Quotas par compte.** Quand : sprint 2 (carte « Quotas »).
- [ ] **État de santé** dans le détail d'un projet (demandé par `etat_projet`). Dépend du moteur. Sprint 3.
- [ ] **Noms réservés** (`www`, `api`, `mcp`, `pay`, `admin`). À appliquer à la création d'un projet.

## Module « Communauté » du README

Le README de l'équipe décrit un septième module qui n'est dans aucune carte Trello ni dans les
endpoints de Babou. Moussa fait toute l'API : ces routes et ces tables sont à sa charge.
Il est construit partie par partie, dans cet ordre (chaque partie s'appuie sur la précédente) :

| Partie | Contenu | État |
|---|---|---|
| C1 | Profils publics, technologies, recherche de profils | Fait |
| C2 | Questions-réponses : question avec code, réponses, vote « Utile », réponse acceptée | À faire |
| C3 | Étoiles, points et classement par pays | À faire |
| C4 | Vitrine de projets : dépôt GitHub cloné, fichiers lisibles et modifiables, `.zip`, commentaires | À faire |
| C5 | Appels à collaboration : publication, candidatures, acceptation, équipe du projet | À faire |

Routes de la partie C1 : `GET /api/technologies`, `GET /api/profils` (filtres `q`, `pays`,
`technologie`, `disponibilite`, `par_page`), `GET /api/profils/{pseudo}` — toutes publiques ;
`GET /api/moi/profil` et `PATCH /api/moi/profil` — avec jeton.

Ce qui est volontairement laissé de côté dans C1 :

- [ ] **Photo de profil (avatar).** Pourquoi reporté : demande un stockage de fichiers, à décider avec
  Souleymane (volume du serveur ou stockage objet Datacloud).
- [ ] **Compteurs sur le profil** (étoiles, rang, projets, réponses acceptées) : ils arrivent avec C2 à C4.
- [ ] **Tri de la recherche par réputation** : aujourd'hui du plus récent au plus ancien ; par points après C3.
- [ ] **Niveau par compétence** (débutant, confirmé…) : non demandé par le README.
- [ ] **Ajout de technologies par un administrateur** : la liste de départ (50 technologies) est dans
  `TechnologySeeder` ; l'ajout par la console d'administration viendra avec le sprint 4.
- [ ] **Recherche tolérante aux accents et aux fautes** (`unaccent`, `pg_trgm`) : la recherche actuelle
  ignore la casse mais pas les accents (« traore » ne trouve pas « Traoré »).
- [ ] **Lancer `php artisan db:seed`** à chaque déploiement de l'API, pour charger les technologies.
  À signaler à Souleymane.

Points d'attention pour C4 (vitrine) :

- Le clonage d'un dépôt est une opération longue faite par le worker : à coordonner avec Souleymane.
- Écarter `.env`, binaires, `node_modules` et `vendor` ; limiter la taille ; ne jamais exécuter le code.

Le sujet du concours est « la plateforme d'échange » et la pertinence pèse 25 % de la note :
ce module ne doit pas être gardé pour la fin.

## Qualité et outillage

- [ ] **Analyse statique du dossier `config/`**.
  Pourquoi reporté : les deux seules erreurs venaient de fichiers fournis par Laravel et Sanctum.
- [ ] **Déclaration de licence** : `nette/schema` et `nette/utils` sont proposés sous BSD-3 ou GPL
  au choix. Il faut déclarer BSD-3 dans la liste des composants. À transmettre : Amadou.
- [ ] **Longueur des sous-domaines** : avec plusieurs services web, l'adresse est
  `<service>-<projet>.<domaine>`. Un service de 30 caractères et un projet de 35 dépassent la
  limite de 63 caractères d'un nom DNS. À décider : Souleymane et Babou (réduire les longueurs
  maximales ou tronquer).

## Questions ouvertes pour l'équipe

- [ ] Babou : les réponses de l'API sont enveloppées dans `{ "data": … }` (convention Laravel).
  Le serveur MCP doit lire `data`.
- [ ] Babou : `/api/moi` renvoie `data.compte` et `data.jeton` (`type`, `capacites`, `plafond_mensuel_fcfa`,
  `expire_le`). Un jeton sans la bonne capacité reçoit 403 `capacite_manquante` avec
  `details.capacites_requises`. Les jetons IA font 56 caractères et commencent par `sc_live_` ou `sc_test_`.
- [ ] Équipe : le README prévoit la spécification OpenAPI dans `docs/` à la racine ; elle est dans
  `api/openapi.json` (à côté du code qui la génère et du test qui la vérifie). À trancher : la laisser
  là et mettre un lien dans le README, ou la copier.
- [ ] Thioro : générer le client TypeScript à partir de `api/openapi.json` (OpenAPI 3.1). Les noms
  d'opération sont `auth.login`, `auth.register`, `auth.me`, `agent-tokens.store`…
- [ ] Thioro : routes d'authentification à brancher — `POST /api/inscription`, `POST /api/connexion`,
  `POST /api/deconnexion`, `GET /api/moi`, `GET|POST /api/jetons-ia`, `DELETE /api/jetons-ia/{id}`.
  Le jeton s'envoie dans l'en-tête `Authorization: Bearer …`.
- [ ] Tous : un projet déjà payé dont les tailles ne changent pas — `creer_commande` doit-il lancer
  directement le déploiement sans nouveau paiement ? (question posée par Babou)
- [ ] Amadou : le plafond de dépense mensuel est-il géré avec les commandes ou dans un réglage du
  compte ? (question posée par Babou ; l'API le rattache au jeton IA, comme dans son document)
