<?php

namespace Drupal\lightgallery_views_flex_justified\Traits;

use Drupal\file\FileInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\MediaInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides reusable logic for albums.
 */
trait ProcessAlbumTrait {

  /**
   * Get the dimensions of an image style.
   */
  private function getImageStyleDimensions(string $style_name): array {
    $max_width = NULL;

    $max_height = NULL;
    if (!empty($style_name)) {
      $image_style = ImageStyle::load($style_name);
      if ($image_style) {
        $effects = $image_style->getEffects();
        foreach ($effects as $effect) {
          $conf = $effect->getConfiguration();
          if ($conf['id'] === 'image_scale' || $conf['id'] === 'image_scale_and_crop') {
            $max_width = $conf['data']['width'] ?? NULL;
            $max_height = $conf['data']['height'] ?? NULL;
            break;
          }
        }
      }
    }
    return ['width' => $max_width, 'height' => $max_height];
  }

  /**
   * Recursively process the grouping structure from Views.
   *
   * @param array $groups
   *   The grouping structure returned by renderGrouping().
   * @param array &$build
   *   The build array (passed by reference to add settings).
   * @param array &$lightgallery_settings
   *   The lightgallery settings (passed by reference to collect).
   * @param int $depth
   *   Current depth (for debug/styling).
   *
   * @return array
   *   Normalized structure for Twig.
   */
  private function processGroupRecursive(array $groups, array &$build, array &$lightgallery_settings, int $depth = 0, $idx = 0) {

    $processed = [];

    foreach ($groups as $group_key => $group_data) {
      $idx = rand();
      $group_item = [
        'title' => $group_data['group'] ?? '',
        'level' => $group_data['level'] ?? $depth,
        'albums' => [],
        'subgroups' => [],
        'groupid' => 'album-group-' . $idx,
      ];

      // Check if this group contains rows (final results)
      if (isset($group_data['rows']) && is_array($group_data['rows'])) {

        // DDetermine if the "rows" are actually other groups or real rows.
        $first_row = reset($group_data['rows']);

        if (is_array($first_row) && isset($first_row['group']) && isset($first_row['level'])) {
          // These are subgroups, process recursively.
          $group_item['subgroups'] = $this->processGroupRecursive(
          $group_data['rows'],
          $build,
          $lightgallery_settings,
          $depth + 1,
          $idx
          );
        }
        else {
          $r = $this->buildAlbumDataFromGroup($group_data['rows'], $idx, $lightgallery_settings);
          if ($r) {
            $group_item['albums'][] = $r;
          }
          /* // These are real rows (ResultRow), process albums.
          foreach ($group_data['rows'] as $index => $row) {
          $album_data = $this->buildAlbumData($row, $index, $lightgallery_settings);
          if ($album_data) {
          $group_item['albums'][] = $album_data;
          }
          } */
        }
      }
      $processed[] = $group_item;
    }

    return $processed;
  }

  /**
   * Build album data from grouped rows.
   *
   * @param array $rows
   *   Array of rows in the same group.
   * @param int $group_index
   *   The group index.
   * @param array $lightgallery_album_settings
   *   The lightgallery settings (passed by reference to collect).
   *
   * @return array|null
   *   The album data or NULL on error.
   */
  private function buildAlbumDataFromGroup($rows, $group_index, array &$lightgallery_album_settings) {
    $medias = [];
    $first_media = NULL;
    $title = '';
    $author = '';
    $description = '';

    foreach ($rows as $index => $row) {
      $this->view->row_index = $index;

      // Get the media entity from the row.
      $media = NULL;
      
      // Vérifier d'abord si le média est dans _relationship_entities (nouvelle structure)
      if (isset($row->_relationship_entities) && is_array($row->_relationship_entities)) {
        // Chercher le champ de relationship (ex: field_media_album_av_media)
        foreach ($row->_relationship_entities as $rel_field => $rel_entity) {
          if ($rel_entity instanceof MediaInterface) {
            $media = $rel_entity;
            break;
          }
        }
      }
      
      // Fallback: ancienne structure où le média était dans _entity
      if (!$media && isset($row->_entity) && $row->_entity instanceof MediaInterface) {
        $media = $row->_entity;
      }
      
      if (!$media) {
        continue;
      }

      // Keep first media for album thumbnail.
      if (!$first_media) {
        $first_media = $media;

        // Get text fields from first row.
        $title = !empty($this->options['image']['title_field'])
        ? $this->getFieldValue($index, $this->options['image']['title_field'])
        : '';
        $author = !empty($this->options['image']['author_field'])
        ? $this->getFieldValue($index, $this->options['image']['author_field'])
        : '';
        $description = !empty($this->options['image']['description_field'])
        ? $this->getFieldValue($index, $this->options['image']['description_field'])
        : '';
      }

      // Get source field and build media data.
      $source_field = $this->getSourceField($media);
      if (!$source_field) {
        continue;
      }

      $media_data = $this->buildMediaItemData($media, $source_field);
      if ($media_data) {
        $medias[] = $media_data;
      }
    }

    if (empty($medias) || !$first_media) {
      return NULL;
    }

    // Use first media's thumbnail as album image.
    $image_url = $medias[0]['thumbnail'] ?? $medias[0]['url'] ?? '';

    // Get lightgallery settings.
    $lightgallery_settings = $this->getLightgallerySettings($first_media);

    // Build plugins list and attach libraries.
    foreach ($lightgallery_settings['plugins'] ?? [] as $plugin_name => $plugin) {
      $lightgallery_album_settings['plugins'][$plugin_name] = 'lightgallery/lightgallery-' . $plugin_name ?? $plugin_name;
    }

    $album_id = 'album-group-' . $group_index;
    $lightgallery_album_settings[$album_id] = $lightgallery_settings;

    return [
      'id' => $album_id,
      'image_url' => $image_url,
      'title' => $title,
      'author' => $author,
      'description' => $description,
      'url' => '',
      'medias' => $medias,
    ];
  }

  /**
   * Build album data from a row.
   *
   * @param object $row
   *   The view row (ResultRow).
   * @param int $index
   *   The row index.
   * @param array $lightgallery_album_settings
   *   The lightgallery settings (passed by reference to collect).
   *
   * @return array|null
   *   The album data or NULL on error.
   */
  private function buildAlbumData($row, $index, array &$lightgallery_album_settings) {
    $this->view->row_index = $index;

    // Get the media entity from the row.
    $media = NULL;
    
    // Vérifier d'abord si le média est dans _relationship_entities (nouvelle structure)
    if (isset($row->_relationship_entities) && is_array($row->_relationship_entities)) {
      // Chercher le champ de relationship (ex: field_media_album_av_media)
      foreach ($row->_relationship_entities as $rel_field => $rel_entity) {
        if ($rel_entity instanceof MediaInterface) {
          $media = $rel_entity;
          break;
        }
      }
    }
    
    // Fallback: ancienne structure où le média était dans _entity
    if (!$media && isset($row->_entity) && $row->_entity instanceof MediaInterface) {
      $media = $row->_entity;
    }

    if (!$media) {
      \Drupal::logger('album_gallery')->warning('No media entity found in row @index', ['@index' => $index]);
      return NULL;
    }

    // Get source field for this media.
    $source_field = $this->getSourceField($media);
    if (!$source_field) {
      \Drupal::logger('album_gallery')->warning('No source field found for media @id', ['@id' => $media->id()]);
      return NULL;
    }

    // Text fields from the view row.
    $title = !empty($this->options['image']['title_field'])
    ? $this->getFieldValue($index, $this->options['image']['title_field'])
    : '';
    $author = !empty($this->options['image']['author_field'])
    ? $this->getFieldValue($index, $this->options['image']['author_field'])
    : '';
    $description = !empty($this->options['image']['description_field'])
    ? $this->getFieldValue($index, $this->options['image']['description_field'])
    : '';

    // Build media item data based on type.
    $media_data = $this->buildMediaItemData($media, $source_field);

    if (!$media_data) {
      \Drupal::logger('album_gallery')->warning('Failed to build media data for media @id', ['@id' => $media->id()]);
      return NULL;
    }

    // Use the first media item's thumbnail as the album image.
    $image_url = $media_data['thumbnail'] ?? $media_data['url'] ?? '';

    // Get lightgallery settings from the unified media field view mode.
    $lightgallery_settings = $this->getLightgallerySettings($media);

    // Build plugins list and attach libraries.
    foreach ($lightgallery_settings['plugins'] ?? [] as $plugin_name => $plugin) {
      $lightgallery_album_settings['plugins'][$plugin_name] = 'lightgallery/lightgallery-' . $plugin_name ?? $plugin_name;
    }

    $album_id = 'album-item-' . $media->id() . '-' . $index;
    $lightgallery_album_settings[$album_id] = $lightgallery_settings;

    return [
      'id' => $album_id,
      'image_url' => $image_url,
      'title' => $title,
      'author' => $author,
      'description' => $description,
    // Pas d'URL de node dans ce contexte.
      'url' => '',
    // Un seul média par row.
      'medias' => [$media_data],
    ];
  }

  /**
   * Build media item data for a single media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param string $source_field
   *   The source field name.
   *
   * @return array|null
   *   The media item data or NULL on error.
   */
  private function buildMediaItemData(MediaInterface $media, $source_field) {
    switch ($media->getSource()->getPluginId()) {
      case 'image':
        $file = $media->get($source_field)->entity;
        if ($file instanceof FileInterface) {
          $original_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          $thumbnail_url = $original_url;

          if (!empty($this->options['image']['image_thumbnail_style'])) {
            try {
              $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
              if ($image_style) {
                $thumbnail_url = $image_style->buildUrl($file->getFileUri());
              }
            }
            catch (\Exception $e) {
              \Drupal::logger('album_gallery')->error('Error loading image style: @error',
              ['@error' => $e->getMessage()]);
            }
          }

          return [
            'url' => $original_url,
            'mime_type' => $file->getMimeType(),
            'alt' => $media->get($source_field)->first()->get('alt')->getValue() ?? '',
            'title' => $media->get($source_field)->first()->get('title')->getValue() ?? '',
            'thumbnail' => $thumbnail_url,
          ];
        }
        break;

      case 'video_file':
        $file = $media->get($source_field)->entity;
        $thumbnail = $media->get('thumbnail')->entity;
        if ($file instanceof FileInterface) {
          $thumbnail_url = '';
          if ($thumbnail) {
            $thumbnail_url = $this->fileUrlGenerator->generateAbsoluteString($thumbnail->getFileUri());

            if (!empty($this->options['image']['image_thumbnail_style'])) {
              try {
                $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
                if ($image_style) {
                  $thumbnail_url = $image_style->buildUrl($thumbnail->getFileUri());
                }
              }
              catch (\Exception $e) {
                \Drupal::logger('album_gallery')->error('Error loading image style for video thumbnail: @error',
                ['@error' => $e->getMessage()]);
              }
            }
          }

          return [
            'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
            'mime_type' => $file->getMimeType(),
            'thumbnail' => $thumbnail_url,
            'title' => $media->get($source_field)->first()->get('description')->getValue() ?? '',
          ];
        }
        break;
    }

    return NULL;
  }

  /**
   * Get lightgallery settings for a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array
   *   The lightgallery settings.
   */
  private function getLightgallerySettings(MediaInterface $media) {
    // Get view mode from the unified media field configuration.
    $view_mode = 'default';
    $fields = $this->view->display_handler->getOption('fields');

    if (isset($fields['media_album_av_unified_media']['view_mode'])) {
      $view_mode = $fields['media_album_av_unified_media']['view_mode'];
    }

    // Get display configuration.
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('media', $media->bundle(), $view_mode);

    $node_settings = [];
    if ($display) {
      // Try to get settings from the source field.
      $source_field = $this->getSourceField($media);
      if ($source_field && $display->getComponent($source_field)) {
        $node_settings = $display->getComponent($source_field)['settings'];
      }
    }

    return self::getGeneralSettings($node_settings);
  }

  /**
   * Retrieves the source field configuration from the media entity's source plugin.
   *
   * Logs a warning and skips processing if the source_field
   * configuration is missing.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   *
   * @return string|null
   *   The source field name or NULL if not found.
   */
  private function getSourceField($media) {
    try {
      if (!$media instanceof MediaInterface) {
        \Drupal::logger('album_gallery')->warning('Invalid media entity provided.');
        return NULL;
      }
      $source = $media->getSource();
      if (!$source) {
        \Drupal::logger('album_gallery')->warning('Media entity @id has no source plugin.', ['@id' => $media->id()]);
        return NULL;
      }
      $source_config = $media->getSource()->getConfiguration();
      $source_field = $source_config['source_field'] ?? NULL;
      if (!$source_field) {
        \Drupal::logger('album_gallery')->warning('Media entity @id is missing a source_field configuration.', ['@id' => $media->id()]);
      }
      return $source_field;
    }
    catch (\Exception $e) {
      \Drupal::logger('album_gallery')->error('Error retrieving source field for media @id: @error', ['@id' => $media->id(), '@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Get media image URL.
   */
  protected function getMediaImageUrl($row, $field_name) {
    if (empty($field_name)) {
      return '';
    }

    if (!isset($row->_entity) || !$row->_entity instanceof EntityInterface) {
      return '';
    }

    $source_field = $this->getSourceField($row->_entity->get($field_name)->entity);
    if (!$source_field) {
      \Drupal::logger('album_gallery')->warning('Media @media does not have a valid source field.', ['@media' => $row->_entity->id()]);
      return '';
    }

    try {
      $media_item = $row->_entity->get($field_name)->entity ?? NULL;
      if ($media_item instanceof MediaInterface) {
        switch ($media_item->getSource()->getPluginId()) {
          case 'image':
            if ($media_item->hasField($source_field) && !$media_item->get($source_field)->isEmpty()) {
              $file = $media_item->get($source_field)->entity;
              if ($file) {
                // Vérifier si un style d'image est défini dans les options.
                if (!empty($this->options['image']['image_thumbnail_style'])) {
                  $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
                  if ($image_style) {
                    // Générer l'URL avec le style d'image.
                    return $image_style->buildUrl($file->getFileUri());
                  }
                }
                // Fallback: URL originale si aucun style n'est défini.
                return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
              }
              else {
                return $this->getDefaultImageUrl('Document_error.png');
              }
            }
            break;

          case 'video_file':
            if ($media_item->hasField($source_field) && !$media_item->get($source_field)->isEmpty()) {
              $thumbnail = $media_item->get('thumbnail')->entity;
              if ($thumbnail && $thumbnail instanceof FileInterface) {
                // Appliquer aussi le style aux miniatures vidéo si besoin.
                if (!empty($this->options['image']['image_thumbnail_style'])) {
                  $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
                  if ($image_style) {
                    return $image_style->buildUrl($thumbnail->getFileUri());
                  }
                }
                return $this->fileUrlGenerator->generateAbsoluteString($thumbnail->getFileUri());
              }
              else {
                return $this->getDefaultImageUrl('Video_error.png');
              }
            }
            else {
              return $this->getDefaultImageUrl('Video.png');
            }
            break;
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('album_gallery')->error('Media field error: @error', ['@error' => $e->getMessage()]);
    }

    return '';
  }

  /**
   * Helper method to get default image URLs.
   */
  protected function getDefaultImageUrl($filename) {
    return \Drupal::request()->getSchemeAndHttpHost() . '/' .
            \Drupal::service('extension.list.module')->getPath('lightgallery') .
            '/images/' . $filename;
  }

  /**
   *
   */
  protected function getTextAndMediaFields() {
    $text_fields = [];
    $media_fields = [];
    $taxo_fields = [];

    /* ********************************* */
    $field_manager = \Drupal::service('entity_field.manager');
    $handlers = $this->displayHandler->getHandlers('field');
    $relationships = $this->displayHandler->getHandlers('relationship');

    // 1. Type d'entité de base de la vue
    $base_entity_type = $this->view->getBaseEntityType()->id();

    // 2. Parcourir les champs
    foreach ($handlers as $field_id => $handler) {
      if (!($handler instanceof EntityField)) {
        continue;
      }

      $field_system_name = $handler->field;
      $field_label = $handler->label();
      $field_name = $handler->definition['title'] ?? $field_system_name;

      // 3. Déterminer l'entité source du champ
      $entity_type_id = $base_entity_type;
      $relationship_id = $handler->options['relationship'] ?? 'none';

      \Drupal::logger('lightgallery_debug')->info('Traitement champ @field_id (@field_name)', [
        '@field_id' => $field_id,
        '@field_name' => $field_system_name,
      ]);

      if ($relationship_id !== 'none' && isset($relationships[$relationship_id])) {
        \Drupal::logger('lightgallery_debug')->info('RELATIONSHIP détectée: @rel_id', ['@rel_id' => $relationship_id]);

        $relationship = $relationships[$relationship_id];

        // IMPORTANT: Le champ du handler ($handler->field) est déjà le bon nom
        // C'est la RELATIONSHIP qui porte la référence à l'entité cible.
        // 1. Récupérer l'entity type CIBLE de la relationship
        // La relationship définit vers quel type d'entité elle pointe.
        $relationship_definition = $relationship->definition;

        \Drupal::logger('lightgallery_debug')->info('Relationship definition: @def', [
          '@def' => json_encode($relationship_definition),
        ]);

        // Méthode 1 : Via la base table de la relationship.
        if (isset($relationship_definition['relationship']['base'])) {
          $base_table = $relationship_definition['relationship']['base'];

          \Drupal::logger('lightgallery_debug')->info('Base table trouvée: @table', ['@table' => $base_table]);

          // Extraire l'entity type depuis la table.
          if (preg_match('/^([a-z_]+)_field_data$/', $base_table, $matches)) {
            // 'media', 'node', 'user', etc.
            $target_entity_type = $matches[1];
            \Drupal::logger('lightgallery_debug')->info('Entity type extrait via regex 1: @type', ['@type' => $target_entity_type]);
          }
          elseif (preg_match('/^([a-z_]+)__/', $base_table, $matches)) {
            $target_entity_type = $matches[1];
            \Drupal::logger('lightgallery_debug')->info('Entity type extrait via regex 2: @type', ['@type' => $target_entity_type]);
          }
          else {
            // Table mapping pour les cas standards.
            $table_map = [
              'media_field_data' => 'media',
              'node_field_data' => 'node',
              'users_field_data' => 'user',
              'taxonomy_term_field_data' => 'taxonomy_term',
              'file_managed' => 'file',
            ];
            $target_entity_type = $table_map[$base_table] ?? NULL;
            \Drupal::logger('lightgallery_debug')->info('Entity type via table_map: @type (NULL si non trouvé)', ['@type' => $target_entity_type ?? 'NULL']);
          }
        }
        else {
          \Drupal::logger('lightgallery_debug')->warning('Base table NOT FOUND in relationship definition');
        }

        // Méthode 2 : Via la configuration du champ d'entity_reference.
        if (!isset($target_entity_type)) {
          \Drupal::logger('lightgallery_debug')->info('Entity type non trouvé via Méthode 1, essai Méthode 2 (field configuration)');

          // Extraire le nom du champ depuis la table de la relationship.
          // Format standard: {entity_type}__{field_name} ou {entity_type}_field_{field_name}
          // Ex: node__field_media_album_av_media.
          $relationship_table = $relationship->table;
          \Drupal::logger('lightgallery_debug')->info('Relationship table: @table', ['@table' => $relationship_table]);

          $relationship_field_name = NULL;

          // Pattern 1: entity__field_name (champs multi-value)
          if (preg_match('/^([a-z_]+)__(.+)$/', $relationship_table, $matches)) {
            $relationship_field_name = $matches[2];
            \Drupal::logger('lightgallery_debug')->info('Field name extrait du pattern multi-value: @fname', ['@fname' => $relationship_field_name]);
          }
          // Pattern 2: entity_field_name (champs single-value dans les anciennes versions)
          elseif (preg_match('/^([a-z_]+)_field_(.+)$/', $relationship_table, $matches)) {
            $relationship_field_name = 'field_' . $matches[2];
            \Drupal::logger('lightgallery_debug')->info('Field name extrait du pattern single-value: @fname', ['@fname' => $relationship_field_name]);
          }

          if ($relationship_field_name) {
            $base_field_storages = $field_manager->getFieldStorageDefinitions($base_entity_type);

            \Drupal::logger('lightgallery_debug')->info('Recherche du champ @fname dans @entity_type', [
              '@fname' => $relationship_field_name,
              '@entity_type' => $base_entity_type,
            ]);

            if (isset($base_field_storages[$relationship_field_name])) {
              $rel_field_storage = $base_field_storages[$relationship_field_name];
              $rel_field_type = $rel_field_storage->getType();
              $rel_field_settings = $rel_field_storage->getSettings();

              \Drupal::logger('lightgallery_debug')->info('Relationship field type: @type, settings: @settings', [
                '@type' => $rel_field_type,
                '@settings' => json_encode($rel_field_settings),
              ]);

              // Si c'est un entity_reference, extraire le target_type.
              if ($rel_field_type === 'entity_reference' && isset($rel_field_settings['target_type'])) {
                $target_entity_type = $rel_field_settings['target_type'];
                \Drupal::logger('lightgallery_debug')->info('✓ Target entity type extrait du champ: @type', ['@type' => $target_entity_type]);
              }
              else {
                \Drupal::logger('lightgallery_debug')->warning('Field @fname n\'est pas un entity_reference ou n\'a pas de target_type', ['@fname' => $relationship_field_name]);
              }
            }
            else {
              \Drupal::logger('lightgallery_debug')->warning('Field @fname NOT FOUND dans @entity_type', [
                '@fname' => $relationship_field_name,
                '@entity_type' => $base_entity_type,
              ]);
            }
          }
          else {
            \Drupal::logger('lightgallery_debug')->warning('Impossible d\'extraire le field name depuis la table: @table', ['@table' => $relationship_table]);
          }
        }

        // Méthode 3 : Via views_data en dernier recours.
        if (!isset($target_entity_type)) {
          \Drupal::logger('lightgallery_debug')->info('Entity type non trouvé via Méthode 2, essai Méthode 3 (views_data)');

          $views_data = \Drupal::service('views.views_data');
          $relationship_table = $relationship->table;
          \Drupal::logger('lightgallery_debug')->info('Relationship table: @table', ['@table' => $relationship_table]);

          $table_data = $views_data->get($relationship_table);

          if ($table_data && isset($table_data['table']['entity type'])) {
            $target_entity_type = $table_data['table']['entity type'];
            \Drupal::logger('lightgallery_debug')->info('Entity type trouvé via views_data: @type', ['@type' => $target_entity_type]);
          }
          else {
            \Drupal::logger('lightgallery_debug')->warning('Entity type NOT FOUND via views_data');
          }
        }

        // 2. Le champ à chercher est celui du HANDLER, pas de la relationship
        $field_system_name = $handler->field;

        // 3. Récupérer les field storages de l'entité CIBLE
        if (isset($target_entity_type)) {
          \Drupal::logger('lightgallery_debug')->info('Chargement field storages pour entity type: @type', ['@type' => $target_entity_type]);

          $field_storages = $field_manager->getFieldStorageDefinitions($target_entity_type);

          \Drupal::logger('lightgallery_debug')->info('Field storages chargées. Recherche du champ: @field', ['@field' => $field_system_name]);

          if (isset($field_storages[$field_system_name])) {
            \Drupal::logger('lightgallery_debug')->info('✓ Champ @field TROUVÉ dans entity type @type', [
              '@field' => $field_system_name,
              '@type' => $target_entity_type,
            ]);
          }
          else {
            \Drupal::logger('lightgallery_debug')->warning('✗ Champ @field NOT FOUND dans entity type @type. Champs disponibles: @fields', [
              '@field' => $field_system_name,
              '@type' => $target_entity_type,
              '@fields' => implode(', ', array_keys($field_storages)),
            ]);
          }
        }
        else {
          \Drupal::logger('lightgallery_debug')->error('ERREUR: target_entity_type non défini (les trois méthodes ont échoué)');
        }
      }
      // Si pas de relationship, on utilise l'entité de base.
      else {
        if ($relationship_id !== 'none') {
          \Drupal::logger('lightgallery_debug')->warning('Relationship @rel_id spécifiée mais NOT FOUND', ['@rel_id' => $relationship_id]);
        }

        \Drupal::logger('lightgallery_debug')->info('Pas de relationship, utilisation entity type de base: @type', ['@type' => $entity_type_id]);

        $field_storages = $field_manager->getFieldStorageDefinitions($entity_type_id);
      }

      if (isset($field_storages[$field_system_name])) {
        $field_storage = $field_storages[$field_system_name];
        $field_type = $field_storage->getType();
        $settings = $field_storage->getSettings();

        if (in_array($field_type, ['string', 'text', 'text_long', 'text_with_summary'])) {
          $text_fields[$field_system_name] = (string) $field_name;
        }
        elseif (
              $field_type === 'entity_reference' &&
              $settings['target_type'] === 'media'
          ) {
          $media_fields[$field_system_name] = (string) $field_name;
        }
        elseif (
              $field_type === 'entity_reference' &&
              $settings['target_type'] === 'taxonomy_term'
          ) {
          $taxo_fields[$field_system_name] = (string) $field_name;
        }
      }
      /*
      $field_options[$field_id] = [
      'label' => $field_label,
      'system_name' => $field_system_name,
      'type' => $field_type,
      'entity_type' => $entity_type_id,
      'relationship' => $relationship_id,
      'settings' => $settings,
      ];

      // Pour les entity_reference.
      if ($field_type === 'entity_reference') {
      $field_options[$field_id]['target_type'] = $settings['target_type'] ?? NULL;
      }
       */
    }
    /* ********************************* */
    return [$text_fields, $media_fields, $taxo_fields];

    $entity_type_id = $view->getBaseEntityType()->id();
    // Retreive bundle from view filters options.
    $bundle = $view->display_handler->getOption('filters')['type']['value'] ?? NULL;
    if (is_array($bundle)) {
      $bundle = reset($bundle);
    }

    // 2. load field definitions for the entity and bundle.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);

    // 3. Browse fields in the view and match with definitions.
    foreach ($view->display_handler->getHandlers('field') as $field_id => $handler) {
      $field_name = $handler->field ?? NULL;
      if ($field_name && isset($field_definitions[$field_name])) {
        $field_def = $field_definitions[$field_name];
        $type = $field_def->getType();

        // 4. test field type.
        if (in_array($type, ['string', 'text', 'text_long', 'text_with_summary'])) {
          $text_fields[$field_name] = (string) $field_def->getLabel();
        }
        elseif (
              $type === 'entity_reference' &&
              $field_def->getSetting('target_type') === 'media'
          ) {
          $media_fields[$field_name] = (string) $field_def->getLabel();
        }
        elseif (
              $type === 'entity_reference' &&
              $field_def->getSetting('target_type') === 'taxonomy_term'
          ) {
          $taxo_fields[$field_name] = (string) $field_def->getLabel();
        }
      }
      else {
        // Pas de fieldDefinition → champ calculé ou relation virtuelle
        // On peut essayer de deviner à partir du plugin_id ou table.
        $plugin_id = $handler->getPluginId();

        if (in_array($plugin_id, ['entity_reference_label'])) {
          $taxo_fields[$field_name] = $handler->adminLabel() ?: $field_name;
        }
        elseif (in_array($plugin_id, ['field', 'media_album_av_unified_media'])) {
          $media_fields[$field_name] = $handler->adminLabel() ?: $field_name;
        }
        else {
          // Par défaut, on peut le considérer comme texte.
          $text_fields[$field_name] = $handler->adminLabel() ?: $field_name;
        }
      }
    }

    return [$text_fields, $media_fields, $taxo_fields];
  }

  /**
   * Retreives text, taxonomy, media, and number fields from the view.
   *
   * @param bool $hidden
   *   Whether to include hidden fields.
   *   True to exclude hidden fields.
   *
   * @return array
   *   An array containing arrays of text fields, media fields, taxonomy fields, and number
   */
  protected function getTextAndMediaFieldsCentric($hidden = FALSE) {

    $a = $this->getAvailableFields($hidden);
    // Récupérer tous les champs de la vue.
    $fields = $this->displayHandler->getHandlers('field');

    // Préparer les options pour les champs texte.
    $text_fields = [];
    $taxonomy_fields = [];
    $media_fields = [];
    $number_fields = [];

    foreach ($fields as $field_name => $field) {

      $is_excluded = $hidden && !empty($field->options['exclude']);

      if ($is_excluded) {
        continue;
      }

      $label = $field->adminLabel() ?: $field_name;

      // Identifier le type de champ.
      $plugin_id = $field->getPluginId();
      $field_definition = $field->definition ?? [];
      $field_identifier = $field->field ?? NULL;

      // Champs de base de l'entité media (name, created, etc.)
      // On vérifie le nom du champ dans la table.
      if (isset($field->table) && $field->table === 'media_field_data') {
        // Champ name (titre du media)
        if ($field_identifier === 'name') {
          $text_fields[$field_name] = [
            'label' => $label,
            'excluded' => $is_excluded,
          ];
        }
      }
      // Champs texte.
      if (in_array($plugin_id, ['field']) && isset($field->definition['field_name'])) {
        $field_storage_config = \Drupal::entityTypeManager()
          ->getStorage('field_storage_config')
          ->load('media.' . $field->definition['field_name']);

        if ($field_storage_config) {
          $field_type = $field_storage_config->getType();

          switch ($field_type) {
            case 'string':
            case 'string_long':
            case 'text':
            case 'text_long':
            case 'text_with_summary':
              $text_fields[$field_name] = $label;
              break;

            case 'entity_reference':
              // Vérifier si c'est une taxonomie.
              $settings = $field_storage_config->getSettings();
              if (isset($settings['target_type']) && $settings['target_type'] === 'taxonomy_term') {
                $taxonomy_fields[$field_name] = $label;
              }
              break;

            case 'integer':
            case 'decimal':
            case 'float':
              $number_fields[$field_name] = $label;
              break;

            case 'image':
            case 'file':
              $media_fields[$field_name] = $label;
              break;
          }
        }
      }

      // Champs spéciaux (comme votre media_album_av_unified_media)
      if ($plugin_id === 'media_album_av_unified_media') {
        $media_fields[$field_name] = $label;
      }

      // Champs de type entity_reference_label (taxonomies)
      if ($plugin_id === 'entity_reference_label') {
        $taxonomy_fields[$field_name] = $label;
      }

      // Champs de type number.
      if ($plugin_id === 'number_integer' || $plugin_id === 'number_decimal') {
        $number_fields[$field_name] = $label;
      }
    }
    return [$text_fields, $media_fields, $taxonomy_fields, $number_fields];
  }

  /**
   *
   */
  public function getAvailableFields(bool $hidden = FALSE): array {

    $handlers = $this->displayHandler->getHandlers('field');

    foreach ($handlers as $field_name => $handler) {
      $a = $this->getFieldInfo($handler);
    }

    $out = [
      'text' => [],
      'media' => [],
      'taxonomy' => [],
      'number' => [],
      'date' => [],
    ];

    return $out;
  }

  /**
   *
   */
  protected function getFieldInfo(EntityField $entityField) {
    $field_name = $entityField->field;
    $entity_type = $entityField->getEntityType();

    // Récupérer la définition du stockage du champ.
    $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);

    if ($field_storage) {
      return [
        'name' => $field_name,
        'type' => $field_storage->getType(),
        'entity_type' => $entity_type,
        'settings' => $field_storage->getSettings(),
        'cardinality' => $field_storage->getCardinality(),
      ];
    }

    return NULL;
  }

  /**
   * Retrieves the value of a field for a specific row in the view.
   *
   * @param int $index
   *   The row index.
   * @param string $field
   *   The field name.
   *
   * @return mixed
   *   The field value.
   */
  public function getFieldValue($index, $field) {
    $filters = $this->view->display_handler->getOption('filters');

    $bundle = NULL;
    foreach (['type', 'bundle', 'media_bundle'] as $key) {
      if (!empty($filters[$key]['value'])) {
        $bundle = $filters[$key]['value'];
        if (is_array($bundle)) {
          $bundle = reset($bundle);
        }
        break;
      }
    }

    // 1. Retrieve base table.
    $base_table = $this->view->storage->get('base_table');

    // 2. table to entity type mapping.
    $table_to_entity = [
      'node_field_data' => 'node',
      'media_field_data' => 'media',
      'user_field_data' => 'user',
      'taxonomy_term_field_data' => 'taxonomy_term',
      // Add other mappings as needed.
    ];
    $entity_type_id = $table_to_entity[$base_table] ?? $base_table;

    // 3. Get the row entity directly.
    $row_entity = $this->view->result[$index]->_entity ?? NULL;
    if (!$row_entity || !$row_entity->hasField($field)) {
      return '';
    }

    // 4. Get field definition.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    $field_definition = $field_definitions[$field] ?? NULL;

    if (!$field_definition) {
      return '';
    }

    $type = $field_definition->getType();

    // Text field - render via field handler if exists, otherwise get raw value.
    if (in_array($type, ['string', 'text', 'text_long', 'text_with_summary'])) {
      // Check if field is in view's field handlers.
      if (isset($this->view->field[$field])) {
        $this->view->row_index = $index;
        $value = $this->view->field[$field]->getValue($this->view->result[$index]);
        unset($this->view->row_index);
        return $value;
      }
      // Fallback: get raw value from entity.
      else {
        $field_value = $row_entity->get($field);
        if (!$field_value->isEmpty()) {
          return $field_value->first()->getValue()['value'] ?? '';
        }
      }
    }
    // Taxonomy term reference field.
    elseif ($type === 'entity_reference' && $field_definition->getSetting('target_type') === 'taxonomy_term') {
      $labels = [];
      foreach ($row_entity->get($field) as $item) {
        if ($item->entity) {
          $labels[] = $item->entity->label();
        }
      }
      // Array of labels to comma-separated string.
      return implode(', ', $labels);
    }

    return '';
  }

}
