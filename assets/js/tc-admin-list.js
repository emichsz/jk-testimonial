/* Testimonial Collector — list screen: prefill the event field in Quick Edit */
(function ($) {
	'use strict';

	if (typeof inlineEditPost === 'undefined') {
		return;
	}

	var origEdit = inlineEditPost.edit;
	inlineEditPost.edit = function (id) {
		origEdit.apply(this, arguments);

		var postId = typeof id === 'object' ? parseInt(this.getId(id), 10) : parseInt(id, 10);
		if (!postId) {
			return;
		}
		var value = $('#post-' + postId).find('.tc-event-value').text();
		var $field = $('#edit-' + postId).find('[name="tc_event"]');

		// A value no longer in the configured list still needs an option.
		if ($field.is('select') && value && !$field.find('option').filter(function () { return this.value === value; }).length) {
			$field.append($('<option>').val(value).text(value));
		}
		$field.val(value);
	};
})(jQuery);
