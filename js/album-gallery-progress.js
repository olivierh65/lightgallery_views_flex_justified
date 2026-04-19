(function ($, Drupal, drupalSettings, once) {
  "use strict";

  if (typeof $ !== "function") {
    console.error("jQuery non disponible dans album-gallery-progress.js");
    return;
  }

  Drupal.behaviors.albumGalleryProgress = {
    attach: function (context, settings) {
      const cfg = drupalSettings.albumGalleryProgress || null;

      if (cfg && cfg.enabled && cfg.token && !window._albumGalleryStarted) {
        window._albumGalleryStarted = true;

        const $modal = AlbumGalleryProgress.openModal();
        const $target = $("#album-gallery-target");

        // Réinitialiser le stockage HTML/settings.
        AlbumGalleryProgress._html = null;
        AlbumGalleryProgress._drupalSettings = null;

        // Lancer le rendu AJAX (requête longue).
        AlbumGalleryProgress.startRender(cfg.renderUrl, $modal, $target);

        // Poller le statut en parallèle.
        setTimeout(function () {
          AlbumGalleryProgress.poll(cfg.token, $modal, $target);
        }, 300);
      }
    },
  };

  window.AlbumGalleryProgress = {
    _html: null,
    _drupalSettings: null,

    startRender: function (renderUrl, $modal, $target) {
      const self = this;

      $.getJSON(renderUrl)
        .done(function (data) {
          self._html = data.html || "";
          self._drupalSettings = data.drupalSettings || null;
        })
        .fail(function () {
          self.error($modal, Drupal.t("Erreur lors du chargement."));
        });
    },

    poll: function (token, $modal, $target) {
      const self = this;

      $.getJSON(Drupal.url("album-gallery/status/" + token))
        .done(function (data) {
          self.updateProgress($modal, data);

          if (data.status === "complete") {
            $modal.find(".album-loading-bar").css("width", "100%");
            $modal.find(".album-loading-message").text(Drupal.t("Terminé !"));
            self.waitForHtml($modal, $target, 0);
          } else {
            setTimeout(function () {
              self.poll(token, $modal, $target);
            }, 500);
          }
        })
        .fail(function () {
          setTimeout(function () {
            self.poll(token, $modal, $target);
          }, 1000);
        });
    },

    waitForHtml: function ($modal, $target, attempts) {
      const self = this;

      if (self._html !== null) {
        setTimeout(function () {
          self.close($modal, $target, self._html, self._drupalSettings);
        }, 400);
      } else if (attempts < 20) {
        setTimeout(function () {
          self.waitForHtml($modal, $target, attempts + 1);
        }, 200);
      } else {
        self.error(
          $modal,
          Drupal.t("Timeout lors de la récupération du rendu."),
        );
      }
    },

    openModal: function () {
      const $modal = $(
        '<div id="album-progress-modal">' +
          '<div class="album-loading-spinner"></div>' +
          '<p class="album-loading-message">' +
          Drupal.t("Initialisation...") +
          "</p>" +
          '<div class="album-loading-bar-wrapper">' +
          '<div class="album-loading-bar" style="width:0%"></div>' +
          "</div>" +
          '<span class="album-loading-detail"></span>' +
          "</div>",
      );

      Drupal.dialog($modal[0], {
        title: Drupal.t("Chargement de la galerie"),
        width: 420,
        closeOnEscape: false,
        buttons: [],
      }).showModal();

      return $modal;
    },

    updateProgress: function ($modal, data) {
      $modal
        .find(".album-loading-bar")
        .css("width", (data.progress || 0) + "%");

      if (data.processed && data.total) {
        $modal.find(".album-loading-message").text(
          Drupal.t("Album @current sur @total", {
            "@current": data.processed,
            "@total": data.total,
          }),
        );
      }
      if (data.current) {
        $modal.find(".album-loading-detail").text(data.current);
      }
    },

    close: function ($modal, $target, html, newSettings) {
      Drupal.dialog($modal[0]).close();

      if (html && $target.length) {
        $target.html(html);

        // Fusionner les drupalSettings avant attachBehaviors.
        if (newSettings) {
          $.extend(true, drupalSettings, newSettings);
        }

        // Désactiver le progress dans drupalSettings pour éviter
        // que attach() ne relance le traitement après attachBehaviors.
        if (drupalSettings.albumGalleryProgress) {
          drupalSettings.albumGalleryProgress.enabled = false;
        }

        Drupal.attachBehaviors($target[0]);

        // Remettre à false APRÈS attachBehaviors.
        window._albumGalleryStarted = false;
      }
    },

    error: function ($modal, message) {
      $modal.find(".album-loading-message").text(message);
      $modal.find(".album-loading-bar").css("background", "#c00");
      $modal.append(
        $('<button class="button">' + Drupal.t("Fermer") + "</button>").on(
          "click",
          function () {
            Drupal.dialog($modal[0]).close();
            window._albumGalleryStarted = false;
          },
        ),
      );
    },
  };
})(jQuery, Drupal, drupalSettings, once);
