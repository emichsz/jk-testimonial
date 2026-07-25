/* Testimonial Collector — edit screen: media pickers (avatar, video) + type toggle */
(function ($) {
	'use strict';

	$(function () {
		function bindMedia(key, libType) {
			var frame = null;
			$('[data-tc-media-select="' + key + '"]').on('click', function (e) {
				e.preventDefault();
				if (!frame) {
					frame = wp.media({ title: '', multiple: false, library: { type: libType } });
					frame.on('select', function () {
						var att = frame.state().get('selection').first().toJSON();
						var thumb = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
						$('[data-tc-media="' + key + '"]').val(att.id);
						var $prev = $('[data-tc-media-preview="' + key + '"]').prop('hidden', false);
						$prev.find('img').attr('src', thumb);
						$prev.find('video').attr('src', att.url);
						$('[data-tc-media-remove="' + key + '"]').prop('hidden', false);
					});
				}
				frame.open();
			});
			$('[data-tc-media-remove="' + key + '"]').on('click', function (e) {
				e.preventDefault();
				$('[data-tc-media="' + key + '"]').val('0');
				var $prev = $('[data-tc-media-preview="' + key + '"]').prop('hidden', true);
				$prev.find('img').attr('src', '');
				$prev.find('video').attr('src', '');
				$(this).prop('hidden', true);
			});
		}
		bindMedia('avatar', 'image');
		bindMedia('tcvideo', 'video');

		// Show the video picker only when type = video.
		function toggleVideo() {
			$('.tc-mb-video').toggle($('#tc_mb_type').val() === 'video');
		}
		$('#tc_mb_type').on('change', toggleVideo);
		toggleVideo();
	});
})(jQuery);
