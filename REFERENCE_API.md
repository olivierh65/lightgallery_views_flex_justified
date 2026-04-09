# 📚 Référence complète des fonctions et méthodes - Module lightgallery_views_flex_justified

**Date**: 9 avril 2026
**Module**: lightgallery_views_flex_justified
**Lieu**: `/web/modules/custom/lightgallery_views_flex_justified`

> Ce document rassemble TOUTES les fonctions et méthodes publiques du module, avec leurs paramètres d'entrée, types de retour et descriptions.

---

## 📋 Table des matières

1. [Hooks Drupal (lightgallery_views_flex_justified.module)](#hooks-drupal--lightgallery_views_flex_justifiedmodule)
2. [Plugins Views - Style Galleries](#plugins-views--style-galleries)
3. [Traits](#traits)

---

## Hooks Drupal (`lightgallery_views_flex_justified.module`)

| # | Fonction | Paramètres | Retour | Description |
|---|----------|-----------|--------|-------------|
| 1 | `lightgallery_views_flex_justified_theme()` | `$existing` (array), `$type` (string), `$theme` (string), `$path` (string) | **array** | Implémente hook_theme() - Définit 3 thèmes (justified, isotope, flexbox) avec variables et templates Twig |
| 2 | `lightgallery_views_flex_justified_requirements()` | `$phase` (string = 'runtime') | **array** | Implémente hook_requirements() - Vérifie présence des librairies JavaScript LightGallery et Justified Gallery |
| 3 | `lightgallery_views_flex_justified_preprocess_image()` | `&$variables` (array) | **void** | Implémente hook_preprocess_image() - Ajoute suffixe #video aux miniatures vidéo pour styler via CSS |
| 4 | `template_preprocess_lightgallery_views_flex_justified()` | `&$variables` (array) | **void** | Préprocesse variables template - Configure attributs, cache, librairies (CSS/JS) et plugins LightGallery (zoom, fullscreen) |

---

## Plugins Views - Style Galleries

### 1. AlbumJustifiedGallery
📄 Fichier: `src/Plugin/views/style/AlbumJustifiedGallery.php`

**Description**: Plugin de style Views pour galerie images avec layout justifié (galerie professionnelle avec rangées équilibrées)

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$file_url_generator` (FileUrlGeneratorInterface) | **void** | Constructeur - Initialise le plugin de style avec service FileUrlGenerator pour URLs fichiers |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **static** | Factory method statique - Crée instance du plugin via conteneur DI |
| 3 | `defineOptions()` | *(aucun)* | **array** | Définit options par défaut : rowHeight (200), margins (10), sélecteurs champs (image, titre, auteur, URL) |
| 4 | `buildOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Construit formulaire configuration : sections image (champ, style, captions) et galerie (hauteur rangée, marges, dernière rangée) |
| 5 | `validateOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Valide le champ image sélectionné (vérifie existence, type image/media reference, cohérence entity/bundle) |
| 6 | `render()` | *(aucun)* | **array** | Rend la galerie complète - Traite résultats groupés, construit albums avec médias, attache librairies (justified-gallery, lightgallery) |

---

### 2. AlbumIsotopeGallery
📄 Fichier: `src/Plugin/views/style/AlbumIsotopeGallery.php`

**Description**: Plugin de style Views pour galerie images avec layout Isotope (grille masonry dynamique, réactive)

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$file_url_generator` (FileUrlGeneratorInterface) | **void** | Constructeur - Initialise plugin Isotope avec FileUrlGenerator pour gestion fichiers média |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **static** | Factory method statique - Crée instance via DI container |
| 3 | `defineOptions()` | *(aucun)* | **array** | Définit options : image (champ, style, tolérance, layout), galerie (largeur, gutter, horizontalOrder, fitWidth) |
| 4 | `buildOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Construit formulaire configuration : sections image (champ, style, captions, titre, description, auteur) et galerie (isotope/masonry/fitRows/vertical, gutter) |
| 5 | `validateOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Valide champ image sélectionné (existence, type image/media, cohérence entity/bundle) |
| 6 | `submitOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Gère soumission formulaire - Logs valeurs regroupement en JSON pour debug |
| 7 | `renderGrouping()` | `$records` (array), `$groupings` (array = []), `$group_rendered` (mixed = NULL) | **array** | Override renderGrouping - Force valeurs brutes de champs pour éviter TypeError avec objets Markup comme clés |
| 8 | `render()` | *(aucun)* | **array** | Rend galerie Isotope - Traite groupes résultats, attache librairies (isotope, lightgallery), retourne structure build Twig |

---

### 3. AlbumFlexboxGallery
📄 Fichier: `src/Plugin/views/style/AlbumFlexboxGallery.php`

**Description**: Plugin de style Views pour galerie images avec layout Flexbox (grille flexible, responsive, moderne)

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `__construct()` | `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array), `$file_url_generator` (FileUrlGeneratorInterface), `$grouping_config_service` (AlbumGroupingConfigService) | **void** | Constructeur - Initialise plugin Flexbox avec FileUrlGenerator et AlbumGroupingConfigService pour regroupements albums |
| 2 | `create()` | `$container` (ContainerInterface), `$configuration` (array), `$plugin_id` (string), `$plugin_definition` (array) | **static** | Factory method statique - Crée instance via DI container |
| 3 | `defineOptions()` | *(aucun)* | **array** | Définit options : image (champ, style, champs texte), lightgallery (closable, closeOnTap, controls) |
| 4 | `buildOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Construit formulaire configuration : sections image (champ, style, captions, titre, description, auteur) avec support 5 niveaux regroupement |
| 5 | `validateOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Valide champ image sélectionné (existence, type image/media, cohérence entity/bundle) |
| 6 | `submitOptionsForm()` | `&$form` (array), `$form_state` (FormStateInterface) | **void** | Gère soumission formulaire - Logs JSON valeurs regroupement |
| 7 | `renderGrouping()` | `$records` (array), `$groupings` (array = []), `$group_rendered` (mixed = NULL) | **array** | Override renderGrouping - Délégation au parent::renderGrouping() |
| 8 | `render()` | *(aucun)* | **array** | Rend galerie Flexbox - Gère 2 modes (per-node specific OU standard grouping), filtre groupes vides, tri par config, tokens sécurité, attache librairies |

---

## Traits

### ProcessAlbumTrait
📄 Fichier: `src/Traits/ProcessAlbumTrait.php`

**Utilisé par**: `AlbumJustifiedGallery`, `AlbumIsotopeGallery`, `AlbumFlexboxGallery`

**Description**: Trait fournissant la logique métier commune pour traitement des albums et médias dans les trois plugins de style.

#### 📌 Méthodes Publiques

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 1 | `getAvailableFields()` | `$hidden` (bool = FALSE) | **array** | Récupère structure des champs disponibles (text, media, taxonomy, number, date) pour utilisation formulaires options |
| 2 | `getFieldValue()` | `$index` (int), `$field` (string) | **mixed** | Récupère valeur d'un champ pour une ligne spécifique dans résultats vue |

#### 🔒 Méthodes Protégées

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 3 | `getMediaImageUrl()` | `$row` (object), `$field_name` (string) | **string** | [PROTECTED] Génère URL image/thumbnail pour média dans row - Applique image style si configuré, sinon fallback URL originale |
| 4 | `getDefaultImageUrl()` | `$filename` (string) | **string** | [PROTECTED] Construit URL fichier image par défaut dans dossier /images du module lightgallery |
| 5 | `getTextAndMediaFields()` | *(aucun)* | **array** | [PROTECTED] Récupère trio [text_fields, media_fields, taxonomy_fields] depuis handlers vue - Gère relationships et entity_reference |
| 6 | `getTextAndMediaFieldsCentric()` | `$hidden` (bool = FALSE) | **array** | [PROTECTED] Version alternative : 4 arrays [text, media, taxonomy, number] - Plus robuste pour field storage definitions |
| 7 | `getFieldInfo()` | `$entityField` (EntityField) | **array\|null** | [PROTECTED] Récupère infos structure champ (name, type, entity_type, settings, cardinality) via FieldStorageConfig |
| 8 | `getNidFieldName()` | *(aucun)* | **string\|null** | [PROTECTED] Identifie champ NID dans handlers vue pour grouping per-node |
| 9 | `getFieldValueFromRow()` | `$row` (object), `$field_name` (string) | **mixed** | [PROTECTED] Extrait valeur champ depuis row (propriété directe, _entity, ou rendered output) |
| 10 | `renderWithPerNodeGrouping()` | `$nid_field` (string), `&$build` (array), `&$lightgallery_settings` (array) | **array** | [PROTECTED] Render spécial : groupe par NID d'abord, puis applique regroupement specific à chaque nœud via AlbumGroupingConfigService |

#### 🔐 Méthodes Privées (détails d'implémentation)

| # | Méthode | Paramètres | Retour | Description |
|---|---------|-----------|--------|-------------|
| 11 | `getImageStyleDimensions()` | `$style_name` (string) | **array** | [PRIVÉE] Extrait dimensions (width, height) depuis image style via effects (image_scale, image_scale_and_crop) |
| 12 | `processGroupRecursive()` | `$groups` (array), `&$build` (array), `&$lightgallery_settings` (array), `$depth` (int = 0), `$idx` (int = 0) | **array** | [PRIVÉE] Traitement récursif structure grouping Views -> structure normalisée Twig (titre, level, albums, subgroups) |
| 13 | `buildAlbumDataFromGroup()` | `$rows` (array), `$group_index` (int), `&$lightgallery_album_settings` (array) | **array\|null** | [PRIVÉE] Construit données album à partir groupe de rows (ensemble médias) - Extrait entity media, gère source_field |
| 14 | `buildAlbumData()` | `$row` (object), `$index` (int), `&$lightgallery_album_settings` (array) | **array\|null** | [PRIVÉE] Construit données album pour une row (1 média = 1 album) - Gère _relationship_entities ET _entity fallback |
| 15 | `buildMediaItemData()` | `$media` (MediaInterface), `$source_field` (string) | **array\|null** | [PRIVÉE] Construit structure média item (URL, mime, alt, title, thumbnail) selon type source (image vs video_file) |
| 16 | `getLightgallerySettings()` | `$media` (MediaInterface) | **array** | [PRIVÉE] Récupère settings LightGallery depuis view mode du champ média unifié via entity display repository |
| 17 | `getSourceField()` | `$media` (MediaInterface) | **string\|null** | [PRIVÉE] Extrait source_field name depuis config plugin média (field_media_image, field_media_video_file, etc.) - Logs warning si absent |

---

## 📊 Résumé statistique

| Catégorie | Total |
|-----------|-------|
| **Hooks Drupal + Preprocess** | 4 |
| **Plugins Views Style** | 3 classes × 6-8 méthodes |
| **Traits** | 1 × 17 méthodes |
| **Total fichiers PHP** | 5 |
| **Total fonctions/méthodes publiques** | **15** |
| **Total métodes protégées** | **9** |
| **Total méthodes privées** | **13** |
| **Total général** | **37** |

---

## 🎯 Architecture du module

Ce module fournit **3 plugins Views Style** pour galeries média avec approches de layout différentes:

### 1. **Justified Gallery** (AlbumJustifiedGallery)
- Rangées équilibrées avec ratio hauteur constant
- Parfait pour portfolios photographiques professionnels
- Champs configurables: image, titre, auteur, URL externe

### 2. **Isotope Gallery** (AlbumIsotopeGallery)
- Grille masonry dynamique et réactive
- Layouts multiples: isotope, masonry, fitRows, vertical
- Gère le reflow en temps réel avec JavaScript Isotope

### 3. **Flexbox Gallery** (AlbumFlexboxGallery)
- Grille moderne CSS Flexbox
- Support multi-niveaux regroupement (jusqu'à 5 niveaux)
- Intégration avec AlbumGroupingConfigService pour regroupement per-nœud

### Points clés du design:

1. **Trait partagé** (ProcessAlbumTrait) - 17 méthodes pour logique métier commune
2. **Integration LightGallery** - Plugin lightbox minimaliste avec zoom, fullscreen, navigation
3. **Configuration flexible** - Champs image, texte, styles d'image configurables
4. **Sécurité HMAC** - Tokens de sécurité injectés dans structure groupes (flexbox)
5. **Support per-node** - Regroupements spécifiques au nœud via AlbumGroupingConfigService

---

## 🔧 Comment utiliser ce document

1. **Localiser une méthode**: Utilisez la table des matières ou Ctrl+F
2. **Comprendre un plugin**: Consultez la section du plugin Views (Justified/Isotope/Flexbox)
3. **Vérifier les paramètres**: Consultez colonne "Paramètres" pour types d'entrée
4. **Vérifier le retour**: Consultez colonne "Retour" pour type de sortie
5. **Logique métier partagée**: Consultez trait ProcessAlbumTrait (17 méthodes)
6. **Pour regroupements**: Voir AlbumFlexboxGallery::render() + AlbumGroupingConfigService

---

## 📦 Dépendances injectées

| Service | Interface | Utilisé par |
|---------|-----------|-------------|
| `file_url_generator` | `FileUrlGeneratorInterface` | Tous les 3 plugins |
| `grouping_config_service` | `AlbumGroupingConfigService` | AlbumFlexboxGallery uniquement |
| Drupal Core Services | Multiples | ProcessAlbumTrait |

---

**Dernière mise à jour**: 9 avril 2026
