<?php
/**
 * GitHub-based plugin updates.
 *
 * Checks the repo's latest release; if the tag is newer than TC_VERSION,
 * WordPress offers the update on the Plugins screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Updater {

	const GITHUB_REPO = 'emichsz/jk-testimonial';
	const CACHE_KEY   = 'tc_github_release';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * Latest release info from GitHub (cached for 6 hours).
	 */
	protected static function get_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		$release = null;
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$release = json_decode( wp_remote_retrieve_body( $response ) );
			if ( empty( $release->tag_name ) ) {
				$release = null;
			}
		}

		// Fallback: no release yet — read the version from the main branch header.
		if ( ! $release ) {
			$release = self::release_from_main_branch();
		}

		set_site_transient( self::CACHE_KEY, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Build a release-like object from the main branch plugin header.
	 */
	protected static function release_from_main_branch() {
		$raw = wp_remote_get(
			'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/main/testimonial-collector.php',
			array( 'timeout' => 10 )
		);
		if ( is_wp_error( $raw ) || 200 !== wp_remote_retrieve_response_code( $raw ) ) {
			return null;
		}
		if ( ! preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/mi', wp_remote_retrieve_body( $raw ), $m ) ) {
			return null;
		}
		return (object) array(
			'tag_name'    => 'v' . $m[1],
			'zipball_url' => 'https://api.github.com/repos/' . self::GITHUB_REPO . '/zipball/main',
			'body'        => '',
			'assets'      => array(),
		);
	}

	protected static function get_package_url( $release ) {
		// Prefer an uploaded .zip asset; fall back to the auto zipball.
		if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( ! empty( $asset->browser_download_url ) && '.zip' === substr( $asset->name, -4 ) ) {
					return $asset->browser_download_url;
				}
			}
		}
		return ! empty( $release->zipball_url ) ? $release->zipball_url : '';
	}

	public static function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_release();
		if ( ! $release ) {
			return $transient;
		}

		$new_version = ltrim( $release->tag_name, 'v' );
		if ( ! version_compare( $new_version, TC_VERSION, '>' ) ) {
			return $transient;
		}

		$package = self::get_package_url( $release );
		if ( ! $package ) {
			return $transient;
		}

		$basename = plugin_basename( TC_PLUGIN_FILE );

		$transient->response[ $basename ] = (object) array(
			'slug'        => dirname( $basename ),
			'plugin'      => $basename,
			'new_version' => $new_version,
			'url'         => 'https://github.com/' . self::GITHUB_REPO,
			'package'     => $package,
		);

		return $transient;
	}

	/**
	 * "View details" popup content.
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( plugin_basename( TC_PLUGIN_FILE ) ) !== $args->slug ) {
			return $result;
		}

		$release = self::get_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Testimonial Collector',
			'slug'          => $args->slug,
			'version'       => ltrim( $release->tag_name, 'v' ),
			'author'        => 'Emich',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'download_link' => self::get_package_url( $release ),
			'sections'      => array(
				'description' => ! empty( $release->body ) ? wp_kses_post( nl2br( $release->body ) ) : 'Testimonial Collector',
			),
		);
	}

	/**
	 * GitHub zipballs extract to "owner-repo-hash/"; rename so the
	 * plugin folder stays "testimonial-collector".
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || plugin_basename( TC_PLUGIN_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}

		global $wp_filesystem;
		$desired = trailingslashit( $remote_source ) . 'testimonial-collector/';

		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $desired ) ) ) {
			return $desired;
		}
		return $source;
	}
}
