/*
 * Testimonial Collector — embed loader for other websites (WordPress or not).
 *
 *   <div data-tc-embed="form"></div>
 *   <script src="https://example.com/wp-content/plugins/testimonial-collector/assets/js/tc-embed.js"
 *           data-tc-site="https://example.com/" async></script>
 *
 * Placeholder attributes:
 *   data-tc-embed="form" | "wall"   what to show (required)
 *   data-tc-event="..."             pin to one event / program (must be in the Events list)
 *   data-tc-per-page="4"            wall page size
 *   data-tc-height="700"            initial height in px until the real one arrives
 */
(function () {
	'use strict';

	var script = document.currentScript;
	var site = script && script.getAttribute('data-tc-site');
	if (!site && script && script.src) {
		// Fallback: the plugin lives under <site>/wp-content/plugins/...
		var cut = script.src.indexOf('/wp-content/');
		site = cut !== -1 ? script.src.slice(0, cut + 1) : '';
	}
	if (!site) {
		return;
	}

	try {
		new URL(site, window.location.href); // eslint-disable-line no-new
	} catch (e) {
		return;
	}

	function mount(el) {
		if (el.getAttribute('data-tc-mounted')) {
			return;
		}
		var view = el.getAttribute('data-tc-embed') === 'wall' ? 'wall' : 'form';
		var url = new URL(site, window.location.href);
		url.searchParams.set('tc_embed', view);
		var event = el.getAttribute('data-tc-event');
		if (event) {
			url.searchParams.set('event', event);
		}
		var perPage = el.getAttribute('data-tc-per-page');
		if (perPage) {
			url.searchParams.set('per_page', perPage);
		}

		var iframe = document.createElement('iframe');
		iframe.src = url.toString();
		iframe.title = el.getAttribute('data-tc-title') || (view === 'wall' ? 'Testimonials' : 'Testimonial form');
		iframe.setAttribute('allow', 'camera; microphone; fullscreen');
		iframe.setAttribute('allowfullscreen', '');
		iframe.setAttribute('scrolling', 'no');
		iframe.style.cssText = 'display:block;width:100%;border:0;overflow:hidden;background:transparent;';
		iframe.style.height = (parseInt(el.getAttribute('data-tc-height'), 10) || 700) + 'px';

		el.setAttribute('data-tc-mounted', '1');
		el.textContent = '';
		el.appendChild(iframe);
	}

	function mountAll() {
		var nodes = document.querySelectorAll('[data-tc-embed]');
		for (var i = 0; i < nodes.length; i++) {
			mount(nodes[i]);
		}
	}

	function frameFor(source) {
		var frames = document.querySelectorAll('[data-tc-embed] iframe');
		for (var i = 0; i < frames.length; i++) {
			if (frames[i].contentWindow === source) {
				return frames[i];
			}
		}
		return null;
	}

	// Several loader tags on one page share a single message listener.
	if (!window.__tcEmbedListener) {
		window.__tcEmbedListener = true;
		window.addEventListener('message', function (e) {
			var data = e.data;
			if (!data || data.tc !== 'testimonial-collector') {
				return;
			}
			var iframe = frameFor(e.source);
			if (!iframe || new URL(iframe.src).origin !== e.origin) {
				return;
			}
			if (data.type === 'height' && data.height > 0) {
				iframe.style.height = Math.ceil(data.height) + 'px';
			} else if (data.type === 'submitted') {
				var top = iframe.getBoundingClientRect().top;
				if (top < 0 || top > window.innerHeight) {
					iframe.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', mountAll);
	} else {
		mountAll();
	}
})();
