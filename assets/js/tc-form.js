/* Testimonial Collector — submission form + in-browser video recording */
(function () {
	'use strict';

	if (typeof tcForm === 'undefined') {
		return;
	}

	var i18n = tcForm.i18n || {};

	function isIOS() {
		return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
			(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	}

	document.querySelectorAll('.tc-form-container').forEach(function (container) {
		var form = container.querySelector('.tc-form');
		if (!form) {
			return;
		}

		var typeInput = form.querySelector('input[name="type"]');
		var tabs = form.querySelectorAll('.tc-tab');
		var panelText = form.querySelector('.tc-panel-text');
		var panelVideo = form.querySelector('.tc-panel-video');
		var message = form.querySelector('.tc-message');
		var submitBtn = form.querySelector('.tc-btn-submit');
		var thanks = container.querySelector('.tc-thanks');
		var questions = container.querySelector('.tc-questions');

		/* ---------- Tabs ---------- */
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				tabs.forEach(function (t) {
					t.classList.remove('tc-tab-active');
					t.setAttribute('aria-selected', 'false');
				});
				tab.classList.add('tc-tab-active');
				tab.setAttribute('aria-selected', 'true');
				var type = tab.getAttribute('data-type');
				typeInput.value = type;
				if (panelText) { panelText.hidden = type !== 'text'; }
				if (panelVideo) { panelVideo.hidden = type !== 'video'; }
			});
		});

		/* ---------- Char counter ---------- */
		var textarea = form.querySelector('textarea[name="content"]');
		var counter = form.querySelector('.tc-chars-used');
		if (textarea && counter) {
			textarea.addEventListener('input', function () {
				counter.textContent = String(textarea.value.length);
			});
		}

		/* ---------- Star rating ---------- */
		var ratingWrap = form.querySelector('.tc-rating-input');
		var ratingInput = form.querySelector('input[name="rating"]');
		if (ratingWrap && ratingInput) {
			ratingWrap.querySelectorAll('.tc-rating-star').forEach(function (star) {
				star.addEventListener('click', function () {
					var value = parseInt(star.getAttribute('data-value'), 10);
					ratingInput.value = String(value);
					ratingWrap.querySelectorAll('.tc-rating-star').forEach(function (s) {
						s.classList.toggle('tc-star-on', parseInt(s.getAttribute('data-value'), 10) <= value);
					});
				});
			});
		}

		/* ---------- Video recorder ---------- */
		var recorderWrap = form.querySelector('.tc-recorder');
		var recordedBlob = null;
		var recordedMime = '';

		if (recorderWrap) {
			var preview = recorderWrap.querySelector('.tc-preview');
			var playback = recorderWrap.querySelector('.tc-playback');
			var status = recorderWrap.querySelector('.tc-rec-status');
			var camBtn = recorderWrap.querySelector('.tc-btn-cam');
			var recBtn = recorderWrap.querySelector('.tc-btn-record');
			var retakeBtn = recorderWrap.querySelector('.tc-btn-retake');
			var uploadFallback = recorderWrap.querySelector('.tc-video-upload');

			var stream = null;
			var mediaRecorder = null;
			var chunks = [];
			var timer = null;
			var secondsLeft = 0;

			var recordingSupported = !!(navigator.mediaDevices &&
				navigator.mediaDevices.getUserMedia &&
				window.MediaRecorder);

			if (!recordingSupported || (tcForm.iosNoRecord && isIOS())) {
				camBtn.hidden = true;
				if (uploadFallback) {
					uploadFallback.hidden = false;
				}
			}

			function pickMime() {
				var candidates = [
					'video/webm;codecs=vp9,opus',
					'video/webm;codecs=vp8,opus',
					'video/webm',
					'video/mp4'
				];
				for (var i = 0; i < candidates.length; i++) {
					if (MediaRecorder.isTypeSupported(candidates[i])) {
						return candidates[i];
					}
				}
				return '';
			}

			function stopStream() {
				if (stream) {
					stream.getTracks().forEach(function (t) { t.stop(); });
					stream = null;
				}
			}

			function stopTimer() {
				if (timer) {
					clearInterval(timer);
					timer = null;
				}
				status.textContent = '';
			}

			camBtn.addEventListener('click', function () {
				navigator.mediaDevices.getUserMedia({ video: true, audio: true })
					.then(function (s) {
						stream = s;
						preview.srcObject = s;
						preview.classList.add('tc-active');
						playback.classList.remove('tc-active');
						playback.hidden = true;
						preview.play();
						camBtn.hidden = true;
						recBtn.hidden = false;
						retakeBtn.hidden = true;
					})
					.catch(function () {
						status.textContent = i18n.camError || 'Camera error';
						if (uploadFallback) {
							uploadFallback.hidden = false;
						}
					});
			});

			recBtn.addEventListener('click', function () {
				if (mediaRecorder && mediaRecorder.state === 'recording') {
					mediaRecorder.stop();
					return;
				}
				if (!stream) {
					return;
				}

				chunks = [];
				recordedBlob = null;
				var mime = pickMime();
				try {
					mediaRecorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
				} catch (e) {
					status.textContent = i18n.camError || 'Camera error';
					return;
				}
				recordedMime = mediaRecorder.mimeType || mime || 'video/webm';

				mediaRecorder.ondataavailable = function (e) {
					if (e.data && e.data.size > 0) {
						chunks.push(e.data);
					}
				};

				mediaRecorder.onstop = function () {
					stopTimer();
					recordedBlob = new Blob(chunks, { type: recordedMime });
					stopStream();
					preview.srcObject = null;
					preview.classList.remove('tc-active');
					playback.hidden = false;
					playback.classList.add('tc-active');
					playback.src = URL.createObjectURL(recordedBlob);
					recBtn.hidden = true;
					recBtn.classList.remove('tc-recording');
					recBtn.textContent = i18n.record || 'Record';
					retakeBtn.hidden = false;
				};

				mediaRecorder.start();
				recBtn.classList.add('tc-recording');
				recBtn.textContent = i18n.stop || 'Stop';

				secondsLeft = parseInt(tcForm.maxSeconds, 10) || 120;
				status.textContent = secondsLeft + ' ' + (i18n.secLeft || 's');
				timer = setInterval(function () {
					secondsLeft -= 1;
					status.textContent = secondsLeft + ' ' + (i18n.secLeft || 's');
					if (secondsLeft <= 0) {
						status.textContent = i18n.tooLong || '';
						if (mediaRecorder && mediaRecorder.state === 'recording') {
							mediaRecorder.stop();
						}
					}
				}, 1000);
			});

			retakeBtn.addEventListener('click', function () {
				recordedBlob = null;
				playback.classList.remove('tc-active');
				playback.hidden = true;
				playback.removeAttribute('src');
				retakeBtn.hidden = true;
				camBtn.hidden = false;
				camBtn.click();
			});
		}

		/* ---------- Submit ---------- */
		function showMessage(text, isError) {
			message.hidden = false;
			message.textContent = text;
			message.className = 'tc-message ' + (isError ? 'tc-message-error' : 'tc-message-success');
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			message.hidden = true;

			var name = form.querySelector('input[name="name"]').value.trim();
			var email = form.querySelector('input[name="email"]').value.trim();
			var consentBox = form.querySelector('input[name="consent"]');
			var type = typeInput.value;

			if (!name || !email) {
				showMessage(i18n.required || 'Required fields missing', true);
				return;
			}
			if (tcForm.consentRequired && consentBox && !consentBox.checked) {
				showMessage(i18n.required || 'Required fields missing', true);
				return;
			}
			if (type === 'text' && textarea && !textarea.value.trim()) {
				showMessage(i18n.required || 'Required fields missing', true);
				return;
			}

			var fd = new FormData();
			fd.append('action', 'tc_submit');
			fd.append('nonce', tcForm.nonce);
			fd.append('type', type);
			fd.append('name', name);
			fd.append('email', email);
			fd.append('rating', ratingInput ? ratingInput.value : '5');
			fd.append('content', textarea ? textarea.value : '');
			if (consentBox && consentBox.checked) {
				fd.append('consent', '1');
			}

			['role', 'social', 'headline', 'event'].forEach(function (fieldName) {
				var field = form.querySelector('[name="' + fieldName + '"]');
				if (field && field.value) {
					fd.append(fieldName, field.value);
				}
			});

			var hp = form.querySelector('input[name="tc_website"]');
			if (hp && hp.value) {
				fd.append('tc_website', hp.value);
			}

			var photo = form.querySelector('input[name="photo"]');
			if (photo && photo.files && photo.files[0]) {
				fd.append('photo', photo.files[0]);
			}

			if (type === 'video') {
				var uploadInput = form.querySelector('input[name="video_file"]');
				if (recordedBlob) {
					var ext = recordedMime.indexOf('mp4') !== -1 ? 'mp4' : 'webm';
					fd.append('video', recordedBlob, 'testimonial.' + ext);
				} else if (uploadInput && uploadInput.files && uploadInput.files[0]) {
					fd.append('video', uploadInput.files[0]);
				} else {
					showMessage(i18n.videoMissing || 'Video missing', true);
					return;
				}
				var maxBytes = (parseInt(tcForm.maxMb, 10) || 200) * 1024 * 1024;
				var videoEntry = fd.get('video');
				if (videoEntry && videoEntry.size > maxBytes) {
					showMessage(i18n.error || 'Error', true);
					return;
				}
			}

			submitBtn.disabled = true;
			showMessage(i18n.uploading || '…', false);

			fetch(tcForm.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data && data.success) {
						form.hidden = true;
						if (questions) {
							questions.hidden = true;
						}
						if (thanks) {
							var msgEl = thanks.querySelector('.tc-thanks-msg');
							if (msgEl && data.data && data.data.message) {
								msgEl.textContent = data.data.message;
							}
							thanks.hidden = false;
							thanks.scrollIntoView({ behavior: 'smooth', block: 'center' });
						}
					} else {
						submitBtn.disabled = false;
						showMessage((data && data.data && data.data.message) || i18n.error || 'Error', true);
					}
				})
				.catch(function () {
					submitBtn.disabled = false;
					showMessage(i18n.error || 'Error', true);
				});
		});
	});
})();
