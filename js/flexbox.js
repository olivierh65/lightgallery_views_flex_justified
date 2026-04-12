(function ($, Drupal, drupalSettings, once) {
  /**
   * Album Gallery Behavior - Flexbox Layout with LightGallery
   *
   * LightGallery provides the lightbox functionality.
   */

  Drupal.behaviors.lightgalleryAlbums = {
    attach: function (context, settings) {
      console.log("=== Album Gallery (Flexbox + LightGallery) ===");

      // update .flexbox-item.album-item heights based on image sizes
      const albumSettings = drupalSettings.settings.lightgallery || {};
      const itemSelector = ".flexbox-item.album-item";
      $(once("lg-flexbox-adjust", itemSelector, context)).each(function () {
        const $item = $(this);
        $item.attr("width", albumSettings.thumbnail_width || "200");
        $item.attr("height", albumSettings.thumbnail_height || "200");
      });

      // Initialize LightGallery on album covers
      $(once("lg-album-init", ".album-cover", context)).each(function () {
        $(this).on("click", function (e) {
          e.preventDefault();

          const albumId = $(this).data("album-id");
          const $album = $("#" + albumId);

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

              // Lazy loading
              lazyLoadEager: 1, // Précharger seulement 1 slide adjacent (au lieu de preload:2)
              preload: 1, // Remplace le preload:2 des albumSettings

              // Thumbnails lazy loading
              loadYouTubeThumbnail: false,
              thumbLoadEager: 2, // Charger seulement 2 miniatures visibles au départ
            });

            $album.data("lightGallery", instance);
          }

          // Open the gallery
          $album.data("lightGallery").openGallery();
        });
      });

      console.log("✓ Album gallery initialized (Flexbox + LightGallery)");
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
