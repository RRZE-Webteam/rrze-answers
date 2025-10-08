/******/ (() => { // webpackBootstrap
/*!**************************************!*\
  !*** ./src/js/rrze-faq-accordion.js ***!
  \**************************************/
// fix for theme RRZE-2019 which has an overlay with the menue
function setHeaderVar() {
  const h = document.getElementById('site-navigation')?.getBoundingClientRect().height || 0;
  document.documentElement.style.setProperty('--header-height', `${Math.ceil(h)}px`);
}
setHeaderVar();
window.addEventListener('resize', setHeaderVar);
// falls der Header-Inhalt dynamisch ist (z.B. Fonts nachladen)
window.addEventListener('load', setHeaderVar);

/* RRZE FAQ accordion: single-open + open-by-hash */
(function ($) {
  'use strict';

  $(function () {
    $('.rrze-faq[data-accordion="single"]').each(function () {
      var $group = $(this);
      var $items = $group.find('details.faq-item');

      // Optional header offset for smooth scroll (set data-scroll-offset="96" on wrapper)
      var scrollOffset = parseInt($group.attr('data-scroll-offset') || '0', 10);

      // Utility: robust ID selector
      function byId(id) {
        try {
          return $('#' + CSS.escape(id));
        } catch (e) {
          return $('#' + id.replace(/([ !"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~])/g, '\\$1'));
        }
      }

      // Close all siblings except the provided one
      function closeSiblings($except) {
        $items.not($except).removeAttr('open');
      }

      // Open target by location hash; returns true if handled
      function openByHash(doScroll) {
        var raw = window.location.hash || '';
        if (!raw) return false;
        var id = decodeURIComponent(raw.replace(/^#/, ''));
        if (!id) return false;
        var $el = byId(id);
        if (!$el.length) return false;
        var $target = $el.closest('details.faq-item');
        if (!$target.length && $el.is('details.faq-item')) $target = $el;
        if (!$target.length || !$group.has($target).length) return false;
        $target.attr('open', 'open');
        closeSiblings($target);
        var $sum = $target.children('summary').first();
        if ($sum.length) {
          try {
            $sum.trigger('focus');
          } catch (e) {}
        }
        if (doScroll) {
          var top = $target.offset().top - scrollOffset;
          $('html, body').stop(true).animate({
            scrollTop: Math.max(0, top)
          }, 300);
        }
        return true;
      }

      // Initial: honor hash; otherwise keep only the first pre-open item
      if (!openByHash(false)) {
        var $firstOpen = $items.filter('[open]').first();
        if ($firstOpen.length) closeSiblings($firstOpen);
      }

      // Keep only one open — prefer native 'toggle', fall back to summary click
      $items.each(function () {
        var $d = $(this);

        // Native 'toggle' event (supported in modern browsers)
        $d.on('toggle', function () {
          if (this.open) {
            closeSiblings($d);

            // refresh Hash in URL
            if ($d.attr('id')) {
              history.replaceState(null, null, '#' + $d.attr('id'));
            }
          }
        });

        // Fallback: after summary click, check open state
        $d.children('summary').on('click', function () {
          setTimeout(function () {
            if ($d.prop('open')) closeSiblings($d);

            // refresh Hash in URL
            if ($d.attr('id')) {
              history.replaceState(null, null, '#' + $d.attr('id'));
            }
          }, 0);
        });
      });

      // React to hash changes
      $(window).on('hashchange', function () {
        openByHash(true);
      });
    });
  });
})(jQuery);
/******/ })()
;
//# sourceMappingURL=rrze-faq-accordion.js.map