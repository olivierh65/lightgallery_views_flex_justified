<?php

namespace Drupal\lightgallery_views_flex_justified\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\views\Views;

/**
 *
 */
class AlbumGalleryStatusController extends ControllerBase {

  /**
   *
   */
  public function status(Request $request, string $token): JsonResponse {
    $data = \Drupal::service('tempstore.private')
      ->get('album_gallery')
      ->get($token);

    if (!$data) {
      return new JsonResponse(['status' => 'waiting', 'progress' => 0]);
    }

    return new JsonResponse($data);
  }

  /**
   *
   */
  public function menuOpen(string $view_id, string $display_id, string $term_id): array {

    // Générer le token ici.
    $render_token = Crypt::randomBytesBase64(12);

    // Initialiser tempstore immédiatement.
    \Drupal::service('tempstore.private')
      ->get('album_gallery')
      ->set($render_token, [
        'status'   => 'waiting',
        'progress' => 0,
        'total'    => 0,
        'current'  => '',
      ]);

    return [
      '#theme'    => 'album_gallery_page',
      '#attached' => [
        'library' => [
          'lightgallery_views_flex_justified/fancybox',
          'lightgallery_views_flex_justified/album_gallery_progress',
        ],
        'drupalSettings' => [
          'albumGalleryProgress' => [
            'token'     => $render_token,
            'enabled'   => TRUE,
            'renderUrl' => \Drupal::service('url_generator')->generateFromRoute(
            'album_gallery.render',
            ['view_id' => $view_id, 'display_id' => $display_id, 'term_id' => $term_id],
            ['query' => ['token' => $render_token]]
            ),
          ],
        ],
      ],
    ];
  }

  /**
   *
   */
  public function renderView(Request $request, string $view_id, string $display_id, string $term_id): JsonResponse {

    $render_token = $request->query->get('token', '');

    $view = Views::getView($view_id);
    if (!$view) {
      return new JsonResponse(['error' => 'View not found'], 404);
    }

    $view->setDisplay($display_id);
    if ($term_id !== 'all') {
      $view->setArguments([$term_id]);
    }
    $view->execute();

    try {
      $view->style_plugin->setRenderToken($render_token);
      $build = $view->style_plugin->render();
    }
    catch (\Throwable $e) {
      $tempstore = \Drupal::service('tempstore.private')->get('album_gallery');

      // Écrire l'erreur dans tempstore pour que le polling JS l'affiche.
      $tempstore->set($render_token, [
        'status'  => 'error',
        'message' => $e->getMessage(),
      ]);

      \Drupal::logger('album_gallery')->error(
        'Error rendering view: @message', ['@message' => $e->getMessage()]
      );

      return new JsonResponse(['status' => 'error', 'error' => $e->getMessage()], 500);
    }

    $drupal_settings = $build['#attached']['drupalSettings'] ?? [];

    $html = (string) \Drupal::service('renderer')->renderRoot($build);

    return new JsonResponse([
      'status' => 'complete',
      'html'   => $html,
      'drupalSettings' => $drupal_settings,
    ]);
  }

}
