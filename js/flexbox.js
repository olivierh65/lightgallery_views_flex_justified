(function ($, Drupal, drupalSettings, once) {

  Drupal.behaviors.lightgalleryAlbums = {
    attach: function (context, settings) {
      console.log("=== Album Gallery (Flexbox + LightGallery) ===");

      const albumSettings = drupalSettings.settings.lightgallery || {};
      const itemSelector = ".flexbox-item.album-item";
      $(once("lg-flexbox-adjust", itemSelector, context)).each(function () {
        const $item = $(this);
        $item.attr("width", albumSettings.thumbnail_width || "200");
        $item.attr("height", albumSettings.thumbnail_height || "200");
      });

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
            drupalSettings.settings.lightgallery?.albums_settings[albumId] || {};

          if (!$album.data("lightGallery")) {
            const plugins = [];
            Object.values(albumSettings.plugins || {}).forEach((name) => {
              if (window[name]) plugins.push(window[name]);
            });

            // Avant d'initialiser lightgallery, remplacer data-thumb par placeholder.
const PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
const VISIBLE_COUNT = 6;

const items = $album[0].querySelectorAll('a.lightgallery__item');
const deferredThumbs = new Map();

items.forEach((item, index) => {
  if (index < VISIBLE_COUNT) return;
  const thumb = item.getAttribute('data-thumb');
  if (thumb) {
    deferredThumbs.set(index, thumb);
    item.setAttribute('data-thumb', PLACEHOLDER);
  }
});

            const instance = window.lightGallery($album[0], {
              ...albumSettings,
              selector: "a",
              plugins: plugins,
              subHtmlSelectorRelative: true,
              preload: 1,
            });

            // Après ouverture, restaurer les vrais data-thumb et lancer le lazy loading.
$album[0].addEventListener('lgAfterOpen', () => {
  // Restaurer les data-thumb originaux dans le HTML source.
  items.forEach((item, index) => {
    if (deferredThumbs.has(index)) {
      item.setAttribute('data-thumb', deferredThumbs.get(index));
    }
  });

  // Maintenant lancer le lazy loading sur les .lg-thumb-item générés.
  const thumbContainer = document.querySelector('.lg-outer .lg-thumb-outer');
  console.log(`[ThumbLazy] lgAfterOpen — album: ${albumId}`);
  if (thumbContainer) {
    $album.data('thumbContainer', thumbContainer);
    lazyLoadThumbnails(thumbContainer, albumId, deferredThumbs);
  }
});


$album[0].addEventListener('lgAfterClose', () => {
  // Utiliser la référence stockée plutôt que querySelector.
  const thumbContainer = $album.data('thumbContainer');
  if (thumbContainer) {
    delete thumbContainer.dataset.lazyInit;
    $album.removeData('thumbContainer');
    console.log(`[ThumbLazy] lgAfterClose — reset for album: ${albumId}`);
  }
});

            $album.data("lightGallery", instance);
          }

          $album.data("lightGallery").openGallery();
        });
      });

      console.log("✓ Album gallery initialized (Flexbox + LightGallery)");
    },
  };

  function lazyLoadThumbnails(thumbContainer, albumId, deferredThumbs) {
    if (!thumbContainer || thumbContainer.dataset.lazyInit) return;
    thumbContainer.dataset.lazyInit = 'true';

    const PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    const VISIBLE_COUNT = 6;
    const MAX_CONCURRENT = 3;

    // File locale à cette instance.
    let activeRequests = 0;
    const queue = [];
    let totalLoaded = 0;
    let totalErrors = 0;
    let totalRetries = 0;

    function logQueueState(action, src) {
      console.log(
        `[ThumbLazy:${albumId}] ${action} | active: ${activeRequests}/${MAX_CONCURRENT} | queue: ${queue.length} | loaded: ${totalLoaded} | errors: ${totalErrors} | retries: ${totalRetries}`,
        src ? `\n  → ${src.split('/').pop().split('?')[0]}` : ''
      );
    }

    function processQueue() {
      if (queue.length === 0) {
        if (activeRequests === 0) {
          logQueueState('QUEUE EMPTY - all done');
        }
        return;
      }
      while (activeRequests < MAX_CONCURRENT && queue.length > 0) {
        const img = queue.shift();
        logQueueState('DEQUEUE', img.dataset.lazySrc);
        loadImage(img);
      }
    }

    function enqueue(img) {
      if (activeRequests < MAX_CONCURRENT) {
        logQueueState('DIRECT LOAD (slot available)', img.dataset.lazySrc);
        loadImage(img);
      } else {
        logQueueState('ENQUEUED (slots full)', img.dataset.lazySrc);
        queue.push(img);
      }
    }

    function loadImage(img, retryCount = 0) {
      return new Promise((resolve) => {
        const src = img.dataset.lazySrc;
        if (!src) return resolve();

        const MAX_RETRIES = 5;
        const BASE_DELAY = 2000;
        const MAX_DELAY = 30000;

        activeRequests++;
        logQueueState(`LOAD START (retry: ${retryCount})`, src);

        const tempImg = new Image();

        tempImg.onload = () => {
          img.src = src;
          delete img.dataset.lazySrc;
          img.style.opacity = '1';
          img.closest('.lg-thumb-item')?.classList.remove('lg-thumb-loading');
          activeRequests--;
          totalLoaded++;
          logQueueState('LOAD SUCCESS', src);
          processQueue();
          resolve();
        };

        tempImg.onerror = () => {
          activeRequests--;

          if (retryCount < MAX_RETRIES) {
            totalRetries++;
            const delay = Math.min(BASE_DELAY * Math.pow(2, retryCount), MAX_DELAY);
            logQueueState(`LOAD ERROR → retry in ${delay}ms (attempt ${retryCount + 1}/${MAX_RETRIES})`, src);

            img.style.opacity = '0.3';
            img.closest('.lg-thumb-item')?.classList.add('lg-thumb-loading');

            setTimeout(() => {
              if (img.dataset.lazySrc) {
                logQueueState(`RETRY ${retryCount + 1}`, src);
                loadImage(img, retryCount + 1).then(resolve);
              } else {
                logQueueState('RETRY SKIPPED (already loaded)', src);
                resolve();
              }
            }, delay);

          } else {
            totalErrors++;
            logQueueState(`LOAD FAILED after ${MAX_RETRIES} retries`, src);

            img.style.opacity = '0.5';
            img.closest('.lg-thumb-item')?.classList.add('lg-thumb-error');

            const thumbItem = img.closest('.lg-thumb-item');
            if (thumbItem && !thumbItem.querySelector('.lg-thumb-error-icon')) {
              thumbItem.insertAdjacentHTML(
                'beforeend',
                '<span class="lg-thumb-error-icon" title="Échec du chargement">⚠</span>'
              );
            }

            processQueue();
            resolve();
          }
        };

        tempImg.src = src;
      });
    }

    // Init.
    // Les .lg-thumb-item sont générés par lightgallery dans l'ordre des slides.
  const lgItems = thumbContainer.querySelectorAll('.lg-thumb-item');
  console.log(`[ThumbLazy:${albumId}] Init — lg-thumb-items: ${lgItems.length}, deferred: ${deferredThumbs.size}, max concurrent: ${MAX_CONCURRENT}`);

  lgItems.forEach((item, index) => {
    const img = item.querySelector('img');
    if (!img) return;

    if (deferredThumbs.has(index)) {
      // Cet item avait été remplacé par un placeholder — injecter la vraie URL.
      const realSrc = deferredThumbs.get(index);
      img.dataset.lazySrc = realSrc;
      img.src = PLACEHOLDER;
      console.log(`[ThumbLazy:${albumId}] DEFERRED #${index}:`, realSrc.split('/').pop().split('?')[0]);
    }
  });

  // Charger les items visibles (non différés) + observer les différés.
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const img = entry.target.querySelector('img[data-lazy-src]');
      if (img) {
        const index = Array.from(lgItems).indexOf(entry.target);
        console.log(`[ThumbLazy:${albumId}] INTERSECTION #${index}:`, img.dataset.lazySrc.split('/').pop().split('?')[0]);
        enqueue(img);
      }
      observer.unobserve(entry.target);
    });
  }, {
    root: thumbContainer,
    rootMargin: '0px 300px',
    threshold: 0,
  });

  lgItems.forEach((item, index) => {
    if (deferredThumbs.has(index)) {
      observer.observe(item);
    }
  });
  }

})(jQuery, Drupal, drupalSettings, window.once);