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
        AlbumGalleryProgress._renderDone = false;
        AlbumGalleryProgress._renderFailed = false;
        AlbumGalleryProgress._pollingActive  = false;

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
      self._html = null;
      self._drupalSettings = null;
      self._renderDone = false;
      self._renderFailed = false; // ← nouveau flag d'échec

      $.getJSON(renderUrl)
        .done(function (data) {
          self._html = data.html || "";
          self._drupalSettings = data.drupalSettings || null;
          self._renderDone = true;
        })
        .fail(function (jqXHR) {
          // La requête HTTP a échoué — signaler l'échec pour débloquer le polling.
          self._renderFailed = true;
          console.error(
            "Render request failed (HTTP " +
              jqXHR.status +
              ") — polling will handle the error display.",
          );
        });
    },

    poll: function (token, $modal, $target) {
      const self = this;

      // Arrêter si le polling a été désactivé (fermeture manuelle du modal).
      if (!self._pollingActive) {
        console.log("Polling arrêté.");
        return;
      }

      $.getJSON(Drupal.url("album-gallery/status/" + token))
        .done(function (data) {
          // Vérifier à nouveau après la réponse AJAX (fermeture pendant la requête).
          if (!self._pollingActive) return;

          self.updateProgress($modal, data);

          if (data.status === "error") {
            self._pollingActive = false;
            self.error(
              $modal,
              data.message || Drupal.t("Une erreur est survenue."),
            );
          } else if (data.status === "complete") {
            if (self._renderDone) {
              self._pollingActive = false;
              $modal.find(".album-loading-bar").css("width", "100%");
              $modal.find(".album-loading-message").text(Drupal.t("Terminé !"));
              self.waitForHtml($modal, $target, 0);
            } else if (self._renderFailed) {
              self._pollingActive = false;
              self.error(
                $modal,
                Drupal.t("Erreur lors du chargement de la galerie."),
              );
            } else {
              setTimeout(function () {
                self.poll(token, $modal, $target);
              }, 200);
            }
          } else {
            setTimeout(function () {
              self.poll(token, $modal, $target);
            }, 500);
          }
        })
        .fail(function () {
          if (!self._pollingActive) return;
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
      const self = this;

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

      const dialog = Drupal.dialog($modal[0], {
        title: Drupal.t("Chargement de la galerie"),
        width: 420,
        closeOnEscape: false,
        buttons: [],
      });

      // Écouter la fermeture du dialog (croix, Escape, etc.)
      $modal[0].addEventListener("dialogclose", function () {
        if (window._albumGalleryStarted) {
          console.log("Modal fermé manuellement — arrêt du polling.");
          self._pollingActive = false;
          self.cleanup();
          window._albumGalleryStarted = false;
        }
      });

      dialog.showModal();
      self._pollingActive = true; // ← activer le polling

      return $modal;
    },

    updateProgress: function ($modal, data) {
      $modal
        .find(".album-loading-bar")
        .css("width", (data.progress || 0) + "%");

      if (data.message) {
        $modal.find(".album-loading-message").text(data.message);
      } else if (data.processed && data.total) {
        $modal.find(".album-loading-message").text(
          Drupal.t("Album @current sur @total", {
            "@current": data.processed,
            "@total": data.total,
          }),
        );
      }

      // Ligne de détail : nom de l'album courant + compteur médias.
      const details = [];
      if (data.current) {
        details.push(data.current);
      }
      if (data.detail) {
        details.push(data.detail);
      }
      // Infos globales si disponibles.
      if (
        data.total_albums &&
        data.total_medias &&
        data.phase === "loading_media"
      ) {
        details.push(
          Drupal.t("@albums albums, @medias médias", {
            "@albums": data.total_albums,
            "@medias": data.total_medias,
          }),
        );
      }

      $modal.find(".album-loading-detail").text(details.join(" — "));
    },

    close: function ($modal, $target, html, newSettings) {
      this._pollingActive = false;
      Drupal.dialog($modal[0]).close();

      // Nettoyer le tempstore.
      this.cleanup();

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
      this._pollingActive = false;
      $modal.find(".album-loading-message").text(message);
      $modal.find(".album-loading-bar").css("background", "#c00");

      // Nettoyer le tempstore en cas d'erreur aussi.
      this.cleanup();

      // N'ajouter le bouton que s'il n'existe pas déjà.
      if (!$modal.find(".album-error-close").length) {
        $modal.append(
          $(
            '<button class="button album-error-close">' +
              Drupal.t("Fermer") +
              "</button>",
          ).on("click", function () {
            Drupal.dialog($modal[0]).close();
            window._albumGalleryStarted = false;
          }),
        );
      }
    },

    cleanup: function () {
      const cfg = drupalSettings.albumGalleryProgress || null;
      if (!cfg || !cfg.token) return;

      $.ajax({
        url: Drupal.url("album-gallery/cleanup/" + cfg.token),
        method: "POST",
        // Fire and forget — pas besoin d'attendre la réponse.
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
