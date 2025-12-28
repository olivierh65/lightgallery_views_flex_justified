<?php

namespace Drupal\lightgallery_views_flex_justified\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;



use Drupal\image\Entity\ImageStyle;
use Drupal\lightgallery_views_flex_justified\Traits\ProcessAlbumTrait;

/**
 * Album Gallery style plugin.
 *
 * @ViewsStyle(
 *   id = "album_isotope_gallery",
 *   title = @Translation("Album Isotope Gallery"),
 *   help = @Translation("Displays albums with an Isotope layout."),
 *   theme = "album_isotope_gallery",
 *   display_types = {"normal"}
 * )
 */
class AlbumIsotopeGallery extends StylePluginBase {
  use \Drupal\lightgallery_settings_ui\Traits\LightGallerySettingsTrait;
  use ProcessAlbumTrait;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Creates an instance of the AlbumIsotopeGallery style plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('file_url_generator')
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
    // Tolerance for thumbnail size in percentage.
        'thumbnail_size_tolerance' => 10,
        'layout' => 'packery',
        'captions' => TRUE,
      ],
    ];
    $options['gallery'] = [
      'default' => [
        'width' => '30%',
        'columnWidth' => '',
        'rowHeight' => '',
        'gutter' => 6,
        'horizontal' => FALSE,
        'horizontalOrder' => TRUE,
        'fitWidth' => FALSE,
        'border' => TRUE,
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
      // Limit to one grouping level.
      $form['grouping'] = array_slice($form['grouping'], 0, 1, TRUE);
      // Limit to one grouping level.
      $form['grouping'] = array_slice($form['grouping'], 0, 1, TRUE);
    }

    [$fields_text, $fields_media, $fields_taxo] = $this->getTextAndMediaFields($this->view);

    // Field for the description.
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

    $form['image']['thumbnail_size_tolerance'] = [
      '#type' => 'number',
      '#title' => $this->t('Thumbnail size tolerance'),
      '#default_value' => $this->options['gallery']['thumbnail_size_tolerance'],
      '#description' => $this->t('Percentage tolerance for thumbnail size.'),
    ];

    $form['image']['border'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Border'),
      '#default_value' => $this->options['image']['border'] ?? TRUE,
      '#description' => $this->t('Border around each items?'),
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

    // don't add grouping options as they are already defined in the parent class
    // when $this->usesGrouping = TRUE;.
    // Options for the Gallery.
    $form['gallery'] = [
      '#type' => 'details',
      '#title' => $this->t('Gallery settings'),
      '#description' => $this->t('Configure the gallery settings.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['gallery']['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => [
        'packery' => 'Packery',
        'masonry' => 'Masory',
        'fitRows' => 'fit Rows',
        'vertical' => 'Vertical',
      ],
      '#default_value' => $this->options['gallery']['layout'],
    ];

    // Width will be determined by the image size settings.
    /* $form['gallery']['width'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Width'),
    '#default_value' => $this->options['gallery']['width'],
    '#description' => $this->t('Width of the gallery items. Use CSS units like %, px, em, etc.'),
    ];

    $form['gallery']['columnWidth'] = [
    '#type' => 'number',
    '#title' => $this->t('Columns width (px)'),
    '#default_value' => $this->options['gallery']['columnWidth'] ?? '30%',
    '#states' => [
    'visible' => [
    ':input[name="style_options[gallery][layout]"]' => [
    ['value' => 'packery'],
    ['value' => 'masonry'],
    ],
    ],
    ],
    ];

    $form['gallery']['rowHeight'] = [
    '#type' => 'number',
    '#title' => $this->t('Row height (px)'),
    '#default_value' => $this->options['gallery']['rowHeight'] ?? '',
    '#description' => $this->t('Aligns items to the height of a row of a vertical grid.'),
    '#states' => [
    // This field will be visible only if...
    'visible' => [
    ':input[name="style_options[gallery][layout]"]' => ['value' => 'packery'],
    ],
    ],
    ];
     */
    $form['gallery']['gutter'] = [
      '#type' => 'number',
      '#title' => $this->t('Gutter'),
      '#default_value' => $this->options['gallery']['gutter'],
      '#description' => $this->t('The horizontal space between item elements.'),
    ];

    // Horizontal option only for packery layout is forced in css/js.
    /* $form['gallery']["horizontal"] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Horizontal'),
    '#default_value' => $this->options['gallery']['horizontal'] ?? FALSE,
    '#description' => $this->t('Arranges items horizontally instead of vertically.'),
    '#states' => [
    // This field will be visible only if...
    'visible' => [
    ':input[name="style_options[gallery][layout]"]' => ['value' => 'packery'],
    ],
    ],
    ]; */

    $form['gallery']['horizontalOrder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Horizontal order'),
      '#default_value' => $this->options['gallery']['horizontalOrder'] ?? 0,
      '#description' => $this->t('Lays out items to (mostly) maintain horizontal left-to-right order.'),
      '#states' => [
    // This field will be visible only if...
        'visible' => [
          ':input[name="style_options[gallery][layout]"]' => [
                      ['value' => 'masonry'],
                      ['value' => 'vertical'],
          ],
        ],
      ],
    ];

    $form['gallery']['fitWidth'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fit width'),
      '#default_value' => $this->options['gallery']['fitWidth'] ?? FALSE,
      '#description' => $this->t('Sets the width of the container to fit the available number of columns.'),
      '#states' => [
    // This filed will be visible only if...
        'visible' => [
          ':input[name="style_options[gallery][layout]"]' => ['value' => 'masonry'],
        ],
      ],
    ];
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
      '#settings' => [
        'thumbnail_width' => $max_width,
        'thumbnail_height' => $max_height,
      ],
      '#attributes' => [
        'class' => ['album-isotope-gallery', 'lightgallery-init'],
      ],
      '#attached' => [
        'library' => [
          'lightgallery/lightgallery',
          'lightgallery_views_flex_justified/isotope',
          'lightgallery_views_flex_justified/isotope',
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

    // Obtenir les résultats groupés de Views.
    $grouped_rows = $this->renderGrouping(
    $this->view->result,
    $this->options['grouping'],
    TRUE
    );

    // Traiter récursivement la structure groupée.
    $build['#groups'] = $this->processGroupRecursive($grouped_rows,
        $build,
        $build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']);

    foreach ($build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']['plugins'] ?? [] as $plugin_name => $plugin) {
      $build['#attached']['library'][] = $plugin;
    }

    $build['#options'] += [
      'border' => $this->options['image']['border'] ?? TRUE,
      'captions' => $this->options['image']['captions'] ?? TRUE,
      'thumbnail_size_tolerance' => $this->options['image']['thumbnail_size_tolerance'],
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

    // Récupérer le type d'entité et le bundle depuis la vue.
    // ex: 'node'.
    $entity_type = $this->view->storage->get('base_table');
    if ($entity_type === 'node_field_data') {
      $entity_type = 'node';
    }
    elseif ($entity_type === 'media_field_data') {
      $entity_type = 'media';
    }
    // ex: 'article'.
    $bundle = $this->view->display_handler->getOption('filters')['type']['value'] ?? NULL;
    if (is_array($bundle)) {
      // Prendre le premier bundle si c'est un tableau.
      $bundle = reset($bundle);
    }

    // Charger la définition du champ.
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
    \Drupal::logger('album_isotope')->info('Form values: @values', [
      '@values' => json_encode($values['style_options']['grouping'] ?? []),
    ]);
  }

  /**
   * {@inheritdoc}
   * Surcharge pour garantir que la valeur utilisée pour le regroupement est une
   * chaîne simple, évitant ainsi la 'TypeError' due à l'utilisation d'un objet
   * Markup comme clé de tableau.
   */
  public function renderGrouping($records, $groupings = [], $group_rendered = NULL) {

    // 1. Force l'utilisation de la valeur brute du champ pour le regroupement.
    $cleaned_grouping = $groupings;
    if (!empty($cleaned_grouping)) {
      foreach ($cleaned_grouping as $key => $grouping_info) {
        if (is_array($grouping_info) && isset($grouping_info['rendered'])) {
          // Cette ligne est cruciale : elle dit à la méthode parente de ne pas utiliser le rendu HTML.
          $cleaned_grouping[$key]['rendered'] = FALSE;
        }
      }
    }

    // 2. Évite l'exécution du bloc de rétrocompatibilité (où l'erreur se produit).
    // Ceci force $group_rendered à une valeur non-NULL, désactivant la boucle boguée.
    $group_rendered_fixed = $group_rendered ?? FALSE;

    // Appel de la méthode parente avec les paramètres corrigés.
    return parent::renderGrouping($records, $cleaned_grouping, $group_rendered_fixed);
  }

}
