/******/ (() => { // webpackBootstrap
/*!**********************************!*\
  !*** ./src/js/rrze-import-ui.js ***!
  \**********************************/
/* global jQuery, RRZEAnswersSync */
(function ($) {
  'use strict';

  // Helper: set the correct "name" attribute on the multiselect based on current site
  function setSelectName($select, shortname) {
    var optionName = RRZEAnswersSync.optionName || 'rrze-answers';
    var key = 'import_faq_categories' + (shortname ? '_' + shortname : '');
    $select.attr('name', optionName + '[' + key + '][]');
  }
  function setStatus(msg, isError) {
    $('#rrze-answers-cats-status').text(msg || '').css({
      color: isError ? '#b32d2e' : '#1d2327'
    });
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
      var opt = $('<option/>', {
        value: slug,
        text: map[slug] || slug
      });
      if (Array.isArray(selected) && selected.indexOf(slug) !== -1) {
        opt.prop('selected', true);
      }
      $select.append(opt);
    });
  }
  function loadCategories(shortname) {
    console.log('loadCategories');
    var $select = $('#rrze-answers_remote_categories_faq_');
    setSelectName($select, shortname);
    console.log('loadCategories 2');
    if (!shortname) {
      $select.empty();
      setStatus('', false);
      setHelp('');
      return;
    }
    setStatus(RRZEAnswersSync.i18n.loading, false);
    setHelp('');
    var url = RRZEAnswersSync.ajaxUrl;
    var payload = {
      action: 'rrze_answers_get_categories',
      _ajax_nonce: RRZEAnswersSync.nonce,
      shortname: shortname
    };
    console.group('loadCategories debug');
    console.log('AJAX URL:', url);
    console.log('Payload:', payload);
    console.log('Full URL (GET-style):', url + '?' + jQuery.param(payload));
    $.ajax({
      url: RRZEAnswersSync.ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'rrze_answers_get_categories',
        _ajax_nonce: RRZEAnswersSync.nonce,
        shortname: shortname
      }
    }).done(function (resp) {
      console.log('resp', resp);
      if (!resp || !resp.success) {
        setStatus(resp && resp.data && resp.data.message || RRZEAnswersSync.i18n.error, true);
        return;
      }
      var cats = resp.data.categories || {};
      var selected = resp.data.selected || [];
      if (Object.keys(cats).length === 0) {
        populateCategories($select, {}, []);
        setStatus(RRZEAnswersSync.i18n.none, false);
        setHelp('');
        return;
      }
      populateCategories($select, cats, selected);
      setStatus('', false);
      setHelp(RRZEAnswersSync.i18n.selectCategories);
    }).fail(function () {
      setStatus(RRZEAnswersSync.i18n.error, true);
    });
  }
  $(function () {
    var $site = $('rrze-answers_remote_url_faq');
    var initial = $site.val() || '';
    console.log('$site', $site);

    // Initial load (use currently selected site if present)
    // loadCategories(initial);

    // On change: fetch & populate
    $site.on('change', function () {
      var shortname = $(this).val() || '';
      console.log('shortname', shortname);
      loadCategories(shortname);
    });
  });
})(jQuery);
/******/ })()
;
//# sourceMappingURL=rrze-import-ui.js.map