/* Testimonial Collector — paginated wall */
(function () {
	'use strict';

	document.querySelectorAll('.tc-carousel').forEach(function (carousel) {
		var perPage = parseInt(carousel.getAttribute('data-per-page'), 10) || 6;
		var cards = Array.prototype.slice.call(carousel.querySelectorAll('.tc-card'));
		var prevBtn = carousel.querySelector('.tc-arrow-prev');
		var nextBtn = carousel.querySelector('.tc-arrow-next');
		var dotsWrap = carousel.querySelector('.tc-dots');
		var pageCount = Math.max(1, Math.ceil(cards.length / perPage));
		var current = 0;

		if (pageCount <= 1) {
			if (prevBtn) { prevBtn.hidden = true; }
			if (nextBtn) { nextBtn.hidden = true; }
			return;
		}

		var dots = [];
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
			prevBtn.disabled = current === 0;
			nextBtn.disabled = current === pageCount - 1;
		}

		prevBtn.addEventListener('click', function () { show(current - 1); });
		nextBtn.addEventListener('click', function () { show(current + 1); });

		show(0);
	});
})();
