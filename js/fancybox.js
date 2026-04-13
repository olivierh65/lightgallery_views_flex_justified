(function ($, Drupal, drupalSettings, once) {

  Drupal.behaviors.fancyboxAlbums = {
    attach: function (context, settings) {
      console.log("=== Album Gallery (Flexbox + Fancybox) ===");

      const albumSettings = drupalSettings.settings.fancybox || {};
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

          if (typeof window.Fancybox !== "function") {
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

          // Ouvrir Fancybox sur le premier élément
          Fancybox.show(
            $links.map(function () {
              return {
                src: $(this).attr("href"),
                thumb: $(this).find("img").attr("src"),
                caption: $(this).find(".fancybox_caption").html() || "",
              };
            }).get(),
            {
              Thumbs: {
                autoStart: true, // active le carrousel thumbnails
              },
              Images: {
                preload: 1, // limite le preload → évite surcharge serveur
              },
            }
          );

        });

      });

      console.log("✓ Album gallery initialized (Fancybox)");
    },
  };

})(jQuery, Drupal, drupalSettings, window.once);
