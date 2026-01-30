<?php

namespace Drupal\lightgallery_views_flex_justified\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_album_av_common\Service\AlbumGroupingConfigService;
use Drupal\node\Entity\Node;

use Drupal\image\Entity\ImageStyle;
use Drupal\lightgallery_settings_ui\Traits\LightGallerySettingsTrait as LightGallerySettingsTrait;
use Drupal\lightgallery_views_flex_justified\Traits\ProcessAlbumTrait;

/**
 * Album Gallery style plugin.
 *
 * @ViewsStyle(
 *   id = "album_flexbox_gallery",
 *   title = @Translation("Album Flexbox Gallery"),
 *   help = @Translation("Displays albums with a Flexbox layout."),
 *   theme = "album_flexbox_gallery",
 *   display_types = {"normal"}
 * )
 */
class AlbumFlexboxGallery extends StylePluginBase {
  use LightGallerySettingsTrait;
  use ProcessAlbumTrait;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The grouping config service.
   *
   * @var \Drupal\media_album_av_common\Service\AlbumGroupingConfigService
   */
  protected AlbumGroupingConfigService $groupingConfigService;


  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = TRUE;

  /**
   * Does the style plugin for itself support to add fields to its output.
   *
   * This option only makes sense on style plugins without row plugins, like
   * for example table.
   *
   * @var bool
   */
  protected $usesFields = FALSE;

  /**
   * Constructs an AlbumIsotopeGallery style plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUrlGeneratorInterface $file_url_generator, AlbumGroupingConfigService $grouping_config_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
    $this->groupingConfigService = $grouping_config_service;
  }

  /**
   * Creates an instance of the AlbumIsotopeGallery style plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('file_url_generator'),
          $container->get('media_album_av_common.album_grouping_config')
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['image'] = [
      'default' => [
        'image_field' => NULL,
        'title_field' => NULL,
        'author_field' => NULL,
        'description_field' => NULL,
        'url_field' => NULL,
        'image_thumbnail_style' => 'medium',
      ],
    ];
    $options['lightgallery'] = [
      'default' => [
        'closable' => TRUE,
        'closeOnTap' => FALSE,
        'controls' => TRUE,
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    if (isset($form['grouping']) && is_array($form['grouping'])) {
      // Limit to five grouping levels.
      $form['grouping'] = array_slice($form['grouping'], 0, 5, TRUE);
    }

    // Get available fields from the view, excluding hidden fields.
    [$fields_text, $fields_media, $fields_taxo] = $this->getTextAndMediaFields();

    // Image styles for thumbnails.
    $image_styles = ImageStyle::loadMultiple();
    foreach ($image_styles as $style => $image_style) {
      $image_thumbnail_style[$image_style->id()] = $image_style->label();
    }
    $default_style = '';
    if (isset($this->options['image']['image_thumbnail_style']) && $this->options['image']['image_thumbnail_style']) {
      $default_style = $this->options['image']['image_thumbnail_style'];
    }
    elseif (isset($image_styles['image']['medium'])) {
      $default_style = 'medium';
    }
    elseif (isset($image_styles['image']['thumbnail'])) {
      $default_style = 'thumbnail';
    }
    elseif (!empty($image_styles)) {
      $default_style = array_key_first($image_styles);
    }
    $this->options['image']['image_thumbnail_style'] = $default_style;

    // Field for the image.
    $form['image'] = [
      '#type' => 'details',
      '#title' => $this->t('Image settings'),
      '#description' => $this->t('Configure the image settings for the album gallery.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['image']['image_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Image field'),
      '#options' => $fields_media,
      '#default_value' => $this->options['image']['image_field'],
      '#required' => TRUE,
    ];

    $form['image']['image_thumbnail_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Thumbnail style'),
      '#options' => $image_thumbnail_style,
      '#default_value' => $this->options['image']['image_thumbnail_style'],
      '#description' => $this->t('Select an image style to apply to the thumbnails.'),
    ];

    $form['image']['captions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display captions'),
      '#default_value' => $this->options['image']['captions'] ?? TRUE,
      '#description' => $this->t('Display captions for images.'),
    ];

    // Field for the title.
    $form['image']['title_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title field'),
      '#options' => ['' => $this->t('- None -')] + $fields_text,
      '#default_value' => $this->options['image']['title_field'],
      '#required' => FALSE,
    ];

    $form['image']['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description field'),
      '#options' => ['' => $this->t('- None -')] + $fields_text,
      '#default_value' => $this->options['image']['description_field'],
      '#required' => FALSE,
    ];
    // Field for the author.
    $form['image']['author_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Author field'),
      '#options' => ['' => $this->t('- None -')] + $fields_taxo,
      '#default_value' => $this->options['image']['author_field'],
    ];

    // Use album lightgallery settings form.
    // // Field for the LightGallery.
    // // get Core settings only.
    // $definitions = self::getLightGalleryPluginDefinitions();
    // $config = self::getGeneralSettings([]);
    // $form['lightgallery'] = self::buildCoreSettingsForm(
    //   $definitions,
    //   $config ?? [],
    //   [],
    // )['params'];
    // // Rename module settings title.
    // $form['lightgallery']['#title'] = $this->t('LightGallery');
    // foreach ($form['lightgallery'] as $key => $value) {
    //   if (is_array($value)) {
    //     // Remove the config target to avoid overwriting global settings.
    //     unset($form['lightgallery'][$key]['#config_target']);
    //     // Set default value from style options.
    //     $form['lightgallery'][$key]['#default_value'] = $this->options['lightgallery'][$key];
    //   }
    // }.
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $id = rand();

    ['width' => $max_width, 'height' => $max_height] = $this->getImageStyleDimensions($this->options['image']['image_thumbnail_style'] ?? '');

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => [],
      '#attributes' => [
        'class' => ['album-flexbox-gallery', 'lightgallery-init'],
      ],
      '#attached' => [
        'library' => [
          'lightgallery_views_flex_justified/flexbox',
          'lightgallery/lightgallery',
        ],
        'drupalSettings' => [
          'settings' => [
            'lightgallery' => [
              'inline' => FALSE,
              'galleryId' => $id,
              'thumbnail_width' => $max_width,
              'thumbnail_height' => $max_height,
              'albums_settings' => [],
            ],
          ],
        ],
      ],
    ];

    // Vérifier si NID est présent dans les résultats.
    $nid_field = $this->getNidFieldName();

    if ($nid_field) {
      // Mode "regroupement par album" spécifique à chaque node.
      $build['#groups'] = $this->renderWithPerNodeGrouping($nid_field, $build, $build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']);
    }
    else {
      // Mode standard : utiliser les champs de regroupement de la vue.
      $grouped_rows = $this->renderGrouping($this->view->result, $this->options['grouping'], TRUE);
      $build['#groups'] = $this->processGroupRecursive($grouped_rows, $build, $build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']);
    }

    // Filter out empty groups recursively (groups without albums and without subgroups).
    $build['#groups'] = $this->filterEmptyGroups($build['#groups']);

    foreach ($build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']['plugins'] ?? [] as $plugin_name => $plugin) {
      $build['#attached']['library'][] = $plugin;
    }

    $build['#options'] += [
      'captions' => $this->options['image']['captions'] ?? TRUE,
    ];

    unset($this->view->row_index);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $image_field = $form_state->getValue(['style_options', 'image', 'image_field']);
    $handlers = $this->displayHandler->getHandlers('field');

    if (empty($image_field) || !isset($handlers[$image_field])) {
      $form_state->setErrorByName('image_field', $this->t('You must select a valid image/media field.'));
      return;
    }

    // Retrieve the entity type and bundle from the view.
    // e.g., 'node'.
    $entity_type = $this->view->storage->get('base_table');
    if ($entity_type === 'node_field_data') {
      $entity_type = 'node';
    }
    elseif ($entity_type === 'media_field_data') {
      $entity_type = 'media';
    }
    // e.g., 'article'.
    $bundle = $this->view->display_handler->getOption('filters')['type']['value'] ?? NULL;
    if (is_array($bundle)) {
      // Take the first bundle if it's an array.
      $bundle = reset($bundle);
    }

    // Load the field definition.
    if ($entity_type && $bundle) {
      $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
      if (isset($field_definitions[$image_field])) {
        $field_def = $field_definitions[$image_field];
        $type = $field_def->getType();
        if (
              $type !== 'image' &&
              !($type === 'entity_reference' && $field_def->getSetting('target_type') === 'media')
          ) {
          $form_state->setErrorByName('image_field', $this->t('The selected field must be an image or a media reference.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    // Debug: Log what we're trying to save.
    $values = $form_state->getValues();
    \Drupal::logger('album_flexbox_gallery')->info('Form values: @values', [
      '@values' => json_encode($values['style_options']['grouping'] ?? []),
    ]);
  }

  /**
   * Recursively filter out empty groups and albums without medias.
   *
   * Removes groups that have no albums with medias and no non-empty subgroups.
   * Also filters albums that have no medias.
   *
   * @param array $groups
   *   The groups array to filter.
   *
   * @return array
   *   Filtered groups array with empty groups/albums removed.
   */
  private function filterEmptyGroups(array $groups) {
    $filtered = [];

    foreach ($groups as $group) {
      // Filter albums: keep only those with medias.
      $filtered_albums = [];
      if (!empty($group['albums'])) {
        foreach ($group['albums'] as $album) {
          if (!empty($album['medias'])) {
            $filtered_albums[] = $album;
          }
        }
      }

      // Recursively filter subgroups.
      $filtered_subgroups = [];
      if (!empty($group['subgroups'])) {
        $filtered_subgroups = $this->filterEmptyGroups($group['subgroups']);
      }

      // Only keep the group if it has albums with medias OR non-empty subgroups.
      if (!empty($filtered_albums) || !empty($filtered_subgroups)) {
        $group['albums'] = $filtered_albums;
        $group['subgroups'] = $filtered_subgroups;
        $filtered[] = $group;
      }
    }

    return $filtered;
  }

  /**
   * {@inheritdoc}
   * Override to ensure the value used for grouping is a
   * simple string, thus avoiding the 'TypeError' caused by using a
   * Markup object as an array key.
   */
  public function renderGrouping($records, $groupings = [], $group_rendered = NULL) {

    return parent::renderGrouping($records, $groupings, $group_rendered);
  }

  /**
   * Render results grouping by NID first, then by each node's specific grouping config.
   *
   * @param string $nid_field
   *   The field name/key for NID in the view results.
   * @param array &$build
   *   The build array.
   * @param array &$lightgallery_settings
   *   The lightgallery settings reference.
   *
   * @return array
   *   The merged groups from all nodes.
   */
  protected function renderWithPerNodeGrouping($nid_field, array &$build, array &$lightgallery_settings) {
    // PREMIÈRE PASSE : Grouper uniquement par NID.
    $nid_grouping = [
      [
        'field' => $nid_field,
        'rendered' => TRUE,
        'rendered_strip' => FALSE,
      ],
    ];

    $grouped_by_nid = $this->renderGrouping($this->view->result, $nid_grouping, TRUE);

    $all_groups = [];

    // DEUXIÈME PASSE : Pour chaque NID (chaque album)
    foreach ($grouped_by_nid as $nid_group) {
      // Récupérer les rows de ce groupe.
      $rows = $nid_group['rows'] ?? [];
      if (empty($rows)) {
        continue;
      }

      // Extraire le NID depuis la première row.
      $first_row = reset($rows);
      $nid = $this->getFieldValueFromRow($first_row, $nid_field);

      if (!$nid || !is_numeric($nid)) {
        continue;
      }

      $node = Node::load($nid);
      if (!$node) {
        continue;
      }

      // Récupérer la config de regroupement spécifique à ce node.
      $node_grouping_fields = $this->groupingConfigService->getAlbumGroupingFields($node);

      if (!empty($node_grouping_fields)) {
        $grouping = $this->convertFieldsToViewGrouping($node_grouping_fields);
      }
      else {
        // Si pas de config spécifique, ne pas regrouper davantage.
        $grouping = [];
      }

      // Traiter uniquement ces rows avec la config de l'album.
      $result = array_values($rows);
      $result = $rows;
      $album_groups_raw = $this->renderGrouping($result, $grouping, TRUE);

      // IMPORTANT : Appeler processGroupRecursive sur CE sous-groupe spécifique.
      $album_groups_processed = $this->processGroupRecursive($album_groups_raw, $build, $lightgallery_settings);

      // Wrapper dans un niveau parent (l'album) - level -1 pour le différencier.
      if (!empty($album_groups_processed)) {
        /* $all_groups[] = [
        // Ou juste $node->getTitle() si vous ne voulez pas de lien.
        'title' => $node->toLink()->toString(),
        // Niveau spécial pour l'album (géré dans votre Twig)
        'level' => -1,
        'groupid' => 'album-node-' . $nid,
        'subgroups' => $album_groups_processed,
        'albums' => [],
        'nid' => $nid,
        ]; */
        $all_groups = array_merge($all_groups, $album_groups_processed);
      }
    }

    return $all_groups;
  }

  /**
   * Trouve le nom du champ NID dans les handlers de la vue.
   *
   * @return string|null
   *   Le nom du champ ou NULL si non trouvé.
   */
  protected function getNidFieldName() {
    foreach ($this->displayHandler->getHandlers('field') as $field_name => $handler) {
      // Cherche nid dans field ou realField.
      if (isset($handler->field) && $handler->field === 'nid') {
        return $field_name;
      }
      if (isset($handler->realField) && $handler->realField === 'nid') {
        return $field_name;
      }
    }
    return NULL;
  }

  /**
   * Récupère la valeur d'un champ depuis une row.
   *
   * @param object $row
   *   The view row.
   * @param string $field_name
   *   The field name/key.
   *
   * @return mixed
   *   The field value.
   */
  protected function getFieldValueFromRow($row, $field_name) {
    // Si c'est une propriété directe (nid sur node)
    if (isset($row->$field_name)) {
      return $row->$field_name;
    }
    // Si c'est dans _entity.
    if (isset($row->_entity) && $row->_entity->hasField($field_name)) {
      return $row->_entity->get($field_name)->value;
    }
    // Si c'est dans le rendered output (plus complexe, nécessite le field handler)
    if (isset($this->view->field[$field_name])) {
      return $this->view->field[$field_name]->getValue($row);
    }
    return NULL;
  }

  /**
   * Convertit les champs de regroupement du service en format attendu par renderGrouping.
   *
   * @param array $grouping_fields
   *   Array de champs préfixés (ex: ['node:field_event', 'media:field_author']).
   *
   * @return array
   *   Format attendu par $this->options['grouping'].
   */
  protected function convertFieldsToViewGrouping(array $grouping_fields) {
    $grouping = [];

    foreach ($grouping_fields as $delta => $prefixed_field) {
      // Retirer le préfixe node: ou media:
      $clean_field = preg_replace('/^(node|media):/', '', $prefixed_field);

      $grouping[$delta] = [
        'field' => $clean_field,
        'rendered' => TRUE,
        'rendered_strip' => FALSE,
      ];
    }

    return $grouping;
  }

}
