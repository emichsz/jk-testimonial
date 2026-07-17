<?php
/**
 * Public submission endpoint (text + video upload).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Ajax {

	public static function init() {
		add_action( 'wp_ajax_tc_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_tc_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_tc_verify', array( __CLASS__, 'handle_verify' ) );
		add_action( 'wp_ajax_nopriv_tc_verify', array( __CLASS__, 'handle_verify' ) );
	}

	public static function handle_submit() {
		$strings = TC_Strings::get();

		if ( ! check_ajax_referer( 'tc_submit', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => $strings['msg_error'] ), 403 );
		}

		// Honeypot: real visitors leave it empty.
		if ( ! empty( $_POST['tc_website'] ) ) {
			wp_send_json_success( array( 'message' => $strings['msg_thanks'] ) );
		}

		$settings = tc_get_settings();

		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$role     = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		$social   = isset( $_POST['social'] ) ? esc_url_raw( wp_unslash( $_POST['social'] ) ) : '';
		$headline = isset( $_POST['headline'] ) ? sanitize_text_field( wp_unslash( $_POST['headline'] ) ) : '';
		$event    = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
		$rating   = isset( $_POST['rating'] ) ? max( 1, min( 5, absint( $_POST['rating'] ) ) ) : 5;
		$type     = ( isset( $_POST['type'] ) && 'video' === $_POST['type'] ) ? 'video' : 'text';
		$content  = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		$consent  = ! empty( $_POST['consent'] );

		if ( $settings['max_chars'] > 0 ) {
			$content = mb_substr( $content, 0, (int) $settings['max_chars'] );
		}
		if ( 'hidden' === $settings['consent_mode'] ) {
			$consent = true;
		}
		$consent_ok = ( 'required' !== $settings['consent_mode'] ) || $consent;

		if ( '' === $name || ! is_email( $email ) || ! $consent_ok ) {
			wp_send_json_error( array( 'message' => $strings['msg_required'] ), 400 );
		}
		if ( 'text' === $type && '' === trim( $content ) ) {
			wp_send_json_error( array( 'message' => $strings['msg_required'] ), 400 );
		}
		if ( 'video' === $type && empty( $_FILES['video']['name'] ) ) {
			wp_send_json_error( array( 'message' => $strings['msg_video_missing'] ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// --- Video upload ---
		$video_id = 0;
		if ( 'video' === $type ) {
			$max_bytes = $settings['video_max_mb'] * 1024 * 1024;
			if ( ! empty( $_FILES['video']['size'] ) && $_FILES['video']['size'] > $max_bytes ) {
				wp_send_json_error( array( 'message' => $strings['msg_error'] ), 400 );
			}

			$allowed = array(
				'webm' => 'video/webm',
				'mp4'  => 'video/mp4',
				'mov'  => 'video/quicktime',
			);
			$check = wp_check_filetype_and_ext(
				$_FILES['video']['tmp_name'],
				sanitize_file_name( $_FILES['video']['name'] ),
				$allowed
			);
			if ( empty( $check['ext'] ) || ! isset( $allowed[ $check['ext'] ] ) ) {
				wp_send_json_error( array( 'message' => $strings['msg_error'] ), 400 );
			}

			$video_id = media_handle_upload(
				'video',
				0,
				array(),
				array(
					'test_form' => false,
					'mimes'     => $allowed,
				)
			);
			if ( is_wp_error( $video_id ) ) {
				wp_send_json_error( array( 'message' => $strings['msg_error'] ), 400 );
			}
		}

		// --- Optional avatar photo ---
		$avatar_id = 0;
		if ( ! empty( $_FILES['photo']['name'] ) ) {
			$img_mimes = array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
			);
			$avatar_id = media_handle_upload(
				'photo',
				0,
				array(),
				array(
					'test_form' => false,
					'mimes'     => $img_mimes,
				)
			);
			if ( is_wp_error( $avatar_id ) ) {
				$avatar_id = 0; // Photo is optional: ignore failures.
			}
		}

		$needs_verify = ! empty( $settings['verify_email'] );

		$post_id = wp_insert_post(
			array(
				'post_type'    => TC_CPT,
				'post_status'  => $needs_verify ? 'draft' : 'pending',
				'post_title'   => $name,
				'post_content' => ( 'text' === $type ) ? $content : '',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $strings['msg_error'] ), 500 );
		}

		update_post_meta( $post_id, '_tc_name', $name );
		update_post_meta( $post_id, '_tc_email', $email );
		update_post_meta( $post_id, '_tc_role', $role );
		update_post_meta( $post_id, '_tc_rating', $rating );
		update_post_meta( $post_id, '_tc_type', $type );
		update_post_meta( $post_id, '_tc_consent', $consent ? 1 : 0 );
		if ( $social ) {
			update_post_meta( $post_id, '_tc_social', $social );
		}
		if ( $headline ) {
			update_post_meta( $post_id, '_tc_headline', $headline );
		}
		if ( $event ) {
			// Only accept values that exist in the configured list.
			$allowed_events = array_filter( array_map( 'trim', explode( "\n", (string) $settings['events'] ) ) );
			if ( in_array( $event, $allowed_events, true ) ) {
				update_post_meta( $post_id, '_tc_event', $event );
			}
		}
		if ( $video_id ) {
			update_post_meta( $post_id, '_tc_video_id', $video_id );
			wp_update_post( array( 'ID' => $video_id, 'post_parent' => $post_id ) );
		}
		if ( $avatar_id ) {
			update_post_meta( $post_id, '_tc_avatar_id', $avatar_id );
			wp_update_post( array( 'ID' => $avatar_id, 'post_parent' => $post_id ) );
		}

		if ( $needs_verify ) {
			$key = wp_generate_password( 24, false, false );
			update_post_meta( $post_id, '_tc_verify_key', $key );

			$verify_url = add_query_arg(
				array(
					'action' => 'tc_verify',
					'id'     => $post_id,
					'key'    => $key,
				),
				admin_url( 'admin-ajax.php' )
			);
			wp_mail(
				$email,
				$strings['verify_subject'],
				sprintf( $strings['verify_body'], $name, $verify_url )
			);

			wp_send_json_success( array( 'message' => $strings['msg_verify_sent'] ) );
		}

		TC_Admin::notify_new_submission( $post_id );

		wp_send_json_success( array( 'message' => $strings['msg_thanks'] ) );
	}

	/**
	 * Email verification link handler.
	 */
	public static function handle_verify() {
		$strings = TC_Strings::get();
		$post_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		$stored = $post_id ? get_post_meta( $post_id, '_tc_verify_key', true ) : '';

		if ( ! $post_id || '' === $key || '' === $stored || ! hash_equals( $stored, $key ) || get_post_type( $post_id ) !== TC_CPT ) {
			wp_die( esc_html( $strings['msg_error'] ), '', array( 'response' => 400 ) );
		}

		delete_post_meta( $post_id, '_tc_verify_key' );
		if ( 'draft' === get_post_status( $post_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
			TC_Admin::notify_new_submission( $post_id );
		}

		wp_die(
			'<div style="font-family:sans-serif;max-width:480px;margin:80px auto;text-align:center;font-size:18px;line-height:1.6;">✅<br>' . esc_html( $strings['msg_verified'] ) . '<br><br><a href="' . esc_url( home_url() ) . '">&larr; ' . esc_html( get_bloginfo( 'name' ) ) . '</a></div>',
			esc_html( $strings['msg_verified'] ),
			array( 'response' => 200 )
		);
	}
}
