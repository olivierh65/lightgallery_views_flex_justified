<?php

namespace Drupal\lightgallery_views_flex_justified\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_album_av_common\Service\AlbumGroupingConfigService;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Crypt;
use Drupal\lightgallery_views_flex_justified\Traits\ProcessAlbumTrait;

/**
 * Album Gallery style plugin.
 *
 * @ViewsStyle(
 *   id = "album_fancybox_gallery",
 *   title = @Translation("Album Fancybox Gallery"),
 *   help = @Translation("Displays albums with a Fancybox layout."),
 *   theme = "album_fancybox_gallery",
 *   display_types = {"normal"}
 * )
 */
class AlbumFancyboxGallery extends StylePluginBase {
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


  protected string $renderToken = '';

  /**
   * Constructs an AlbumFancyboxGallery style plugin instance.
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
   * Creates an instance of the AlbumFancyboxGallery style plugin.
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
   *
   */
  public function setRenderToken(string $token): void {
    $this->renderToken = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $id = rand();

    // Token unique pour ce rendu — permet au JS de poller l'avancement.
    $render_token = $this->renderToken;
    $tempstore    = \Drupal::service('tempstore.private')->get('album_gallery');

    // Phase initiale — requête SQL Views déjà exécutée.
    $total_rows = count($this->view->result);
    if ($tempstore && !empty($render_token)) {
      $tempstore->set($render_token, [
        'status'   => 'processing',
        'phase'    => 'starting',
        'message'  => 'Démarrage...',
        'progress' => 2,
        'detail'   => $total_rows . ' résultats SQL',
      ]);
    }

    ['width' => $max_width, 'height' => $max_height] = $this->getImageStyleDimensions($this->options['image']['image_thumbnail_style'] ?? '');

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => [],
      '#attributes' => [
        'class' => ['album-fancybox-gallery', 'fancybox-init'],
      ],
      '#attached' => [
        'library' => [
          'lightgallery_views_flex_justified/fancybox',
        ],
        'drupalSettings' => [
          'settings' => [
            'albumFancybox' => [
              'inline' => FALSE,
              'galleryId' => $id,
              'thumbnail_width' => $max_width,
              'thumbnail_height' => $max_height,
              'albums_settings' => [],
            ],
          ],
          // Token transmis au JS pour le polling.
          'albumGalleryProgress' => [
            'token'   => $render_token,
            'enabled' => TRUE,
          ],
        ],
      ],
    ];

    // Vérifier si NID est présent dans les résultats.
    $nid_field = $this->getNidFieldName();

    if ($nid_field) {
      $build['#groups'] = $this->renderWithPerNodeGrouping(
      $nid_field,
      $build,
      $build['#attached']['drupalSettings']['settings']['albumFancybox']['albums_settings'],
      $render_token
      );
    }
    else {
      $grouped_rows     = $this->renderGrouping($this->view->result, $this->options['grouping'], FALSE);
      $build['#groups'] = $this->processGroupRecursive(
      $grouped_rows,
      $build,
      $build['#attached']['drupalSettings']['settings']['albumFancybox']['albums_settings'],
      );
    }

    $build['#groups'] = $this->filterEmptyGroups($build['#groups']);
    $build['#groups'] = $this->sortGroupsByNodeGrouping($build['#groups'], $nid_field);

    if ($tempstore && !empty($render_token)) {
      $tempstore->set($render_token, [
        'status'   => 'processing',
        'phase'    => 'rendering',
        'message'  => 'Rendu final...',
        'progress' => 95,
        'detail'   => 'Génération du HTML...',
      ]);
    }

    foreach ($build['#attached']['drupalSettings']['settings']['albumFancybox']['albums_settings']['plugins'] ?? [] as $plugin_name => $plugin) {
      $build['#attached']['library'][] = $plugin;
    }

    $build['#options'] += ['captions' => $this->options['image']['captions'] ?? TRUE];

    $session_id  = \Drupal::service('session_manager')->getId();
    $private_key = \Drupal::service('private_key')->get();
    $render_id   = Crypt::randomBytesBase64(8);
    $s_token     = Crypt::hmacBase64($render_id, $session_id . $private_key);
    $this->injectSecurityTokens($build['#groups'], $s_token, $render_id);

    $build['#cache']['contexts'][] = 'session';

    // Marquer comme terminé.
    $tempstore->set($render_token, [
      'status'   => 'complete',
      'progress' => 100,
      'message'  => 'Terminé',
    ]);

    unset($this->view->row_index);
    return $build;
  }

  /**
   * Inject security tokens recursively into the groups structure.
   *
   * @param array $groups
   *   The groups array to process.
   * @param string $s_token
   *   The generated HMAC token.
   * @param string $render_id
   *   The random render ID.
   */
  private function injectSecurityTokens(array &$groups, string $s_token, string $render_id) {
    foreach ($groups as &$group) {
      // On injecte les tokens au niveau du groupe.
      $group['s_token'] = $s_token;
      $group['s_id'] = $render_id;

      // Si le groupe contient des albums, on les marque aussi.
      if (!empty($group['albums'])) {
        foreach ($group['albums'] as &$album) {
          $album['s_token'] = $s_token;
          $album['s_id'] = $render_id;
        }
      }

      // Si on a des sous-groupes, on descend d'un niveau (récursivité)
      if (!empty($group['subgroups'])) {
        $this->injectSecurityTokens($group['subgroups'], $s_token, $render_id);
      }
    }
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
    \Drupal::logger('album_fancybox_gallery')->info('Form values: @values', [
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

    if ($group_rendered === NULL) {
      $group_rendered = FALSE;
    }

    return parent::renderGrouping($records, $groupings, $group_rendered);
  }

  /**
   * Sort groups by node grouping configuration using terms_rendered.
   *
   * @param array $groups
   *   The groups to sort.
   * @param string|null $nid_field
   *   The NID field name from the view.
   *
   * @return array
   *   The sorted groups.
   */
  protected function sortGroupsByNodeGrouping(array $groups, ?string $nid_field): array {
    if (empty($groups) || !$nid_field) {
      return $groups;
    }

    // Group by nid from albums and recurse into subgroups.
    $nid_to_config = [];

    // Collect all NIDs from albums to build configuration map once.
    $this->collectNidsForConfiguration($groups, $nid_to_config);

    // Sort the groups using the configuration.
    return $this->sortGroupsRecursive($groups, $nid_to_config);
  }

  /**
   * Collect NIDs from groups for configuration loading.
   *
   * @param array $groups
   *   The groups to collect from.
   * @param array &$nid_to_config
   *   Map to populate with NID => configuration.
   */
  private function collectNidsForConfiguration(array $groups, array &$nid_to_config) {
    foreach ($groups as $group) {
      // Collect NIDs from albums.
      if (!empty($group['albums'])) {
        foreach ($group['albums'] as $album) {
          if (!empty($album['nid']) && !isset($nid_to_config[$album['nid']])) {
            $node = Node::load($album['nid']);
            if ($node) {
              $nid_to_config[$album['nid']] = $this->groupingConfigService->getAlbumGroupingFieldsConfig($node);
            }
          }
        }
      }

      // Recurse into subgroups.
      if (!empty($group['subgroups'])) {
        $this->collectNidsForConfiguration($group['subgroups'], $nid_to_config);
      }
    }
  }

  /**
   * Recursively sort groups using node configuration.
   *
   * @param array $groups
   *   The groups to sort.
   * @param array $nid_to_config
   *   Map of NID => configuration.
   *
   * @return array
   *   The sorted groups.
   */
  private function sortGroupsRecursive(array $groups, array $nid_to_config): array {
    // First, collect the NID for this level (from first album if any).
    $nid = NULL;
    foreach ($groups as $group) {
      if (!empty($group['albums'])) {
        foreach ($group['albums'] as $album) {
          if (!empty($album['nid'])) {
            $nid = $album['nid'];
            break 2;
          }
        }
      }
    }

    // Get the configuration for this NID.
    $config = $nid && isset($nid_to_config[$nid]) ? $nid_to_config[$nid] : [];

    // Build config_order: a map where each term label gets its position in the config.
    // For each level in config, we use the order of terms_rendered.
    $config_order = [];
    $position = 0;
    foreach ($config as $level => $field_config) {
      if (!empty($field_config['terms_rendered'])) {
        // terms_rendered maintains the order of term IDs.
        foreach ($field_config['terms_rendered'] as $rendered_label) {
          $config_order[$rendered_label] = $position++;
        }
      }
    }

    // Build two arrays: positioned and unpositioned groups.
    $positioned = [];
    $unpositioned = [];

    foreach ($groups as $group) {
      $title = trim(strip_tags($group['title'] ?? ''));

      if (isset($config_order[$title])) {
        // Position this group at its configured position.
        $pos = $config_order[$title];
        $positioned[$pos] = $group;
      }
      else {
        // Groups not in config go to unpositioned.
        $unpositioned[] = $group;
      }
    }

    // Sort positioned by position.
    ksort($positioned);

    // Merge: positioned groups first (in order), then unpositioned (alphabetical).
    usort($unpositioned, function ($a, $b) {
      $title_a = trim(strip_tags($a['title'] ?? ''));
      $title_b = trim(strip_tags($b['title'] ?? ''));
      return strcmp($title_a, $title_b);
    });

    $sorted = array_merge($positioned, $unpositioned);

    // Recursively sort subgroups.
    foreach ($sorted as &$group) {
      if (!empty($group['subgroups'])) {
        $group['subgroups'] = $this->sortGroupsRecursive($group['subgroups'], $nid_to_config);
      }
    }

    return $sorted;
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
  protected function renderWithPerNodeGrouping($nid_field, array &$build, array &$lightgallery_settings, string $render_token = '') {

    $tempstore = !empty($render_token)
    ? \Drupal::service('tempstore.private')->get('album_gallery')
    : NULL;

    // ÉTAPE 1 : Groupement manuel par NID + collecte des MIDs depuis les
    // propriétés SQL de la row (déjà dans le résultat, zéro requête).
    if ($tempstore) {
      $tempstore->set($render_token, [
        'status'  => 'processing',
        'phase'   => 'grouping',
        'message' => 'Analyse des albums...',
        'progress' => 5,
      ]);
    }

    $grouped_by_nid = [];
    $all_mids       = [];

    foreach ($this->view->result as $index => $row) {
      $nid = $row->nid ?? ($row->_entity?->id() ?? NULL);
      if (!$nid) {
        continue;
      }

      $grouped_by_nid[$nid]['rows'][$index] = $row;

      // Le MID est déjà dans la row grâce au JOIN SQL de Views.
      $mid_key = 'media_field_data_node__field_media_album_av_media_mid';
      $mid = $row->$mid_key ?? NULL;
      if ($mid) {
        $all_mids[(int) $mid] = (int) $mid;
        $grouped_by_nid[$nid]['mids'][(int) $mid] = (int) $mid;
      }
    }

    $total      = count($grouped_by_nid);
    $total_mids = count($all_mids);

    // Mettre à jour avec les totaux maintenant connus.
    if ($tempstore) {
      $tempstore->set($render_token, [
        'status'       => 'processing',
        'phase'        => 'loading_media',
        'message'      => 'Chargement des médias...',
        'progress'     => 10,
        'total_albums' => $total,
        'total_medias' => $total_mids,
        'processed'    => 0,
        'total'        => $total,
        'current'      => '',
        'detail'       => $total . ' albums, ' . $total_mids . ' médias',
      ]);
    }

    // ÉTAPE 2 : Charger TOUS les médias en une seule requête.
    $all_media_entities = !empty($all_mids)
      ? \Drupal::entityTypeManager()->getStorage('media')->loadMultiple($all_mids)
      : [];

    if ($tempstore) {
      $tempstore->set($render_token, [
        'status'       => 'processing',
        'phase'        => 'processing_albums',
        'message'      => 'Préparation des albums...',
        'progress'     => 15,
        'total_albums' => $total,
        'total_medias' => $total_mids,
        'processed'    => 0,
        'total'        => $total,
        'current'      => '',
        'detail'       => $total_mids . ' médias chargés',
      ]);
    }

    // ÉTAPE 3 : Traiter chaque album.
    $all_groups = [];
    $processed  = 0;

    foreach ($grouped_by_nid as $nid => $nid_data) {
      $rows = $nid_data['rows'];
      if (empty($rows)) {
        continue;
      }

      // Node déjà chargé par Views dans _entity — pas de Node::load().
      $first_row = reset($rows);
      $node = $first_row->_entity ?? NULL;
      if (!$node) {
        continue;
      }

      $processed++;
      $album_media_count = count($nid_data['mids'] ?? []);
      // Écrire la progression dans tempstore.
      if ($tempstore) {
        $tempstore->set($render_token, [
          'status'       => 'processing',
          'phase'        => 'processing_albums',
          'message'      => 'Traitement album ' . $processed . '/' . $total,
          'progress'     => 15 + round($processed / $total * 75),
          'total_albums' => $total,
          'total_medias' => $total_mids,
          'processed'    => $processed,
          'total'        => $total,
          'current'      => $node->label(),
          'detail'       => $album_media_count . ' médias',
        ]);
      }

      // Médias de cet album indexés par MID pour lookup rapide dans les sous-groupes.
      $album_medias = [];
      foreach ($nid_data['mids'] ?? [] as $mid) {
        if (isset($all_media_entities[$mid])) {
          $album_medias[$mid] = $all_media_entities[$mid];
        }
      }

      // ÉTAPE 4 : Sous-groupement spécifique à ce node.
      $node_grouping_fields = $this->groupingConfigService->getAlbumGroupingFields($node);

      if (!empty($node_grouping_fields)) {
        // Groupement par champs de taxonomie du média.
        // On groupe manuellement pour éviter renderGrouping avec rendered=TRUE.
        $grouping = $this->groupingConfigService->convertFieldsToViewGrouping($node_grouping_fields, FALSE);
        $album_groups_raw = $this->renderGrouping($rows, $grouping, FALSE);
        $album_groups_raw = $this->resolveGroupLabels($album_groups_raw);
        $album_groups_processed = $this->processGroupRecursive(
        $album_groups_raw,
        $build,
        $lightgallery_settings,
        0,
        0,
        $album_medias
        );
      }
      else {
        // Pas de sous-groupement : un seul groupe = tout l'album.
        $album_groups_processed = $this->processGroupRecursive(
        [['group' => $node->label(), 'level' => 0, 'rows' => $rows]],
        $build,
        $lightgallery_settings,
        0,
        0,
        $album_medias
        );
      }

      if (!empty($album_groups_processed)) {
        $all_groups = array_merge($all_groups, $album_groups_processed);
      }
    }

    if ($tempstore) {
      $tempstore->set($render_token, [
        'status'       => 'processing',
        'phase'        => 'finalizing',
        'message'      => 'Finalisation...',
        'progress'     => 90,
        'total_albums' => $total,
        'total_medias' => $total_mids,
        'processed'    => $processed,
        'total'        => $total,
        'current'      => '',
        'detail'       => 'Tri et sécurisation...',
      ]);
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
   *
   */
  protected function getGeneralSettings($node_settings) {
    return [];
  }

}
