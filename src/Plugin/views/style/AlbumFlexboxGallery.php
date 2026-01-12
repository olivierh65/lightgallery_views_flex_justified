<?php

namespace Drupal\lightgallery_views_flex_justified\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;



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
    [$fields_text, $fields_media, $fields_taxo, $fields_number] = $this->getTextAndMediaFields(TRUE);

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

    // Get grouped results from Views.
    $grouped_rows = $this->renderGrouping(
    $this->view->result,
    $this->options['grouping'],
    TRUE
    );

    // Recursively process the grouped structure.
    $build['#groups'] = $this->processGroupRecursive($grouped_rows,
        $build,
        $build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']);

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
   * {@inheritdoc}
   * Override to ensure the value used for grouping is a
   * simple string, thus avoiding the 'TypeError' caused by using a
   * Markup object as an array key.
   */
  public function renderGrouping($records, $groupings = [], $group_rendered = NULL) {

    // 1. Force the use of the raw field value for grouping.
    $cleaned_grouping = $groupings;
    if (!empty($cleaned_grouping)) {
      foreach ($cleaned_grouping as $key => $grouping_info) {
        if (is_array($grouping_info) && isset($grouping_info['rendered'])) {
          // This line is crucial: it tells the parent method not to use the HTML rendering.
          $cleaned_grouping[$key]['rendered'] = FALSE;
        }
      }
    }

    // 2. Avoid executing the backward compatibility block (where the error occurs).
    // This forces $group_rendered to a non-NULL value, disabling the buggy loop.
    $group_rendered_fixed = $group_rendered ?? FALSE;

    // Call the parent method with the corrected parameters.
    return parent::renderGrouping($records, $cleaned_grouping, $group_rendered_fixed);
  }

}
