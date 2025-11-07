/* global jQuery, RRZEAnswersSync */
(function ($) {
  'use strict';

  // Helper: set the correct "name" attribute on the multiselect based on current site
  function setSelectNameForSite($select, site_url) {
    // Remove name if no site is selected
    if (!site_url) {
      $select.removeAttr('name');
      return;
    }
    // Important: keep raw site_url as array key (WordPress/PHP can handle this)
    var fieldName = 'rrze-answers[remote_categories_faq][' + site_url + ']';
    $select.attr('name', fieldName);
  }

  function setStatus(msg, isError) {
    $('#rrze-answers-cats-status')
      .text(msg || '')
      .css({ color: isError ? '#b32d2e' : '#1d2327' });
  }

  function setHelp(msg) {
    $('#rrze-answers-cats-help').text(msg || '');
  }

  function populateCategories($select, map, selected) {
    $select.empty();

    // map: {slug: "Name", ...}
    var keys = Object.keys(map || {});
    keys.sort(function (a, b) {
      return (map[a] || '').toLowerCase().localeCompare((map[b] || '').toLowerCase());
    });

    keys.forEach(function (slug) {
      var opt = $('<option/>', { value: slug, text: map[slug] || slug });
      if (Array.isArray(selected) && selected.indexOf(slug) !== -1) {
        opt.prop('selected', true);
      }
      $select.append(opt);
    });
  }

  // Populate the "remaining sites" dropdown below categories
  function populateRemainingSites($select, urls, current) {
    // Keep the field name constant as requested
    $select.attr('name', 'rrze-answers[remote_url_faq]');
    $select.empty();

    // Add a placeholder if there are items, otherwise clear completely
    if (Array.isArray(urls) && urls.length) {
      $select.append($('<option/>', { value: '', text: '— Auswahl —' }));
      urls.forEach(function (u) {
        if (u && u !== current) {
          $select.append($('<option/>', { value: u, text: u }));
        }
      });
      $select.prop('disabled', false).show();
    } else {
      // No remaining URLs -> hide/disable
      $select.prop('disabled', true).hide();
    }
  }

  function loadCategories(site_url) {
    var $catsSelect = $('#rrze-answers_remote_categories_faq_');
    var $nextSiteSelect = $('#rrze-answers_remote_url_faq'); // below the categories

    if (!site_url) {
      $catsSelect.empty();
      setSelectNameForSite($catsSelect, ''); // remove name if nothing selected
      setStatus('', false);
      setHelp('');
      // If we have a prefilled list from server-side, leave it as-is
      return;
    }

    setStatus(RRZEAnswersSync.i18n.loading, false);
    setHelp('');

    $.ajax({
      url: RRZEAnswersSync.ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'rrze_answers_get_categories',
        _ajax_nonce: RRZEAnswersSync.nonce,
        site_url: site_url
      }
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          setStatus((resp && resp.data && resp.data.message) || RRZEAnswersSync.i18n.error, true);
          return;
        }

        // Always set the select name to include the current site_url
        setSelectNameForSite($catsSelect, site_url);

        var cats = resp.data.categories || {};
        var selected = resp.data.selected || [];

        if (Object.keys(cats).length === 0) {
          populateCategories($catsSelect, {}, []);
          setStatus(RRZEAnswersSync.i18n.none, false);
          setHelp('');
        } else {
          populateCategories($catsSelect, cats, selected);
          setStatus('', false);
          setHelp(RRZEAnswersSync.i18n.selectCategories);
        }

        // Fill the "remaining sites" dropdown under the categories
        var remaining = resp.data.remaining_urls || [];
        populateRemainingSites($nextSiteSelect, remaining, site_url);
      })
      .fail(function () {
        setStatus(RRZEAnswersSync.i18n.error, true);
      });
  }

  $(function () {
    var $site = $('#rrze-answers_remote_url_faq'); // top site selector
    var $catsSelect = $('#rrze-answers_remote_categories_faq_');

    // Make sure category select is multi-select in UI (if not already)
    $catsSelect.attr('multiple', 'multiple');

    var initial = $site.val() || '';
    loadCategories(initial);

    // When the top site changes
    $site.on('change', function () {
      var site_url = $(this).val() || '';
      loadCategories(site_url);
    });

    // When the "remaining sites" dropdown (below) changes, reuse same loader
    $('#rrze-answers_remote_url_faq').on('change', function () {
      var site_url = $(this).val() || '';
      if (site_url) {
        // Move selection to the top selector (optional UX)
        $site.val(site_url);
        loadCategories(site_url);
      }
    });
  });
})(jQuery);
