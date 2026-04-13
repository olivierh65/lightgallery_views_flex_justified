<?php

namespace Drupal\lightgallery_views_flex_justified\Plugin\views\style;

/**
 * Style plugin to render each item in a Fancybox grid layout.
 *
 * @ViewsStyle(
 * id = "album_fancybox_grid",
 * title = @Translation("Album Fancybox Grid"),
 * help = @Translation("Display albums in a grid layout with Fancybox."),
 * theme = "album_fancybox_grid",
 * display_types = {"normal"}
 * )
 */
class AlbumFancyboxGrid extends AlbumFancyboxGallery {

  /**
   * En héritant, on récupère toute la logique de préparation des données.
   */
  public function render() {
    $build = parent::render();
    $build['#attached']['library'][] = 'lightgallery_views_flex_justified/fancybox_grid';
    return $build;
  }

}
