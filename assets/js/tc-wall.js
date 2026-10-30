/* Testimonial Collector — paginated wall */
(function () {
	'use strict';

	var cfg = window.tcWall || {};
	var READ_MORE = cfg.readMore || 'Read more';
	var READ_LESS = cfg.readLess || 'Show less';
	var CLAMP_PX = 160; // must match .tc-text.tc-clampable max-height in CSS

	// Collapse an overflowing text testimonial with a fade + read-more toggle.
	// Measured only while the card is visible (hidden cards report 0 height).
	function setupClamp(card) {
		if (card.dataset.tcClampDone) {
			return;
		}
		var textEl = card.querySelector('.tc-text');
		if (!textEl || card.offsetParent === null) {
			return; // not a text card, or not visible yet — try again when shown
		}
		card.dataset.tcClampDone = '1';
		if (textEl.scrollHeight <= CLAMP_PX + 8) {
			return; // short enough, leave as is
		}
		textEl.classList.add('tc-clampable');
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'tc-readmore';
		btn.textContent = READ_MORE;
		btn.addEventListener('click', function () {
			var expanded = textEl.classList.toggle('tc-expanded');
			btn.textContent = expanded ? READ_LESS : READ_MORE;
		});
		textEl.insertAdjacentElement('afterend', btn);
	}

	document.querySelectorAll('.tc-carousel').forEach(function (carousel) {
		var cards = Array.prototype.slice.call(carousel.querySelectorAll('.tc-card'));
		var prevBtn = carousel.querySelector('.tc-arrow-prev');
		var nextBtn = carousel.querySelector('.tc-arrow-next');
		var dotsWrap = carousel.querySelector('.tc-dots');
		var current = 0;
		// Page size comes from the per_page setting / shortcode attribute
		// (data-per-page); the grid wraps it into as many rows as needed.
		var perPage = Math.max(1, parseInt(carousel.getAttribute('data-per-page'), 10) || 6);
		var pageCount = 1;
		var dots = [];

		if (!cards.length) {
			return;
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
				if (visible) {
					// Cards start hidden, so clamp is measured the first time a
					// card is actually shown on its page.
					setupClamp(card);
				}
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

		pageCount = Math.max(1, Math.ceil(cards.length / perPage));

		var hideNav = pageCount <= 1;
		if (prevBtn) { prevBtn.hidden = hideNav; }
		if (nextBtn) { nextBtn.hidden = hideNav; }
		if (dotsWrap) { dotsWrap.hidden = hideNav; }

		if (prevBtn) { prevBtn.addEventListener('click', function () { show(current - 1); }); }
		if (nextBtn) { nextBtn.addEventListener('click', function () { show(current + 1); }); }

		buildDots();
		show(0);
	});
})();
