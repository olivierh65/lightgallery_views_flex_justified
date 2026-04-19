(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.fancyboxAlbums = {
    attach: function (context, settings) {
      console.log("=== Album Gallery (Flexbox + Fancybox) ===");

      if (!drupalSettings.settings?.albumFancybox) {
        console.log(
          "Album Fancybox settings not found, skipping initialization.",
        );
        return;
      }

      const albumSettings = drupalSettings.settings.albumFancybox || {};

      const itemSelector = ".flexbox-item.album-item";

      // Ajustement dimensions thumbnails (inchangé)
      $(once("fb-flexbox-adjust", itemSelector, context)).each(function () {
        const $item = $(this);
        $item.attr("width", albumSettings.thumbnail_width || "200");
        $item.attr("height", albumSettings.thumbnail_height || "200");
      });

      // Initialisation Fancybox
      $(once("fb-init", ".album-cover", context)).each(function () {
        $(this).on("click", function (e) {
          e.preventDefault();

          const albumId = $(this).data("album-id");
          const $album = $("#" + albumId);

          if (!$album.length) {
            console.warn("Album container not found:", albumId);
            return;
          }

          if (!window.Fancybox || typeof window.Fancybox.show !== "function") {
            console.error("Fancybox is not loaded!");
            return;
          }

          // Associer les items à une galerie Fancybox
          const $links = $album.find("a.fancybox__item");

          if (!$links.length) {
            console.warn("No items found in album:", albumId);
            return;
          }

          // Ajouter attribut Fancybox si absent
          $links.each(function () {
            if (!$(this).attr("data-fancybox")) {
              $(this).attr("data-fancybox", albumId);
            }
          });

          // Build explicit items to avoid undefined src resolution.
          const items = $links
            .map(function () {
              const $link = $(this);
              const src = $link.attr("href") || $link.data("src");

              // Skip malformed entries; they trigger /undefined requests.
              if (!src || src === "undefined") {
                return null;
              }

              const item = {
                src: src,
                thumb: $link.attr("data-thumb") || undefined,
                caption: $link.attr("data-caption") || "",
              };

              const mediaType = $link.attr("data-type");
              if (mediaType) {
                item.type = mediaType;
              }

              return item;
            })
            .get()
            .filter(Boolean);

          if (!items.length) {
            console.warn("No valid Fancybox items found in album:", albumId);
            return;
          }

          // Ouvrir Fancybox sur le premier élément
          window.Fancybox.show(items, {
            Carousel: {
              preload: 0,
            },
            Images: {
              preload: 0,
            },
            Thumbs: {
              minCount: 1,
              showOnStart: true,
              type: "modern",
            },
          });
        });
      });

      console.log("✓ Album gallery initialized (Fancybox)");
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
