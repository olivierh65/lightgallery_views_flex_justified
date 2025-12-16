(function ($, Drupal, drupalSettings, once) {
  // Stockage global des instances Isotope
  const isoInstances = {};

  // =====================================================
  // BEHAVIOR: ISOTOPE ALBUM GALLERY
  // =====================================================
  Drupal.behaviors.isotopeAlbumGallery = {
    attach: function (context, settings) {
      const layout_settings = drupalSettings.settings?.layout || {};

      console.log("=== Isotope Album Gallery Initialization ===");

      // Options Isotope par défaut
      const defaultOptions = {
        itemSelector: ".isotope-item",
        layoutMode: layout_settings.layoutMode || "fitRows", // fitRows pour horizontal
        percentPosition: false,
        transitionDuration: "0.4s",

        fitRows: {
          gutter: parseInt(layout_settings.gutter) || 10,
        },

        masonry: {
          columnWidth: parseInt(layout_settings.columnWidth) || 220,
          gutter: parseInt(layout_settings.gutter) || 10,
          horizontalOrder: layout_settings.horizontalOrder !== false,
          fitWidth: layout_settings.fitWidth || false,
        },

        packery: {
          gutter: parseInt(layout_settings.gutter) || 10,
          columnWidth: parseInt(layout_settings.columnWidth) || 220,
          rowHeight: parseInt(layout_settings.rowHeight) || undefined,
          horizontalOrder: layout_settings.horizontalOrder !== false,
        },
      };

      // Fonction pour initialiser un conteneur Isotope
      function initIsotopeContainer($container) {
        const containerId = $container.attr("id");

        // Vérifications
        if (!containerId) {
          console.warn("Container without ID, skipping");
          return;
        }

        if (isoInstances[containerId]) {
          console.log("Container already initialized:", containerId);
          return;
        }

        const $items = $container.children(".isotope-item");
        if ($items.length === 0) {
          console.warn("No items in container:", containerId);
          return;
        }

        const level = $container.data("level");
        console.log(
          "Initializing Isotope:",
          containerId,
          "- Level:",
          level,
          "- Items:",
          $items.length
        );

        // Initialisation différée pour laisser le DOM se stabiliser
        setTimeout(function () {
          try {
            // Initialiser Isotope
            const iso = $container.isotope(defaultOptions);
            isoInstances[containerId] = iso.data("isotope");

            console.log("✓ Isotope initialized:", containerId);

            // Chercher et initialiser les sous-conteneurs
            // IMPORTANT: chercher dans les enfants directs uniquement
            $items.each(function () {
              const $item = $(this);
              // Chercher le conteneur enfant direct de cet item
              $item.children(".isotope-container").each(function () {
                initIsotopeContainer($(this));
              });
            });

            // Relayout après initialisation des enfants
            setTimeout(function () {
              if (isoInstances[containerId]) {
                isoInstances[containerId].layout();
              }
            }, 100);
          } catch (error) {
            console.error(
              "Error initializing Isotope for",
              containerId,
              ":",
              error
            );
          }
        }, 50);
      }

      // Fonction pour relayout tous les conteneurs
      function relayoutAll() {
        console.log("Relayouting all Isotope instances...");
        Object.keys(isoInstances).forEach(function (key) {
          const instance = isoInstances[key];
          if (instance) {
            try {
              instance.layout();
            } catch (e) {
              console.warn("Could not relayout:", key);
            }
          }
        });
      }

      // Fonction pour relayout en cascade (enfants puis parents)
      function relayoutCascade() {
        const ids = Object.keys(isoInstances);
        // Relayout du plus profond au plus superficiel
        ids.reverse().forEach(function (key) {
          const instance = isoInstances[key];
          if (instance) {
            instance.layout();
          }
        });
      }

      // Chercher le conteneur racine
      const $root = $(
        once("albums-grid-init", ".albums-grid[data-isotope-albums]", context)
      );

      if ($root.length === 0) {
        return;
      }

      // Attendre que les images soient chargées si possible
      if (typeof $.fn.imagesLoaded !== "undefined") {
        $root.imagesLoaded(function () {
          console.log("Images loaded, initializing...");
          initIsotopeContainer($root);

          // Relayout final après un délai
          setTimeout(relayoutCascade, 500);
          setTimeout(relayoutCascade, 1000);
        });
      } else {
        // Initialiser immédiatement
        console.log("imagesLoaded not available, initializing immediately");
        initIsotopeContainer($root);

        // Relayout après un délai pour laisser les images se charger
        setTimeout(relayoutCascade, 1000);
        setTimeout(relayoutCascade, 2000);
      }

      // Relayout au redimensionnement (debounced)
      let resizeTimer;
      $(window).on("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
          console.log("Window resized, relayouting...");
          relayoutCascade();
        }, 250);
      });

      // Relayout au chargement complet
      $(window).on("load", function () {
        setTimeout(relayoutCascade, 500);
      });

      // API publique
      window.isotopeGallery = {
        relayout: relayoutAll,
        relayoutCascade: relayoutCascade,
        instances: isoInstances,
      };
    },
  };

  // =====================================================
  // BEHAVIOR: LIGHTGALLERY ALBUMS
  // =====================================================
  Drupal.behaviors.lightgalleryAlbums = {
    attach: function (context, settings) {
      console.log("lightgalleryAlbums behavior attached");

      // Event delegation pour les albums
      $(once("lg-album-init", ".album-cover", context)).each(function () {
        console.log("Binding click event for album cover:", this);

        $(this).on("click", function (e) {
          e.preventDefault();

          const albumId = $(this).data("album-id");
          const $album = $("#" + albumId);
          console.log("Opening album:", albumId, $album);

          if (!$album.length) {
            console.warn("Album container not found:", albumId);
            return;
          }

          if (typeof window.lightGallery !== "function") {
            console.error("LightGallery is not loaded!");
            return;
          }

          const albumSettings =
            drupalSettings.settings.lightgallery?.albums_settings[albumId] ||
            {};

          // Initialize LightGallery if not already done
          if (!$album.data("lightGallery")) {
            const plugins = [];
            Object.values(albumSettings.plugins || {}).forEach((name) => {
              if (window[name]) plugins.push(window[name]);
            });

            const instance = window.lightGallery($album[0], {
              ...albumSettings,
              selector: "a",
              plugins: plugins,
              subHtmlSelectorRelative: true,
            });

            $album.data("lightGallery", instance);
          }

          $album.data("lightGallery").openGallery();
        });
      });
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
