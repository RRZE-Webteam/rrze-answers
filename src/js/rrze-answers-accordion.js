


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
    $('.rrze-answers[data-accordion="single"]').each(function () {
      var $group = $(this);
      var $items = $group.find('details.rrze-answers-item');

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

      function getContent($details) {
        return $details.children().not('summary');
      }

      function setOpen($details, shouldOpen, animate) {
        var $content = getContent($details);

        if (shouldOpen) {
          $details.attr('open', 'open');
          if (animate) {
            $content.stop(true, true).slideDown(400);
          } else {
            $content.stop(true, true).show();
          }
          return;
        }

        if (animate) {
          $content.stop(true, true).slideUp(400, function () {
            $details.removeAttr('open');
          });
        } else {
          $content.stop(true, true).hide();
          $details.removeAttr('open');
        }
      }

      // Close all siblings except the provided one
      function closeSiblings($except, animate) {
        $items.not($except).each(function () {
          var $d = $(this);
          if ($d.prop('open')) {
            setOpen($d, false, animate);
          }
        });
      }

      function openItem($target, animate) {
        setOpen($target, true, animate);
        closeSiblings($target, animate);

        if ($target.attr('id')) {
          history.replaceState(null, null, '#' + $target.attr('id'));
        }
      }

      // Open target by location hash; returns true if handled
      function openByHash(doScroll) {
        var raw = window.location.hash || '';
        if (!raw) return false;

        var id = decodeURIComponent(raw.replace(/^#/, ''));
        if (!id) return false;

        var $el = byId(id);
        if (!$el.length) return false;

        var $target = $el.closest('details.rrze-answers-item');
        if (!$target.length && $el.is('details.rrze-answers-item')) $target = $el;
        if (!$target.length || !$group.has($target).length) return false;

        openItem($target, true);

        var $sum = $target.children('summary').first();
        if ($sum.length) { try { $sum.trigger('focus'); } catch (e) { } }

        if (doScroll) {
          var top = $target.offset().top - scrollOffset;
          $('html, body').stop(true).animate({ scrollTop: Math.max(0, top) }, 300);
        }
        return true;
      }

      // Initial: honor hash; otherwise keep only the first pre-open item
      $items.each(function () {
        var $d = $(this);
        var $content = getContent($d);
        if ($d.prop('open')) {
          $content.show();
        } else {
          $content.hide();
        }
      });

      if (!openByHash(false)) {
        var $firstOpen = $items.filter('[open]').first();
        if ($firstOpen.length) {
          closeSiblings($firstOpen, false);
          setOpen($firstOpen, true, false);
        }
      }

      // Keep only one open — custom toggle with slide animation
      $items.each(function () {
        var $d = $(this);
        var $summary = $d.children('summary');

        function toggleItem() {
          if ($d.prop('open')) {
            setOpen($d, false, true);
          } else {
            openItem($d, true);
          }
        }

        $summary.on('click', function (e) {
          e.preventDefault();
          toggleItem();
        });

        $summary.on('keydown', function (e) {
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            toggleItem();
          }
        });
      });

      // React to hash changes
      $(window).on('hashchange', function () { openByHash(true); });
    });
  });

})(jQuery);
