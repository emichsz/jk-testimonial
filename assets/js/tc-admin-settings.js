/* Testimonial Collector — settings page: tabs, media pickers, colors, live preview */
(function ($) {
	'use strict';

	$(function () {
		var cfg = window.tcSettingsPreview || { strings: { hu: {}, en: {} }, autoLang: 'en' };

		/* ---------- Admin tabs ---------- */
		var $tabs = $('.tc-nav-tabs .nav-tab');
		$tabs.on('click', function (e) {
			e.preventDefault();
			$tabs.removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');
			$('.tc-tab-panel').prop('hidden', true);
			$($(this).attr('href')).prop('hidden', false);
		});

		/* ---------- Media pickers ---------- */
		function bindMedia(key) {
			var frame = null;
			$('[data-tc-media-select="' + key + '"]').on('click', function (e) {
				e.preventDefault();
				if (!frame) {
					frame = wp.media({ title: '', multiple: false, library: { type: 'image' } });
					frame.on('select', function () {
						var att = frame.state().get('selection').first().toJSON();
						var url = (att.sizes && (att.sizes.medium || att.sizes.full)) ? (att.sizes.medium || att.sizes.full).url : att.url;
						$('[data-tc-media="' + key + '"]').val(att.id);
						$('[data-tc-media-preview="' + key + '"]').prop('hidden', false).find('img').attr('src', url);
						$('[data-tc-media-remove="' + key + '"]').prop('hidden', false);
						updatePreview();
					});
				}
				frame.open();
			});
			$('[data-tc-media-remove="' + key + '"]').on('click', function (e) {
				e.preventDefault();
				$('[data-tc-media="' + key + '"]').val('0');
				$('[data-tc-media-preview="' + key + '"]').prop('hidden', true).find('img').attr('src', '');
				$(this).prop('hidden', true);
				updatePreview();
			});
		}
		bindMedia('logo');
		bindMedia('thankyou');

		/* ---------- Color pickers ---------- */
		$('.tc-color').wpColorPicker({
			change: function () { setTimeout(updatePreview, 10); },
			clear: function () { setTimeout(updatePreview, 10); }
		});

		/* ---------- Preview mode switch ---------- */
		$('.tc-preview-mode-btn').on('click', function () {
			$('.tc-preview-mode-btn').removeClass('active');
			$(this).addClass('active');
			var mode = $(this).data('mode');
			$('#tc-preview-form').prop('hidden', mode !== 'form');
			$('#tc-preview-thanks').prop('hidden', mode !== 'thanks');
		});

		/* ---------- Live preview ---------- */
		function currentLang() {
			var lang = $('[data-tc-field="language"]').val() || 'auto';
			return lang === 'auto' ? cfg.autoLang : lang;
		}

		function textValue(key) {
			var lang = currentLang();
			var override = $.trim($('[data-tc-text="' + key + '"][data-tc-lang="' + lang + '"]').val() || '');
			return override !== '' ? override : (cfg.strings[lang] && cfg.strings[lang][key]) || '';
		}

		function updatePreview() {
			var lang = currentLang();

			// Texts
			['form_title', 'form_intro', 'questions_title', 'label_consent', 'thankyou_title', 'msg_thanks'].forEach(function (key) {
				$('[data-pv="' + key + '"]').text(textValue(key));
			});
			$('[data-pv="tab_video"]').text('🎥 ' + textValue('tab_video'));
			$('[data-pv="tab_text"]').text('✏️ ' + textValue('tab_text'));

			// Questions list
			var $ul = $('[data-pv="questions"]').empty();
			textValue('questions').split('\n').forEach(function (line) {
				line = $.trim(line);
				if (line) {
					$('<li>').text(line).appendTo($ul);
				}
			});

			// Collection type: hide buttons accordingly
			var collection = $('[data-tc-field="collection_type"]').val();
			$('[data-pv="tab_video"]').toggle(collection !== 'text');
			$('[data-pv="tab_text"]').toggle(collection !== 'video');

			// Rating stars visibility
			$('[data-pv="stars"]').toggle($('[data-tc-flag="collect_rating"]').is(':checked'));

			// Consent visibility
			var consentMode = $('[data-tc-field="consent_mode"]').val();
			$('[data-pv="label_consent"]').toggle(consentMode !== 'hidden');

			// Colors
			var colors = {
				'--tc-primary': $('[data-tc-color="primary"]').val(),
				'--tc-accent': $('[data-tc-color="accent"]').val(),
				'--tc-bg': $('[data-tc-color="bg"]').val(),
				'--tc-on-primary': $('[data-tc-color="on-primary"]').val()
			};
			$('#tc-preview-form, #tc-preview-thanks').each(function () {
				for (var prop in colors) {
					if (colors[prop]) {
						this.style.setProperty(prop, colors[prop]);
					}
				}
				$(this).toggleClass('tc-theme-dark', $('[data-tc-field="theme"]').val() === 'dark');
			});

			// Logo
			var logoSrc = $('[data-tc-media-preview="logo"] img').attr('src') || '';
			$('[data-pv="logo-wrap"]').prop('hidden', !logoSrc);
			$('[data-pv="logo"]').attr('src', logoSrc);

			// Thank you image
			var tySrc = $('[data-tc-media-preview="thankyou"] img').attr('src') || '';
			var showTyImg = $('[data-tc-flag="thankyou_show_image"]').is(':checked') && tySrc;
			$('[data-pv="thankyou-img"]').prop('hidden', !showTyImg).attr('src', tySrc);
		}

		// Bind all relevant inputs.
		$(document).on('input change', '[data-tc-text], [data-tc-field], [data-tc-flag]', updatePreview);

		updatePreview();
	});
})(jQuery);
