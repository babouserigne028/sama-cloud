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
| C2 | Questions-réponses : question avec code, réponses, vote « Utile », réponse acceptée | Fait |
| C3 | Points de réputation, classement par pays, anti-triche | Fait pour les questions-réponses. Les étoiles sur les projets, templates et pannes arriveront avec ces contenus (C4 et sprint 4) |
| C4 | Vitrine de projets : dépôt GitHub copié, fichiers lisibles et modifiables, `.zip`, étoiles, commentaires | En partie : import, lecture du code et `.zip` faits ; restent la modification des fichiers, les étoiles et les commentaires |
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

Routes de la partie C2 : `GET /api/questions` (filtres `q`, `technologie`, `statut`, `par_page`) et
`GET /api/questions/{id}` — publiques ; avec un jeton de session : `POST /api/questions`,
`PATCH` et `DELETE /api/questions/{id}`, `POST /api/questions/{id}/reponses`, `PATCH` et
`DELETE /api/reponses/{id}`, `PUT` et `DELETE /api/questions/{id}/reponse-acceptee`,
`PUT` et `DELETE /api/reponses/{id}/vote-utile`.

Ce qui est volontairement laissé de côté dans C2 :

- [ ] **Affichage du Markdown : à la charge du front.** L'API stocke et renvoie le texte brut. Le front doit
  le mettre en forme ET le nettoyer (DOMPurify) avant de l'afficher, sinon une question peut contenir
  du code malveillant (XSS). À dire à Thioro, c'est un point de sécurité.
- [x] **Signalement d'un contenu** par les membres. Fait : `POST /api/questions/{id}/signalements`,
  `POST /api/reponses/{id}/signalements` ; côté administrateur `GET /api/admin/signalements` et
  `POST /api/admin/signalements/{id}/decision` (`retirer` ou `rejeter`).
- [ ] **Contenus d'un compte suspendu** : ses questions et réponses restent visibles (son profil, lui,
  est masqué). À décider : les masquer aussi.
- [x] **Notifications** dans le back-office : l'auteur d'une question est prévenu quand quelqu'un répond,
  l'auteur d'une réponse quand elle est acceptée. Fait : `GET /api/notifications`,
  `POST /api/notifications/{id}/lue`, `POST /api/notifications/lues`.
- [x] **Pagination des réponses** : la question joint ses 20 premières réponses ; les suivantes se lisent
  avec `GET /api/questions/{id}/reponses?page=2`.
- [ ] **Commentaires** sous une réponse, et **historique des modifications** : non demandés par le README.
- [ ] **Tris supplémentaires** de la liste (sans réponse, les plus actives) et recherche plein texte
  (`tsvector`) : la recherche actuelle est un simple « contient ce texte ».
- [x] **Points de réputation** : réponse acceptée +15, vote « Utile » reçu +5, affichés sur le profil
  (`points`). Accepter sa propre réponse ne rapporte rien ; supprimer un contenu reprend ses points.
- [ ] **Notifications par e-mail** : seules les notifications du back-office existent. L'envoi d'e-mails
  dépend du module Alertes d'Amadou (serveur SMTP).
- [ ] **Autres notifications** : vote « Utile » reçu, décision prise sur un signalement. Non demandées.
- [ ] **Retour au membre qui a signalé** (son signalement a été retenu ou rejeté) : il n'en est pas informé.
- [ ] **Signalement d'un profil** ou d'un futur projet de la vitrine : seules les questions et les
  réponses se signalent pour l'instant.
- [x] **Anti-triche, comptes récents** : le vote ou l'acceptation d'un compte de moins de 24 h est
  enregistré mais ne rapporte pas de points (réglage `COMMUNITY_MIN_ACCOUNT_AGE_HOURS`).
- [ ] **Nettoyage des vieilles notifications** : elles s'accumulent sans limite. Commande planifiée à prévoir.

Route de la partie C3 : `GET /api/classement` (filtres `pays`, `periode` = `tout` ou `mois`, `par_page`),
publique. Le rang est celui du classement demandé ; les ex æquo partagent le même rang.

Ce qui est volontairement laissé de côté dans C3 :

- [ ] **Décision pour la démonstration : la règle des 24 heures.** Un membre du jury qui crée un compte et
  vote aussitôt verra son vote compté, mais sans points pour l'auteur : il peut croire à un bogue.
  À décider avant la soumission : mettre `COMMUNITY_MIN_ACCOUNT_AGE_HOURS=0` sur la plateforme de
  démonstration, ou l'expliquer dans l'interface. Les points ne sont pas donnés après coup quand le
  compte atteint 24 heures.
- [ ] **Étoiles sur les projets, templates et pannes** (+5), **déploiement d'un template** (+2),
  **développeur aidé par une panne** (+2), **contenu publié** (+10) : ces contenus n'existent pas encore.
  Le registre des points (`ReputationLedger`) est prêt à les recevoir.
- [ ] **Rang affiché sur le profil public** : le profil montre les points, pas encore le rang.
- [ ] **Alerte aux administrateurs en cas de pic anormal de votes** sur un compte.
- [ ] **Classement par technologie** : non demandé par le README (il l'était dans l'ancien cahier des charges).
- [ ] **Performance** : le classement est recalculé à chaque appel. Suffisant pour le concours ; au-delà de
  quelques milliers de membres, prévoir un cache de quelques minutes.

Routes de la partie C4 (première moitié) — publiques : `GET /api/vitrine` (filtres `q`, `technologie`,
`par_page`), `GET /api/vitrine/{id}`, `GET /api/vitrine/{id}/fichiers`,
`GET /api/vitrine/{id}/fichiers/contenu?chemin=…`, `GET /api/vitrine/{id}/archive` ; avec un jeton de
session : `POST /api/vitrine`, `PATCH` et `DELETE /api/vitrine/{id}`, `POST /api/vitrine/{id}/import`.

Décisions prises pour la vitrine :

- **Pas de `git clone`** : l'API télécharge l'archive `.zip` du dépôt chez GitHub et la lit. Aucune commande
  n'est lancée et aucun code n'est exécuté, donc pas besoin d'accès à Docker.
- **Fichiers stockés dans PostgreSQL** (table `showcase_files`), pas sur le disque : sauvegarde unique,
  modification simple, pas de volume à gérer.
- **Limites** (dans `config/samacloud.php`) : archive de 30 Mo, 500 fichiers, 200 Ko par fichier, 5 Mo au total.
  Au-delà, le projet est marqué « incomplet ».
- **Écartés** : `.env` et ses variantes, clés et certificats, `node_modules`, `vendor`, dossiers générés,
  binaires, fichiers de verrouillage.

Ce qui est volontairement laissé de côté :

- [ ] **Un worker de file d'attente doit tourner en production** (`php artisan queue:work`). Sans lui, les
  imports restent « en_attente » indéfiniment. À demander à Souleymane, avec le démarrage automatique.
- [ ] **Imports bloqués** : si le worker s'arrête en plein import, le projet reste « en_cours ».
  Prévoir une commande planifiée qui passe en « echec » les imports trop vieux.
- [ ] **Captures d'écran du projet** : demandent un stockage de fichiers (volume ou stockage objet Datacloud),
  à décider avec Souleymane.
- [ ] **Limite d'appels de GitHub** : sans jeton, GitHub accepte 60 téléchargements par heure et par adresse IP
  du serveur. Suffisant pour le concours ; au-delà, ajouter un jeton GitHub de SamaCloud dans la configuration.
- [ ] **Dépôts privés** : non pris en charge (le README parle de dépôt public).
- [ ] **Archive lue en mémoire** (30 Mo au plus) : suffisant ; pour de plus gros dépôts, écrire directement
  sur le disque pendant le téléchargement.
- [ ] **Lien automatique avec un projet déployé** : « Voir la démo » est une adresse saisie à la main
  (`demo_url`), pas encore reliée aux projets hébergés.
- [ ] **Signalement d'un projet de la vitrine** : seules les questions et réponses se signalent.
- [ ] À dire à Thioro : nettoyer le Markdown de `description` avant affichage (comme pour les questions) ;
  le contenu des fichiers est du texte brut à donner à Monaco, jamais à insérer comme HTML.
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
