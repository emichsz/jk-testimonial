<?php
/**
 * Built-in HU/EN string tables + settings overrides.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Strings {

	/**
	 * Keys that can be overridden on the settings page.
	 */
	public static function overridable_keys() {
		return array(
			'wall_title'    => __( 'Wall title', 'testimonial-collector' ),
			'wall_subtitle' => __( 'Wall subtitle', 'testimonial-collector' ),
			'form_title'    => __( 'Form title', 'testimonial-collector' ),
			'form_intro'    => __( 'Form intro', 'testimonial-collector' ),
			'tab_video'     => __( 'Video button text', 'testimonial-collector' ),
			'tab_text'      => __( 'Text button text', 'testimonial-collector' ),
			'btn_submit'    => __( 'Submit button', 'testimonial-collector' ),
			'thankyou_title' => __( 'Thank you title', 'testimonial-collector' ),
			'msg_thanks'    => __( 'Thank you message', 'testimonial-collector' ),
			'label_consent' => __( 'Consent checkbox text', 'testimonial-collector' ),
			'questions_title' => __( 'Questions heading', 'testimonial-collector' ),
			'questions'     => __( 'Guiding questions (one per line)', 'testimonial-collector' ),
		);
	}

	public static function table( $lang ) {
		$hu = array(
			'wall_title'      => 'Vélemények',
			'wall_subtitle'   => 'Ismerd meg ügyfeleink történeteit első kézből! Videók és rövid írásos vélemények arról, mit adott nekik a közös munka.',
			'form_title'      => 'Oszd meg a véleményed!',
			'form_intro'      => 'Sokat jelent nekünk, ha megosztod a tapasztalatodat. Írhatsz néhány mondatot, vagy rögzíthetsz egy rövid videót közvetlenül a böngészőből.',
			'questions_title' => 'KÉRDÉSEK',
			'questions'       => "Ki vagy, mivel foglalkozol?\nMiben segített neked a közös munka?\nMi a legjobb dolog a szolgáltatásunkban?",
			'tab_text'        => 'Írásos vélemény',
			'tab_video'       => 'Videós vélemény',
			'label_name'      => 'Név',
			'label_email'     => 'E-mail cím (nem jelenik meg)',
			'label_role'      => 'Cég / szerep (opcionális)',
			'label_social'    => 'Közösségi / weboldal link (opcionális)',
			'label_headline'  => 'Vélemény címe (opcionális)',
			'msg_verify_sent' => 'Majdnem kész! Küldtünk egy megerősítő e-mailt — kattints a benne lévő linkre a beküldés véglegesítéséhez.',
			'msg_verified'    => 'Köszönjük, az e-mail címedet megerősítetted! A véleményed jóváhagyás után jelenik meg az oldalon.',
			'verify_subject'  => 'Erősítsd meg a véleményed beküldését',
			'verify_body'     => "Szia %1\$s!\n\nKöszönjük a véleményedet! Kérjük, erősítsd meg az e-mail címedet az alábbi linkre kattintva:\n\n%2\$s\n\nHa nem te küldted a véleményt, hagyd figyelmen kívül ezt a levelet.",
			'label_upload_video' => 'Videó feltöltése',
			'label_rating'    => 'Értékelés',
			'label_text'      => 'Véleményed',
			'label_photo'     => 'Fotó (opcionális)',
			'label_consent'   => 'Hozzájárulok, hogy a véleményem (nevemmel és fotómmal/videómmal együtt) megjelenjen a weboldalon.',
			'btn_start_cam'   => 'Kamera bekapcsolása',
			'btn_record'      => 'Felvétel indítása',
			'btn_stop'        => 'Felvétel leállítása',
			'btn_retake'      => 'Újrafelvétel',
			'btn_submit'      => 'Vélemény beküldése',
			'thankyou_title'  => 'Köszönjük! 🙏',
			'msg_thanks'      => 'Köszönjük! A véleményedet megkaptuk, jóváhagyás után jelenik meg az oldalon.',
			'msg_error'       => 'Hiba történt a beküldés során. Kérjük, próbáld újra!',
			'msg_required'    => 'Kérjük, töltsd ki a kötelező mezőket!',
			'msg_video_missing' => 'Kérjük, rögzíts egy videót a beküldés előtt!',
			'msg_cam_error'   => 'Nem sikerült elérni a kamerát. Ellenőrizd a böngésző engedélyeit!',
			'msg_too_long'    => 'A videó elérte a maximális hosszt, a felvétel leállt.',
			'msg_uploading'   => 'Feltöltés folyamatban…',
			'badge'           => 'Vélemény',
			'sec_left'        => 'másodperc van hátra',
			'prev'            => 'Előző',
			'next'            => 'Következő',
			'no_items'        => 'Még nincsenek jóváhagyott vélemények.',
		);

		$en = array(
			'wall_title'      => 'Testimonials',
			'wall_subtitle'   => 'Explore the stories of our clients firsthand. A collection of videos and concise text narratives showcasing the impact of our work together.',
			'form_title'      => 'Share your experience!',
			'form_intro'      => 'It means a lot to us when you share your experience. Write a few sentences, or record a short video right here in your browser.',
			'questions_title' => 'QUESTIONS',
			'questions'       => "Who are you / what are you working on?\nHow has working with us helped you?\nWhat is the best thing about our service?",
			'tab_text'        => 'Text testimonial',
			'tab_video'       => 'Video testimonial',
			'label_name'      => 'Name',
			'label_email'     => 'Email (never shown publicly)',
			'label_role'      => 'Company / role (optional)',
			'label_social'    => 'Social / website link (optional)',
			'label_headline'  => 'Testimonial title (optional)',
			'msg_verify_sent' => 'Almost done! We sent you a confirmation email — click the link in it to finalize your submission.',
			'msg_verified'    => 'Thank you, your email address is confirmed! Your testimonial will appear on the site after approval.',
			'verify_subject'  => 'Confirm your testimonial submission',
			'verify_body'     => "Hi %1\$s!\n\nThank you for your testimonial! Please confirm your email address by clicking the link below:\n\n%2\$s\n\nIf you did not submit a testimonial, please ignore this email.",
			'label_upload_video' => 'Upload a video',
			'label_rating'    => 'Rating',
			'label_text'      => 'Your testimonial',
			'label_photo'     => 'Photo (optional)',
			'label_consent'   => 'I agree that my testimonial (with my name and photo/video) may be published on this website.',
			'btn_start_cam'   => 'Turn on camera',
			'btn_record'      => 'Start recording',
			'btn_stop'        => 'Stop recording',
			'btn_retake'      => 'Record again',
			'btn_submit'      => 'Submit testimonial',
			'thankyou_title'  => 'Thank you! 🙏',
			'msg_thanks'      => 'Thank you! We received your testimonial — it will appear on the site after approval.',
			'msg_error'       => 'Something went wrong. Please try again!',
			'msg_required'    => 'Please fill in the required fields!',
			'msg_video_missing' => 'Please record a video before submitting!',
			'msg_cam_error'   => 'Could not access the camera. Please check your browser permissions!',
			'msg_too_long'    => 'The video reached the maximum length, recording stopped.',
			'msg_uploading'   => 'Uploading…',
			'badge'           => 'Testimonial',
			'sec_left'        => 'seconds left',
			'prev'            => 'Previous',
			'next'            => 'Next',
			'no_items'        => 'No approved testimonials yet.',
		);

		return ( 'hu' === $lang ) ? $hu : $en;
	}

	/**
	 * Final strings: built-in table + non-empty settings overrides.
	 */
	public static function get() {
		$lang     = tc_get_language();
		$strings  = self::table( $lang );
		$settings = tc_get_settings();

		foreach ( array_keys( self::overridable_keys() ) as $key ) {
			$override = isset( $settings[ $key . '_' . $lang ] ) ? trim( (string) $settings[ $key . '_' . $lang ] ) : '';
			if ( '' !== $override ) {
				$strings[ $key ] = $override;
			}
		}

		return $strings;
	}
}
