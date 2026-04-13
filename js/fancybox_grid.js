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
            $links
              .map(function () {
                return {
                  src: $(this).attr("href"),
                  thumb: $(this).find("img").attr("src"),
                  caption: $(this).find(".fancybox_caption").html() || "",
                };
              })
              .get(),
            {
              Thumbs: {
                autoStart: true, // active le carrousel thumbnails
              },
              Images: {
                preload: 1, // limite le preload → évite surcharge serveur
              },
            },
          );
        });
      });

      /**
       * Calcule le nombre de 'spans' (lignes de 10px) nécessaires pour chaque album
       */
      function resizeMasonryItem(item) {
        const grid = document.querySelector(".flexbox-container");
        if (!grid) return;

        // On récupère la hauteur de la ligne (10px) et l'écart
        const rowHeight = parseInt(
          window.getComputedStyle(grid).getPropertyValue("grid-auto-rows"),
        );
        const rowGap = parseInt(
          window.getComputedStyle(grid).getPropertyValue("grid-row-gap"),
        );

        // On calcule le span basé sur la hauteur réelle du contenu de la carte
        // Le +10 à la fin sert de petit padding de sécurité
        const rowSpan = Math.ceil(
          (item.getBoundingClientRect().height + rowGap) / (rowHeight + rowGap),
        );

        item.style.gridRowEnd = "span " + rowSpan;
      }

      function resizeAllMasonryItems() {
        const allItems = document.querySelectorAll(".album-item");
        allItems.forEach(resizeMasonryItem);
      }

      // 1. On lance au chargement
      window.addEventListener("load", resizeAllMasonryItems);

      // 2. On relance si on redimensionne la fenêtre (responsive)
      window.addEventListener("resize", resizeAllMasonryItems);

      // 3. CRUCIAL : On relance quand les images sont vraiment chargées
      // (car une image non chargée a une hauteur de 0)
      document.querySelectorAll(".album-img").forEach((img) => {
        if (img.complete) {
          resizeMasonryItem(img.closest(".album-item"));
        } else {
          img.addEventListener("load", () => {
            resizeMasonryItem(img.closest(".album-item"));
          });
        }
      });

      console.log("✓ Album gallery initialized (Fancybox)");
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
