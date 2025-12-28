(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.lightgalleryAlbums = {
    attach: function (context, settings) {
      console.log("lightgalleryAlbums behavior attached");
      const justifiedGalleryOptions = drupalSettings.settings
        ?.justifiedGallery || {
        rowHeight: 200,
        maxRowHeight: '200%',
        maxRowsCount: 0,
        border: -1,
        captions: true,
        margins: 5,
        lastRow: "justify",
      };
      // Force captions à être un booléen
      if (typeof justifiedGalleryOptions.captions !== "undefined") {
        justifiedGalleryOptions.captions = !!justifiedGalleryOptions.captions;
      }

      $("#albums-gallery").justifiedGallery(justifiedGalleryOptions);
      $(once("lg-album", ".album-cover", context)).on("click", function (e) {
        e.preventDefault();
        var albumId = $(this).data("album-id");
        var $album = $("#" + albumId);

        // Récupère les settings spécifiques à cet album
        const albumSettings = drupalSettings.settings.lightgallery?.albums_settings[albumId] || {};

        if ($album.length && typeof window.lightGallery === "function") {
          if (!$album.data("lightGallery")) {
            // Initialisation native
            const plugins = [];
            Object.values(albumSettings.plugins || {}).forEach((name) => {
              if (window[name]) plugins.push(window[name]);
            });

            const instance = window.lightGallery($album[0], {
              ...albumSettings,
              selector: "a",
              plugins: plugins,
              subHtmlSelectorRelative: true,
              // autres options...
            });
            $album.data("lightGallery", instance);
          }
          // Ouvre la galerie
          $album.data("lightGallery").openGallery();
        } else {
          console.error("LightGallery is not loaded!");
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
