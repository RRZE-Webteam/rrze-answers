/* global inlineEditPost */
(function ($) {
	'use strict';

	const fieldName = 'rrze_answers_lang';

	function populateQuickEdit(postId) {
		const $row = $('#edit-' + postId);
		const lang = $('#rrze_answers_inline_lang_' + postId).text();
		const $select = $row.find('select[name="' + fieldName + '"]');

		if ($select.length) {
			$select.val(lang);
		}
	}

	const $inlineEdit = inlineEditPost.edit;
	inlineEditPost.edit = function (id) {
		$inlineEdit.apply(this, arguments);

		let postId = 0;
		if (typeof id === 'object') {
			postId = parseInt(this.getId(id), 10);
		}

		if (postId > 0) {
			populateQuickEdit(postId);
		}
	};
})(jQuery);
