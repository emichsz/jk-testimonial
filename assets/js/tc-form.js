/* Testimonial Collector — submission form + in-browser video recording */
(function () {
	'use strict';

	if (typeof tcForm === 'undefined') {
		return;
	}

	var i18n = tcForm.i18n || {};
	// wp_localize_script turns numbers into strings, and "0" is truthy — compare numerically.
	var iosNoRecord = parseInt(tcForm.iosNoRecord, 10) === 1;
	var consentRequired = parseInt(tcForm.consentRequired, 10) === 1;

	function isIOS() {
		return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
			(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	}

	var PHOTO_SIZE = 600;

	// Phones and tablets: the native camera app takes better selfies than a live preview.
	function isTouchDevice() {
		return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
	}

	// Center-crop a drawable (img / video / canvas) to a square JPEG, at most PHOTO_SIZE px.
	// mirror: flip horizontally, so a live selfie is saved the way the person saw it.
	function squareJpeg(src, w, h, mirror) {
		return new Promise(function (resolve) {
			var side = Math.min(w, h);
			var size = Math.min(PHOTO_SIZE, side);
			var canvas = document.createElement('canvas');
			canvas.width = canvas.height = size;
			var ctx = canvas.getContext('2d');
			if (mirror) {
				ctx.translate(size, 0);
				ctx.scale(-1, 1);
			}
			ctx.drawImage(src, (w - side) / 2, (h - side) / 2, side, side, 0, 0, size, size);
			canvas.toBlob(resolve, 'image/jpeg', 0.88);
		});
	}

	/* ---------- Photo: camera snapshot, upload, or a frame from the video ---------- */
	function setupPhoto(wrap) {
		if (!wrap) {
			return null;
		}
		var img = wrap.querySelector('.tc-photo-img');
		var live = wrap.querySelector('.tc-photo-live');
		var placeholder = wrap.querySelector('.tc-photo-placeholder');
		var note = wrap.querySelector('.tc-photo-note');
		var takeBtn = wrap.querySelector('.tc-photo-take');
		var snapBtn = wrap.querySelector('.tc-photo-snap');
		var cancelBtn = wrap.querySelector('.tc-photo-cancel');
		var uploadBtn = wrap.querySelector('.tc-photo-upload');
		var removeBtn = wrap.querySelector('.tc-photo-remove');
		var captureInput = wrap.querySelector('.tc-photo-capture');
		var fileInput = wrap.querySelector('.tc-photo-file');

		var hint = note.textContent;
		var canLive = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
		var blob = null;
		var source = ''; // camera | upload | video
		var stream = null;
		var url = null;

		function show(state) { // empty | live | photo
			placeholder.hidden = state !== 'empty';
			live.hidden = state !== 'live';
			img.hidden = state !== 'photo';
			takeBtn.hidden = state === 'live';
			uploadBtn.hidden = state === 'live';
			snapBtn.hidden = state !== 'live';
			cancelBtn.hidden = state !== 'live';
			removeBtn.hidden = state !== 'photo';
			takeBtn.textContent = state === 'photo' ? (i18n.photoRetake || 'New photo') : (i18n.photoTake || 'Take a photo');
		}

		function stopLive() {
			if (stream) {
				stream.getTracks().forEach(function (t) { t.stop(); });
				stream = null;
			}
			live.srcObject = null;
		}

		function setPhoto(b, src) {
			if (!b) {
				return;
			}
			blob = b;
			source = src;
			if (url) {
				URL.revokeObjectURL(url);
			}
			url = URL.createObjectURL(b);
			img.src = url;
			note.textContent = src === 'video' ? (i18n.photoFromVideo || hint) : hint;
			show('photo');
		}

		function clear() {
			blob = null;
			source = '';
			if (url) {
				URL.revokeObjectURL(url);
				url = null;
			}
			img.removeAttribute('src');
			note.textContent = hint;
			show('empty');
		}

		function fromFile(file) {
			if (!file) {
				return;
			}
			var fileUrl = URL.createObjectURL(file);
			var image = new Image();
			image.onload = function () {
				// Browsers apply the EXIF orientation here, so phone photos come out upright.
				squareJpeg(image, image.naturalWidth, image.naturalHeight, false).then(function (b) {
					URL.revokeObjectURL(fileUrl);
					setPhoto(b, 'upload');
				});
			};
			image.onerror = function () {
				URL.revokeObjectURL(fileUrl);
			};
			image.src = fileUrl;
		}

		function startLive() {
			navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false })
				.then(function (s) {
					stream = s;
					live.srcObject = s;
					live.play();
					show('live');
				})
				.catch(function () {
					note.textContent = i18n.camError || 'Camera error';
				});
		}

		takeBtn.addEventListener('click', function () {
			if (isTouchDevice() || !canLive) {
				captureInput.click();
			} else {
				startLive();
			}
		});
		function snap() {
			if (!stream) {
				return; // cancelled meanwhile
			}
			squareJpeg(live, live.videoWidth, live.videoHeight, true).then(function (b) {
				stopLive();
				setPhoto(b, 'camera');
			});
		}
		snapBtn.addEventListener('click', function () {
			if (live.videoWidth) {
				snap();
			} else {
				live.addEventListener('loadeddata', snap, { once: true }); // clicked before the first frame
			}
		});
		cancelBtn.addEventListener('click', function () {
			stopLive();
			show(blob ? 'photo' : 'empty');
		});
		uploadBtn.addEventListener('click', function () {
			fileInput.click();
		});
		[captureInput, fileInput].forEach(function (input) {
			input.addEventListener('change', function () {
				fromFile(input.files && input.files[0]);
				input.value = ''; // picking the same file again must fire change again
			});
		});
		removeBtn.addEventListener('click', clear);

		show('empty');

		return {
			blob: function () { return blob; },
			stop: stopLive,
			// A frame from the testimonial video — only while the person has not chosen a photo themselves.
			fromVideo: function (video) {
				if ((source && source !== 'video') || !video.videoWidth) {
					return;
				}
				squareJpeg(video, video.videoWidth, video.videoHeight, false).then(function (b) {
					if (!source || source === 'video') {
						setPhoto(b, 'video');
					}
				});
			}
		};
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
		var photo = setupPhoto(form.querySelector('.tc-photo'));

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

			if (!recordingSupported || (iosNoRecord && isIOS())) {
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
					if (photo && !photo.blob()) {
						photo.fromVideo(preview); // short recording: take the frame before the camera stops
					}
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
				if (photo) {
					// A couple of seconds in, the person is usually settled and looking at the camera.
					setTimeout(function () {
						if (mediaRecorder && mediaRecorder.state === 'recording') {
							photo.fromVideo(preview);
						}
					}, 2000);
				}
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

			// Uploaded video file: grab a frame from it as well (best effort).
			var videoFileInput = form.querySelector('input[name="video_file"]');
			if (photo && videoFileInput) {
				videoFileInput.addEventListener('change', function () {
					var file = videoFileInput.files && videoFileInput.files[0];
					if (!file) {
						return;
					}
					var v = document.createElement('video');
					var vUrl = URL.createObjectURL(file);
					var done = false;
					function grab() {
						if (done) {
							return;
						}
						done = true;
						photo.fromVideo(v);
						URL.revokeObjectURL(vUrl);
					}
					v.muted = true;
					v.playsInline = true;
					v.preload = 'auto';
					v.addEventListener('loadeddata', function () {
						var t = (isFinite(v.duration) && v.duration > 0) ? Math.min(2, v.duration / 3) : 0;
						if (t > 0) {
							v.currentTime = t;
						} else {
							grab();
						}
					});
					v.addEventListener('seeked', grab);
					v.src = vUrl;
				});
			}

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
			if (consentRequired && consentBox && !consentBox.checked) {
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

			var photoBlob = photo && photo.blob();
			if (photoBlob) {
				fd.append('photo', photoBlob, 'photo.jpg');
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

			function send() {
				return fetch(tcForm.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function (r) { return r.json().then(function (data) { return { status: r.status, data: data }; }); });
			}

			// A page cache (or a long-open tab) can hand out an expired nonce:
			// on 403 fetch a fresh one and retry once.
			function refreshNonce() {
				var nf = new FormData();
				nf.append('action', 'tc_nonce');
				return fetch(tcForm.ajaxUrl, { method: 'POST', body: nf, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (!res || !res.success || !res.data || !res.data.nonce) {
							throw new Error('nonce');
						}
						tcForm.nonce = res.data.nonce;
						fd.set('nonce', tcForm.nonce);
					});
			}

			send()
				.then(function (res) {
					if (res.status === 403) {
						return refreshNonce().then(send).then(function (retry) { return retry.data; });
					}
					return res.data;
				})
				.then(function (data) {
					if (data && data.success) {
						if (photo) {
							photo.stop();
						}
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
							if (window.parent === window) {
								// In an embed iframe the host page scrolls instead (tc-embed.js).
								thanks.scrollIntoView({ behavior: 'smooth', block: 'center' });
							}
						}
						container.dispatchEvent(new CustomEvent('tc:submitted', { bubbles: true }));
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
