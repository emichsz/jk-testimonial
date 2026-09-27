/* Testimonial Collector — runs inside the embed iframe, reports height to the host page */
(function () {
	'use strict';

	if (window.parent === window) {
		return; // opened directly, not framed
	}

	var root = document.querySelector('.tc-embed-root') || document.body;
	var lastHeight = 0;

	function post(msg) {
		msg.tc = 'testimonial-collector';
		// Height and scroll hints are not sensitive, so any parent may read them.
		window.parent.postMessage(msg, '*');
	}

	function reportHeight() {
		var height = Math.ceil(root.getBoundingClientRect().height);
		if (height && height !== lastHeight) {
			lastHeight = height;
			post({ type: 'height', height: height });
		}
	}

	if (window.ResizeObserver) {
		new ResizeObserver(reportHeight).observe(root);
	} else {
		setInterval(reportHeight, 500);
	}
	window.addEventListener('load', reportHeight);
	reportHeight();

	// After a successful submission the thank-you block replaces the form, so
	// ask the host page to bring the top of the iframe back into view.
	document.addEventListener('tc:submitted', function () {
		reportHeight();
		post({ type: 'submitted' });
	});
})();
