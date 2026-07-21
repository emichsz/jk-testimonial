/* Testimonial Collector — paginated wall */
(function () {
	'use strict';

	document.querySelectorAll('.tc-carousel').forEach(function (carousel) {
		var cards = Array.prototype.slice.call(carousel.querySelectorAll('.tc-card'));
		var grid = carousel.querySelector('.tc-grid');
		var prevBtn = carousel.querySelector('.tc-arrow-prev');
		var nextBtn = carousel.querySelector('.tc-arrow-next');
		var dotsWrap = carousel.querySelector('.tc-dots');
		var current = 0;
		var perPage = 2;
		var pageCount = 1;
		var dots = [];

		if (!cards.length) {
			return;
		}

		// How many cards actually fit side by side right now (2 on desktop,
		// 1 on narrow screens per the .tc-grid media query). Reading the
		// live grid keeps the carousel in sync with the CSS instead of a
		// fixed "items per page" number, so exactly one row is ever visible.
		function getColumns() {
			if (!grid || !window.getComputedStyle) {
				return 2;
			}
			var cols = getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length;
			return Math.max(1, cols);
		}

		function buildDots() {
			if (!dotsWrap) {
				return;
			}
			dotsWrap.innerHTML = '';
			dots = [];
			for (var i = 0; i < pageCount; i++) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'tc-dot';
				dot.setAttribute('aria-label', String(i + 1));
				(function (idx) {
					dot.addEventListener('click', function () { show(idx); });
				})(i);
				dotsWrap.appendChild(dot);
				dots.push(dot);
			}
		}

		function show(page) {
			current = Math.max(0, Math.min(pageCount - 1, page));
			cards.forEach(function (card, idx) {
				var visible = Math.floor(idx / perPage) === current;
				card.hidden = !visible;
				if (!visible) {
					// Pause any playing video on hidden pages.
					var video = card.querySelector('video');
					if (video && !video.paused) {
						video.pause();
					}
				}
			});
			dots.forEach(function (d, idx) {
				d.classList.toggle('tc-dot-active', idx === current);
			});
			if (prevBtn) { prevBtn.disabled = current === 0; }
			if (nextBtn) { nextBtn.disabled = current === pageCount - 1; }
		}

		function rebuild() {
			perPage = getColumns();
			pageCount = Math.max(1, Math.ceil(cards.length / perPage));
			current = Math.min(current, pageCount - 1);

			var hideNav = pageCount <= 1;
			if (prevBtn) { prevBtn.hidden = hideNav; }
			if (nextBtn) { nextBtn.hidden = hideNav; }
			if (dotsWrap) { dotsWrap.hidden = hideNav; }

			buildDots();
			show(current);
		}

		if (prevBtn) { prevBtn.addEventListener('click', function () { show(current - 1); }); }
		if (nextBtn) { nextBtn.addEventListener('click', function () { show(current + 1); }); }

		rebuild();

		// Re-check on resize (e.g. rotating a tablet, or the desktop/mobile
		// breakpoint) so the carousel keeps showing exactly one row.
		var resizeTimer;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () {
				if (getColumns() !== perPage) {
					rebuild();
				}
			}, 150);
		});
	});
})();
