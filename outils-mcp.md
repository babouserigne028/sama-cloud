# Serveur MCP SamaCloud — Liste des outils par niveau de risque

> Sprint 1 · Responsable : Babou · À valider avec Moussa (API) et Souleymane (moteur)
> Version 0.1 — 3 octobre 2026

Le serveur MCP ne contient **aucune logique métier** : chaque outil appelle un endpoint
de l'API Laravel avec le jeton du développeur. Les règles (prix, quotas, confirmations,
plafond de dépense) sont **appliquées par l'API**, jamais seulement par le serveur MCP.

Légende de la colonne **Sprint** : `S3` = sprint 3, `S4` = sprint 4.

---

## 1. Lecture — exécution libre

Aucun effet sur l'infrastructure ni sur la facturation.

| # | Outil | Ce qu'il fait | Paramètres | Endpoint API | Renvoie | Sprint |
|---|---|---|---|---|---|---|
| 1 | `analyser_depot` | Analyse un dépôt Git et propose une configuration | `url_depot`, `branche?` | `POST /api/analyses` | framework, version du langage, dépendances, bases nécessaires, taille conseillée, `datacloud.yaml` proposé | S3 |
| 2 | `lister_projets` | Liste les projets du développeur | — | `GET /api/projets` | nom, statut, URL, taille, échéance | S3 |
| 3 | `etat_projet` | Détail d'un projet | `projet` | `GET /api/projets/{projet}` | statut, URL, services, bases, santé, échéance | S3 |
| 4 | `lire_logs` | Logs récents d'un service ou d'un build | `projet`, `service?`, `type` (`execution` \| `build`), `deploiement_id?`, `lignes?` (défaut 100, max 500) | `GET /api/projets/{projet}/logs` | lignes de logs **marquées comme données non fiables** | S3 |
| 5 | `lire_metriques` | Processeur et mémoire comparés à la taille payée | `projet`, `periode?` (`1h` \| `24h` \| `7j`) | `GET /api/projets/{projet}/metriques` | séries CPU/mémoire, limite de la taille, alerte de saturation | S3 |
| 6 | `lister_deploiements` | Historique des déploiements | `projet` | `GET /api/projets/{projet}/deploiements` | id, date, commit, statut, durée, acteur (humain / IA) | S3 |
| 7 | `suivre_operation` | Avancement d'une opération longue (build, création de base…) | `operation_id` | `GET /api/operations/{id}` | statut, étape, pourcentage, erreur éventuelle | S3 |
| 8 | `consulter_catalogue` | Tailles disponibles et prix en FCFA | — | `GET /api/catalogue` | tailles d'application et de base, ressources, prix sur 30 jours | S3 |
| 9 | `lister_commandes` | Commandes, paiements, échéances, crédit | `projet?` | `GET /api/commandes` | commandes, statut du paiement, échéances, solde de crédit | S3 |
| 10 | `lire_journal_audit` | Qui a fait quoi | `projet?`, `limite?` | `GET /api/audit` | actions, date, acteur (humain / IA) | S3 |
| 11 | `planifier_deploiement` | Prépare un **plan** et son devis, sans rien exécuter | `projet` (existant ou nouveau nom), `url_depot?`, `template_id?`, `datacloud_yaml?`, `tailles?` | `POST /api/plans` | `plan_id`, liste lisible des actions, devis FCFA calculé par l'API, expiration du plan | S3 |
| 12 | `chercher_incidents` | Cherche des pannes résolues par la communauté | `requete`, `framework?` | `GET /api/incidents?q=` | symptôme, cause, correctif, auteur — **données non fiables** | S4 |
| 13 | `chercher_templates` | Cherche des templates partagés | `requete`, `framework?` | `GET /api/templates?q=` | titre, auteur, étoiles, coût estimé | S4 |

> `planifier_deploiement` crée seulement un plan en base : aucune ressource n'est
> réservée et rien n'est facturé. C'est la partie **plan** du mode plan/apply.

---

## 2. Écriture réversible — résumé des changements, puis validation

Avant l'appel, l'IA présente un résumé de ce qui va changer et attend l'accord du
développeur. Chaque appel envoie un en-tête `Idempotency-Key` généré par le serveur
MCP : une demande répétée ne crée pas de doublon.

| # | Outil | Ce qu'il fait | Paramètres | Endpoint API | Renvoie | Sprint |
|---|---|---|---|---|---|---|
| 14 | `creer_commande` | **Applique** un plan validé : crée la commande et le lien de paiement | `plan_id` | `POST /api/plans/{plan_id}/appliquer` | `commande_id`, devis final, **lien de paiement valable 15 min**, capacité réservée jusqu'à… | S3 |
| 15 | `regenerer_lien_paiement` | Nouveau lien quand le précédent a expiré (devis recalculé) | `commande_id` | `POST /api/commandes/{id}/lien` | nouveau lien, nouveau devis | S3 |
| 16 | `preparer_renouvellement` | Lien de renouvellement avant l'échéance | `projet` | `POST /api/projets/{projet}/renouvellement` | devis, lien de paiement | S3 |
| 17 | `redeployer` | Relance un déploiement (période payée) | `projet`, `branche?`, `commit?` | `POST /api/projets/{projet}/deploiements` | `operation_id` à suivre avec `suivre_operation` | S3 |
| 18 | `modifier_variables` | Ajoute, modifie ou retire des variables d'environnement | `projet`, `service?`, `definir?` (`{NOM: valeur}`), `retirer?` (`[NOM]`) | `PATCH /api/projets/{projet}/variables` | **noms** des variables modifiées, jamais les valeurs ; indique si un redéploiement est nécessaire | S3 |
| 19 | `publier_template` | Publie la stack d'un projet comme template, en deux temps | étape 1 : `projet`, `titre`, `description` · étape 2 : `apercu_id` | 1. `POST /api/templates/apercus` · 2. `POST /api/templates` | 1. aperçu **sans secrets** à faire relire · 2. template publié | S4 |
| 20 | `publier_incident` | Publie un incident résolu anonymisé, en deux temps | étape 1 : `deploiement_id`, `symptome`, `cause`, `correctif`, `runbook?` · étape 2 : `apercu_id` | 1. `POST /api/incidents/apercus` · 2. `POST /api/incidents` | 1. aperçu anonymisé à faire relire · 2. incident publié | S4 |

> Les publications passent toujours par un **aperçu** relu par l'auteur : l'API retire
> les secrets (variables sensibles, clés, URL privées) et l'IA ne peut publier qu'un
> `apercu_id` déjà généré.

---

## 3. Destructive — confirmation explicite

Le développeur doit **taper le nom exact du projet**. L'IA ne doit jamais remplir ce
champ elle-même à partir du contexte : elle le demande et recopie la réponse.
L'API refuse l'appel si `confirmation_nom` ne correspond pas.

| # | Outil | Ce qu'il fait | Paramètres | Endpoint API | Renvoie | Sprint |
|---|---|---|---|---|---|---|
| 21 | `supprimer_projet` | Arrête et supprime un projet et ses conteneurs | `projet`, `confirmation_nom` | `DELETE /api/projets/{projet}` | `operation_id` | S3 |
| 22 | `supprimer_base` | Supprime une base de données et ses données | `projet`, `base`, `confirmation_nom` | `DELETE /api/projets/{projet}/bases/{base}` | `operation_id` | S3 |

---

## 4. Paiement — aucun outil

L'IA **ne paie jamais**. Elle transmet le lien renvoyé par `creer_commande`,
`regenerer_lien_paiement` ou `preparer_renouvellement` ; seul un humain paie.

### Outils volontairement exclus

| Outil absent | Raison |
|---|---|
| `payer`, `confirmer_paiement` | Seul un humain paie ; la confirmation vient du webhook du fournisseur |
| `modifier_prix`, `calculer_devis` côté IA | Le prix est toujours calculé par l'API |
| `lire_valeur_variable` | Les valeurs des secrets ne sortent jamais vers l'IA |
| `executer_commande` (shell dans un conteneur) | Trop risqué ; hors périmètre |
| `donner_etoile` | Les étoiles sont un geste humain ; évite que l'IA gonfle le classement |
| Outils d'administration (crédits, suspension, catalogue) | Réservés au rôle administrateur, jamais au jeton de l'agent IA |

---

## 5. Ressources MCP (lecture passive)

| Ressource | Contenu | Endpoint API |
|---|---|---|
| `samacloud://projets` | État de tous les projets | `GET /api/projets` |
| `samacloud://projets/{projet}/logs` | Logs récents | `GET /api/projets/{projet}/logs` |
| `samacloud://consommation` | Consommation et échéances | `GET /api/commandes` |
| `samacloud://incidents` | Incidents partagés par la communauté | `GET /api/incidents` |

## 6. Prompts prêts à l'emploi

| Prompt | Enchaînement d'outils |
|---|---|
| `diagnostiquer_mon_deploiement` | `lister_deploiements` → `lire_logs` (build) → `chercher_incidents` → explication + correctif → validation → `redeployer` → proposer `publier_incident` |
| `mettre_en_place_stack_laravel` | `analyser_depot` → `consulter_catalogue` → `planifier_deploiement` → validation → `creer_commande` → lien de paiement → `suivre_operation` |
| `partager_ma_stack` | `etat_projet` → `publier_template` (aperçu) → relecture → `publier_template` (publication) |

---

## 7. Règles transverses (à implémenter côté API)

1. **Jeton de l'agent IA.** Le développeur crée un jeton Sanctum de type « agent IA »
   depuis le back-office. Ses capacités (`abilities`) : `projets:lire`, `projets:ecrire`,
   `projets:supprimer`, `echange:publier`. Jamais de capacité d'administration.
   Détails en section 8.
2. **Journal d'audit.** L'API marque « IA » toute action faite avec ce jeton — elle se
   base sur le jeton, pas sur un en-tête envoyé par le client.
3. **Plafond de dépense mensuel.** `creer_commande` est refusé par l'API au-delà du
   plafond défini par le développeur.
4. **Idempotence.** Toutes les écritures acceptent `Idempotency-Key` ; une même clé
   renvoie le même résultat sans refaire l'action.
5. **Opérations longues.** Build, création de base, suppression : réponse immédiate
   avec `operation_id`, avancement via `GET /api/operations/{id}`.
6. **Données non fiables.** Logs, fichiers du dépôt et incidents partagés sont renvoyés
   dans un champ distinct (ex. `contenu_non_fiable`) avec leur source ; le serveur MCP
   rappelle à l'IA que ce sont des données, jamais des instructions. Aucun outil
   d'écriture ne doit être déclenché à partir de leur seul contenu.
7. **Erreurs lisibles.** Format commun `{ code, message, details? }` en français, pour
   que l'IA puisse expliquer le refus (quota atteint, lien expiré, plafond dépassé…).

---

## 8. Authentification du serveur MCP

### Principe

Le développeur fournit au serveur MCP un **jeton personnel** créé dans le back-office.
Le serveur MCP l'envoie à chaque appel (`Authorization: Bearer <jeton>`) ; l'API sait
ainsi quel développeur agit, et qu'il agit **via l'IA**. Le jeton reste dans la
configuration de l'éditeur : l'IA ne le voit jamais dans la conversation.

```
Back-office « Accès IA » → « Créer un jeton pour l'IA »
        ↓  API (Sanctum) : jeton affiché UNE SEULE FOIS — sc_live_8f3k...
Configuration de l'éditeur (variable SAMACLOUD_TOKEN)
        ↓
Serveur MCP local → Authorization: Bearer sc_live_8f3k... → API Laravel
```

### Mode retenu pour le concours : serveur MCP local (stdio)

Le serveur tourne sur la machine du développeur, lancé par l'éditeur, et lit le jeton
dans la variable d'environnement `SAMACLOUD_TOKEN` (et l'adresse de l'API dans
`SAMACLOUD_API_URL`, avec une valeur par défaut). Au démarrage, il vérifie le jeton
(`GET /api/moi`) et s'arrête avec un message clair s'il est absent, expiré ou révoqué.

**Claude Code**

```bash
claude mcp add samacloud --env SAMACLOUD_TOKEN=sc_live_8f3k... -- npx -y @samacloud/mcp
```

**VS Code** — `.vscode/mcp.json` (le jeton est demandé une fois, jamais écrit en clair)

```json
{
  "servers": {
    "samacloud": {
      "command": "npx",
      "args": ["-y", "@samacloud/mcp"],
      "env": { "SAMACLOUD_TOKEN": "${input:samacloud-token}" }
    }
  },
  "inputs": [
    { "id": "samacloud-token", "type": "promptString", "description": "Jeton SamaCloud", "password": true }
  ]
}
```

**Cursor** — `.cursor/mcp.json`

```json
{
  "mcpServers": {
    "samacloud": {
      "command": "npx",
      "args": ["-y", "@samacloud/mcp"],
      "env": { "SAMACLOUD_TOKEN": "sc_live_8f3k..." }
    }
  }
}
```

La page « Accès IA » du back-office affiche ces trois blocs **pré-remplis** avec le
jeton qui vient d'être créé, prêts à copier.

### Endpoints de gestion des jetons

Réservés à la **session du navigateur** : un jeton d'agent IA ne peut ni créer, ni
lister, ni révoquer de jetons.

| Action | Endpoint | Notes |
|---|---|---|
| Créer un jeton IA | `POST /api/jetons-ia` | Corps : `nom` (ex. « VS Code portable »), `expiration_jours?` (défaut 90), `plafond_mensuel_fcfa?`. Renvoie le jeton en clair **une seule fois** |
| Lister ses jetons | `GET /api/jetons-ia` | Nom, préfixe, date de création, dernière utilisation, expiration — jamais le jeton |
| Révoquer un jeton | `DELETE /api/jetons-ia/{id}` | Effet immédiat |
| Vérifier le jeton courant | `GET /api/moi` | Utilisé par le serveur MCP au démarrage : compte, type de jeton, capacités |

### Règles du jeton (côté API)

| Règle | Pourquoi |
|---|---|
| Type « agent IA », distinct du jeton de session du navigateur | Le journal d'audit marque automatiquement ces actions « IA » |
| Capacités : `projets:lire`, `projets:ecrire`, `projets:supprimer`, `echange:publier` | Jamais d'accès administrateur ni de gestion des jetons |
| Affiché une seule fois, stocké haché (comportement natif de Sanctum) | Une fuite de la base ne révèle pas les jetons |
| Expiration (90 jours par défaut) et révocation depuis le back-office | En cas de fuite, le développeur le coupe |
| Préfixe `sc_live_` (compte réel) / `sc_test_` (compte de démonstration) | Repérable s'il est commité par erreur dans un dépôt Git |
| Jamais écrit dans les logs, ni côté API ni côté serveur MCP | Règle du cahier des charges sur les secrets |
| Plafond de dépense mensuel rattaché au jeton | `creer_commande` refusé au-delà |
| Limitation du débit (ex. 60 requêtes / minute par jeton) | Évite qu'une IA en boucle sature l'API |

### Compte de démonstration (jury)

Le compte de test génère des jetons `sc_test_...` limités : quotas réduits, paiement
simulé, plafond de dépense fictif. La page d'accueil et le README expliquent comment
créer ce jeton et brancher le serveur MCP en moins de deux minutes.

### Feuille de route : serveur MCP distant avec OAuth

Après le concours : héberger le serveur MCP en HTTP (`https://mcp.<domaine>`) avec une
connexion **OAuth**. Le développeur clique sur « Se connecter à SamaCloud » depuis
Claude ou VS Code et n'a plus de jeton à copier. Le serveur MCP reste une façade sans
logique métier : seule la manière d'obtenir le jeton change.

---

## 9. Résumé pour l'API (à intégrer à la spécification OpenAPI)

Liste dédoublonnée des endpoints dont le serveur MCP a besoin : **28 endpoints**.
Les paramètres et réponses détaillés sont dans les sections 1 à 3 et 8.

La colonne **Priorité** indique le sprint où l'endpoint doit exister. Les endpoints
marqués `S2` suffisent pour brancher le serveur MCP sur l'API dès le sprint 2.

| # | Méthode | Endpoint | Utilisé par (outil MCP) | Priorité |
|---|---|---|---|---|
| **Authentification** | | | | |
| 1 | `GET` | `/api/moi` | démarrage du serveur MCP | S2 |
| 2 | `POST` | `/api/jetons-ia` | back-office uniquement | S2 |
| 3 | `GET` | `/api/jetons-ia` | back-office uniquement | S2 |
| 4 | `DELETE` | `/api/jetons-ia/{id}` | back-office uniquement | S2 |
| **Projets** | | | | |
| 5 | `GET` | `/api/projets` | `lister_projets` | S2 |
| 6 | `GET` | `/api/projets/{projet}` | `etat_projet` | S2 |
| 7 | `DELETE` | `/api/projets/{projet}` | `supprimer_projet` | S3 |
| 8 | `DELETE` | `/api/projets/{projet}/bases/{base}` | `supprimer_base` | S3 |
| 9 | `PATCH` | `/api/projets/{projet}/variables` | `modifier_variables` | S3 |
| 10 | `POST` | `/api/projets/{projet}/renouvellement` | `preparer_renouvellement` | S3 |
| **Déploiements et suivi** | | | | |
| 11 | `GET` | `/api/projets/{projet}/deploiements` | `lister_deploiements` | S2 |
| 12 | `POST` | `/api/projets/{projet}/deploiements` | `redeployer` | S2 |
| 13 | `GET` | `/api/operations/{id}` | `suivre_operation` | S2 |
| 14 | `GET` | `/api/projets/{projet}/logs` | `lire_logs` | S3 |
| 15 | `GET` | `/api/projets/{projet}/metriques` | `lire_metriques` | S3 |
| 16 | `POST` | `/api/analyses` | `analyser_depot` | S3 |
| **Plan, devis et commandes** | | | | |
| 17 | `GET` | `/api/catalogue` | `consulter_catalogue` | S3 |
| 18 | `POST` | `/api/plans` | `planifier_deploiement` | S3 |
| 19 | `POST` | `/api/plans/{plan_id}/appliquer` | `creer_commande` | S3 |
| 20 | `GET` | `/api/commandes` | `lister_commandes` | S3 |
| 21 | `POST` | `/api/commandes/{id}/lien` | `regenerer_lien_paiement` | S3 |
| 22 | `GET` | `/api/audit` | `lire_journal_audit` | S3 |
| **Échange** | | | | |
| 23 | `GET` | `/api/templates` | `chercher_templates` | S4 |
| 24 | `POST` | `/api/templates/apercus` | `publier_template` (étape 1) | S4 |
| 25 | `POST` | `/api/templates` | `publier_template` (étape 2) | S4 |
| 26 | `GET` | `/api/incidents` | `chercher_incidents` | S4 |
| 27 | `POST` | `/api/incidents/apercus` | `publier_incident` (étape 1) | S4 |
| 28 | `POST` | `/api/incidents` | `publier_incident` (étape 2) | S4 |

### Règles communes à tous ces endpoints

1. **Jeton « agent IA »** avec les capacités `projets:lire`, `projets:ecrire`,
   `projets:supprimer`, `echange:publier`. Les endpoints 2 à 4 lui sont **interdits**.
2. **Journal d'audit** : toute action faite avec un jeton IA est marquée « IA ».
3. **`Idempotency-Key`** accepté sur tous les `POST`, `PATCH` et `DELETE`.
4. **Opérations longues** (endpoints 7, 8, 12, 19) : réponse immédiate avec un
   `operation_id`, avancement via l'endpoint 13.
5. **`confirmation_nom` vérifié par l'API** sur les suppressions (endpoints 7 et 8).
6. **Plafond de dépense mensuel** vérifié sur l'endpoint 19.
7. **Contenu non fiable** (logs, incidents) renvoyé dans un champ séparé, avec sa source.
8. **Valeurs des variables jamais renvoyées** : uniquement les noms.
9. **Format d'erreur commun** `{ code, message, details? }`, messages en français.
10. **Limite de débit** : 60 requêtes par minute et par jeton.

---

## Récapitulatif

| Niveau | Nombre | Outils |
|---|---|---|
| Lecture | 13 | `analyser_depot`, `lister_projets`, `etat_projet`, `lire_logs`, `lire_metriques`, `lister_deploiements`, `suivre_operation`, `consulter_catalogue`, `lister_commandes`, `lire_journal_audit`, `planifier_deploiement`, `chercher_incidents`, `chercher_templates` |
| Écriture réversible | 7 | `creer_commande`, `regenerer_lien_paiement`, `preparer_renouvellement`, `redeployer`, `modifier_variables`, `publier_template`, `publier_incident` |
| Destructive | 2 | `supprimer_projet`, `supprimer_base` |
| Paiement | 0 | — |
| **Total** | **22** | |

## Questions ouvertes pour l'équipe

- **Moussa** : les noms et chemins d'endpoints te conviennent-ils pour l'OpenAPI ?
  Faut-il une ressource `plans` séparée des `commandes` ?
- **Souleymane** : `analyser_depot` passe-t-il par le détecteur du moteur (clonage
  temporaire) ? Comment gérer un dépôt privé (jeton Git du développeur) ?
- **Amadou** : le plafond de dépense mensuel est-il géré avec les commandes ou dans
  un réglage du compte ?
- **Tous** : un projet déjà payé dont les tailles ne changent pas — `creer_commande`
  doit-il lancer directement le déploiement sans nouveau paiement ?
