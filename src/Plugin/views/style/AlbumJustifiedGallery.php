<?php

namespace Drupal\lightgallery_views_flex_justified\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Xss;


use Drupal\image\Entity\ImageStyle;
use Drupal\lightgallery_views_flex_justified\Traits\ProcessAlbumTrait;

/**
 * Album Justified Gallery style plugin.
 *
 * @ViewsStyle(
 *   id = "album_justified_gallery",
 *   title = @Translation("Album Justified Gallery"),
 *   help = @Translation("Displays albums in a justified gallery layout."),
 *   theme = "album_justified_gallery",
 *   display_types = {"normal"}
 * )
 */
class AlbumJustifiedGallery extends StylePluginBase {
  use \Drupal\lightgallery_settings_ui\Traits\LightGallerySettingsTrait;
  use ProcessAlbumTrait;


  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Indicates whether the style uses a row plugin.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Creates an instance of the AlbumJustifiedGallery style plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   Returns an instance of the style plugin.
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
    $options['rowHeight'] = ['default' => 200];
    $options['margins'] = ['default' => 10];
    $options['lastRow'] = ['default' => 'justify'];

    $options['image_field'] = ['default' => ''];
    $options['title_field'] = ['default' => ''];
    $options['author_field'] = ['default' => ''];
    $options['url_field'] = ['default' => ''];

    $options['ThumbnailStyle'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

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

    // Field for the image/media.
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

    // Field for the author.
    $form['image']['author_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Author field'),
      '#options' => ['' => $this->t('- None -')] + $fields_taxo,
      '#default_value' => $this->options['image']['author_field'],
    ];

    // Justified Gallery Options.
    // height get from image style max height.
    /*
    $form['rowHeight'] = [
    '#type' => 'number',
    '#title' => $this->t('Row height (px)'),
    '#default_value' => $this->options['rowHeight'],
    ];
     */

    $form['maxRowsCount'] = [
      '#type' => 'number',
      '#title' => $this->t('Max rows count'),
      '#default_value' => $this->options['maxRowsCount'] ?? 0,
      '#description' => $this->t('Maximum number of rows to display. Set to 0 for no limit.'),
    ];

    $form['margins'] = [
      '#type' => 'number',
      '#title' => $this->t('Margins'),
      '#default_value' => $this->options['margins'],
      '#description' => $this->t('Margin between items in pixels.'),
    ];
    $form['border'] = [
      '#type' => 'number',
      '#title' => $this->t('Border'),
      '#default_value' => $this->options['border'] ?? -1,
      '#description' => $this->t('Border around each item in pixels.'),
    ];

    $form['lastRow'] = [
      '#type' => 'select',
      '#title' => $this->t('Last row behavior'),
      '#options' => [
        'justify' => $this->t('Justify'),
        'nojustify' => $this->t('No justify'),
        'hide' => $this->t('Hide'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $this->options['lastRow'],
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
      '#rows' => [],
      '#options' => $this->options,
      '#attributes' => [
        'class' => ['album-justified-gallery', 'lightgallery-init'],
      ],
      '#attached' => [
        'library' => [
          'lightgallery/lightgallery',
          'lightgallery_views_flex_justified/justified-gallery',
        ],
        'drupalSettings' => [
          'settings' => [
            'lightgallery' => [
              'inline' => FALSE,
              'galleryId' => $id,
              'albums_settings' => [],
            ],
            'justifiedGallery' => [
              'rowHeight' => $max_height ?? 220,
              'maxRowsCount' => $this->options['maxRowsCount'] ?? 0,
              'border' => $this->options['border'] ?? -1,
              'captions' => $this->options['captions'] ?? TRUE,
              'margins' => $this->options['margins'],
              'lastRow' => $this->options['lastRow'],
            ],
          ],
        ],
      ],
    ];

    /*
    foreach ($this->view->result as $index => $row) {
    $this->view->row_index = $index;

    // Get first media as presentation image.
    $image_url = $this->getMediaImageUrl($row, $this->options['image_field']);

    // Text fields.
    $title = !empty($this->options['title_field']) ? $this->getFieldValue($index, $this->options['title_field']) : '';
    $author = !empty($this->options['author_field']) ? $this->getFieldValue($index, $this->options['author_field']) : '';
    $description = !empty($this->options['description_field']) ? $this->getFieldValue($index, $this->options['description_field']) : '';
    $url = Url::fromRoute('entity.node.canonical', ['node' => $row->nid])->toString();

    // Get all media items associated with the row's entity.
    $medias = [];
    if (
    isset($row->_entity)
    && $row->_entity instanceof
    EntityInterface            && $row->_entity->hasField($this->options['image_field'])
    ) {
    foreach ($row->_entity->get($this->options['image_field']) as $media_item) {
    $media = $media_item->entity;
    try {
    switch ($media->getSource()->getPluginId()) {
    case 'image':
    // Image media type.
    $source_field = $media->getSource()->getConfiguration()['source_field'] ?? 'field_media_image';
    $file = $media->get($source_field)->entity;
    if ($file instanceof FileInterface) {

    $medias[] = [
    'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
    'mime_type' => $file->getMimeType(),
    'alt' => $media->get($source_field)->first()->get('alt')->getValue() ?? '',
    'title' => $media->get($source_field)->first()->get('title')->getValue() ?? '',
    'thumbnail' => ImageStyle::load($this->options['image_thumbnail_style'])->buildUrl($file->getFileUri())
    ?? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
    ];
    }
    break;

    case 'video_file':
    // Video file media type.
    $source_field = $media->getSource()->getConfiguration()['source_field'] ?? 'field_media_video_file';
    $file = $media->get($source_field)->entity;
    $thumbnail = $media->get('thumbnail')->entity;
    if ($file instanceof FileInterface) {
    $medias[] = [
    'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
    'mime_type' => $file->getMimeType(),
    'thumbnail' => $thumbnail ? $this->fileUrlGenerator->generateAbsoluteString($thumbnail->getFileUri()) : '',
    'title' => $media->get($source_field)->first()->get('description')->getValue() ?? '',
    ];
    }
    break;

    // @todo Add support for other media types if needed.
    default:
    // Unknown media type, log a warning.
    \Drupal::logger('album_justified_gallery')->warning('Unsupported media type: @type', ['@type' => $media->getSource()->getPluginId()]);
    break;
    }
    }
    catch (\Exception $e) {
    \Drupal::logger('album_justified_gallery')->error('Error processing media item: @error', ['@error' => $e->getMessage()]);
    }
    }
    }

    $renderer = $this->view->display_handler->getOption('fields')[$this->options['image_field']]['settings']['view_mode'];
    $display = \Drupal::service('entity_display.repository')->getViewDisplay('node', $row->_entity->bundle(), $renderer ?? 'default');
    if ($display && $display->getComponent($this->options['image_field'])) {
    $node_settings = $display->getComponent($this->options['image_field'])['settings'];
    }
    else {
    $node_settings = [];
    }

    $album_id = 'album-item-' . rand();
    // Build the settings for the album
    // Should be the sames settings for all albums,
    // as they use the same formatter/renderer.
    $build['#attached']['drupalSettings']['lightgallery']['albums'][$album_id] = self::getGeneralSettings($node_settings);
    // Build plugins list and attach libraries.
    $plugins_mapping = self::getPluginsLibrary();
    $build['#attached']['drupalSettings']['lightgallery']['albums'][$album_id]['plugins'] = [];
    foreach ($node_settings['lightgallery_settings']['plugins'] ?? [] as $plugin_name => $plugin) {
    if (isset($plugin['enabled']) && $plugin['enabled'] == FALSE) {
    // Skip disabled plugins.
    continue;
    }
    $build['#attached']['library'][] = 'lightgallery/lightgallery-' . $plugin_name ?? $plugin_name;
    $build['#attached']['drupalSettings']['lightgallery']['albums'][$album_id]['plugins'][] = $plugins_mapping[$plugin_name] ?? $plugin_name;
    }

    $build['#rows'][] = [
    'image_url' => $image_url,
    'title' => $title,
    'author' => $author,
    'description' => $description,
    'url' => $url,
    'medias' => $medias,
    'id' => $album_id,
    ];
    }
     */
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
    $build['#rows'] = $build['#groups'][0]['albums'] ?? [];

    $build['#options'] += [
      'border' => $this->options['image']['border'] ?? TRUE,
      'captions' => $this->options['image']['captions'] ?? TRUE,
      'thumbnail_size_tolerance' => $this->options['image']['thumbnail_size_tolerance'] ?? 0,
    ];

    unset($this->view->row_index);
    return $build;
  }

  /**
   * Converts node settings to JSON-encoded strings for safe rendering.
   *
   * @param array $node_settings
   *   The node settings array to process.
   *
   * @return array
   *   The processed settings with arrays JSON-encoded and strings filtered.
   */
  private function buildJSONSettings($node_settings) {
    $settings = [];
    foreach ($node_settings as $key => $value) {
      if (is_array($value)) {
        $settings[$key] = json_encode($value);
      }
      else {
        $settings[$key] = PlainTextOutput::renderFromHtml(Xss::filter($value));
      }
    }
    return $settings;
  }

  /**
   *
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $image_field = $form_state->getValue(['style_options', 'image', 'image_field']);
    $handlers = $this->displayHandler->getHandlers('field');

    if (empty($image_field) || !isset($handlers[$image_field])) {
      $form_state->setErrorByName('image_field', $this->t('You must select a valid image/media field.'));
      return;
    }

    // Retrieve entity type and bundle from the view
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
      // Get first bundle if it's an array.
      $bundle = reset($bundle);
    }

    // Load field definitions and check field type.
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

}
