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
            });

            // Après ouverture, différer le chargement des thumbnails non visibles.
$album[0].addEventListener('lgAfterOpen', () => {
  lazyLoadThumbnails();
});

            $album.data("lightGallery", instance);
          }

          // Open the gallery
          $album.data("lightGallery").openGallery();
        });
      });

      function lazyLoadThumbnails() {
  const thumbContainer = document.querySelector('.lg-thumb-outer');
  if (!thumbContainer || thumbContainer.dataset.lazyInit) return;
  thumbContainer.dataset.lazyInit = 'true';

  const PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
  const VISIBLE_COUNT = 6;
  const MAX_CONCURRENT = 3; // Maximum de requêtes simultanées.

  let activeRequests = 0;
  const queue = [];

  // Charger une image avec contrôle de concurrence.
  function loadImage(img) {
    return new Promise((resolve) => {
      const src = img.dataset.lazySrc;
      if (!src) return resolve();

      activeRequests++;
      const tempImg = new Image();

      tempImg.onload = tempImg.onerror = () => {
        img.src = src;
        delete img.dataset.lazySrc;
        activeRequests--;
        processQueue(); // Lancer le suivant dans la file.
        resolve();
      };
      tempImg.src = src;
    });
  }

  // Traiter la file d'attente.
  function processQueue() {
    while (activeRequests < MAX_CONCURRENT && queue.length > 0) {
      const img = queue.shift();
      loadImage(img);
    }
  }

  // Ajouter une image à la file d'attente.
  function enqueue(img) {
    if (activeRequests < MAX_CONCURRENT) {
      loadImage(img);
    } else {
      queue.push(img);
    }
  }

  // Différer toutes les thumbnails au-delà de VISIBLE_COUNT.
  const items = thumbContainer.querySelectorAll('.lg-thumb-item');
  items.forEach((item, index) => {
    if (index < VISIBLE_COUNT) return;
    const img = item.querySelector('img');
    if (!img || !img.src || img.src === PLACEHOLDER) return;
    img.dataset.lazySrc = img.src;
    img.src = PLACEHOLDER;
  });

  // Charger les premières VISIBLE_COUNT via la file (limitées à MAX_CONCURRENT).
  items.forEach((item, index) => {
    if (index >= VISIBLE_COUNT) return;
    const img = item.querySelector('img');
    if (img && img.src && img.src !== PLACEHOLDER) {
      // Remplacer temporairement pour passer par la file.
      const realSrc = img.src;
      img.dataset.lazySrc = realSrc;
      img.src = PLACEHOLDER;
      enqueue(img);
    }
  });

  // Observer pour les thumbnails hors viewport.
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const img = entry.target.querySelector('img[data-lazy-src]');
      if (img) {
        enqueue(img); // Passer par la file au lieu de charger directement.
      }
      observer.unobserve(entry.target);
    });
  }, {
    root: thumbContainer,
    rootMargin: '0px 300px',
    threshold: 0,
  });

  items.forEach((item, index) => {
    if (index >= VISIBLE_COUNT) {
      observer.observe(item);
    }
  });
}

      console.log("✓ Album gallery initialized (Flexbox + LightGallery)");
    },
  };
})(jQuery, Drupal, drupalSettings, window.once);
