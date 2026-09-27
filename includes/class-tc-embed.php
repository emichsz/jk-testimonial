<?php
/**
 * Embed mode: serves the form / wall as a bare, theme-less page that other
 * sites (even non-WordPress ones) can load in an iframe.
 *
 *   https://example.com/?tc_embed=form[&event=...]
 *   https://example.com/?tc_embed=wall[&event=...][&per_page=4]
 *
 * The host page normally does not build the iframe by hand but drops in
 * assets/js/tc-embed.js, which creates it with camera/microphone permission
 * and keeps its height in sync via postMessage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Embed {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 0 );
	}

	/**
	 * Allowed parent origins (scheme://host[:port]), from the settings.
	 */
	public static function allowed_origins() {
		$settings = tc_get_settings();
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $settings['embed_origins'] ) ) ) );
	}

	/**
	 * Normalise one line of user input to "scheme://host[:port]", or '' if invalid.
	 */
	public static function normalize_origin( $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			return '';
		}
		if ( false === strpos( $line, '://' ) ) {
			$line = 'https://' . $line;
		}
		$parts = wp_parse_url( $line );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		$host = strtolower( $parts['host'] );
		if ( ! preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
			return '';
		}
		$origin = strtolower( $parts['scheme'] ) . '://' . $host;
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}
		return $origin;
	}

	/**
	 * Base URL of the embed endpoint (what the loader script appends ?tc_embed=... to).
	 */
	public static function endpoint_url() {
		return home_url( '/' );
	}

	public static function maybe_render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only view.
		if ( ! isset( $_GET['tc_embed'] ) ) {
			return;
		}
		$view = sanitize_key( wp_unslash( $_GET['tc_embed'] ) );
		if ( ! in_array( $view, array( 'form', 'wall' ), true ) ) {
			return;
		}
		$event    = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 0;
		// phpcs:enable

		// Never let a page cache store this: the form carries a nonce.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		$origins = self::allowed_origins();
		if ( empty( $origins ) ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html__( 'Embedding is disabled. Add the allowed sites under Testimonials → Settings → Embed.', 'testimonial-collector' );
			exit;
		}

		// Only the listed sites (and this site) may frame the page.
		if ( function_exists( 'header_remove' ) ) {
			header_remove( 'X-Frame-Options' );
			header_remove( 'Permissions-Policy' );
		}
		header( "Content-Security-Policy: frame-ancestors 'self' " . implode( ' ', $origins ) );
		// Let the parent's allow="camera; microphone" actually reach the recorder.
		header( 'Permissions-Policy: camera=(self), microphone=(self), fullscreen=(self)' );
		status_header( 200 );

		TC_Shortcodes::register_assets();
		wp_register_script( 'tc-embed-frame', TC_PLUGIN_URL . 'assets/js/tc-embed-frame.js', array(), TC_VERSION, true );
		wp_add_inline_style(
			'tc-frontend',
			'html,body{margin:0;padding:0;background:transparent;}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;}.tc-embed-root{display:flow-root;}.tc-embed-root .tc-container{margin:0 auto;}.tc-embed-root .tc-container+.tc-container{margin-top:24px;}'
		);

		$atts = array();
		if ( '' !== $event ) {
			$atts['event'] = $event;
		}
		if ( 'wall' === $view && $per_page ) {
			$atts['per_page'] = $per_page;
		}
		$body = ( 'form' === $view ) ? TC_Shortcodes::render_form( $atts ) : TC_Shortcodes::render_wall( $atts );

		$scripts = array_values(
			array_filter(
				array( 'tc-form', 'tc-wall' ),
				function ( $handle ) {
					return wp_script_is( $handle, 'enqueued' );
				}
			)
		);
		$scripts[] = 'tc-embed-frame';

		?><!DOCTYPE html>
<html lang="<?php echo esc_attr( tc_get_language() ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
<?php wp_print_styles( array( 'tc-frontend' ) ); ?>
</head>
<body>
<div class="tc-embed-root">
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderers. ?>
</div>
<?php wp_print_scripts( $scripts ); ?>
</body>
</html>
		<?php
		exit;
	}
}
