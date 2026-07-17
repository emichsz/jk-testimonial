<?php
/**
 * Settings page with tabbed sections and live preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . TC_CPT,
			__( 'Testimonial Settings', 'testimonial-collector' ),
			__( 'Settings', 'testimonial-collector' ),
			'manage_options',
			'tc-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'tc-settings' ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_style( 'tc-frontend', TC_PLUGIN_URL . 'assets/css/tc-frontend.css', array(), TC_VERSION );
		wp_enqueue_style( 'tc-admin', TC_PLUGIN_URL . 'assets/css/tc-admin.css', array(), TC_VERSION );
		wp_enqueue_script(
			'tc-admin-settings',
			TC_PLUGIN_URL . 'assets/js/tc-admin-settings.js',
			array( 'jquery', 'wp-color-picker' ),
			TC_VERSION,
			true
		);

		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		wp_localize_script(
			'tc-admin-settings',
			'tcSettingsPreview',
			array(
				'option'   => TC_OPTION,
				'autoLang' => ( 0 === strpos( $locale, 'hu' ) ) ? 'hu' : 'en',
				'strings'  => array(
					'hu' => TC_Strings::table( 'hu' ),
					'en' => TC_Strings::table( 'en' ),
				),
			)
		);
	}

	public static function register() {
		register_setting( 'tc_settings_group', TC_OPTION, array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$out      = array();
		$defaults = tc_default_settings();
		$input    = is_array( $input ) ? $input : array();

		$out['language']        = in_array( $input['language'] ?? 'auto', array( 'auto', 'hu', 'en' ), true ) ? $input['language'] : 'auto';
		$out['collection_type'] = in_array( $input['collection_type'] ?? 'both', array( 'both', 'text', 'video' ), true ) ? $input['collection_type'] : 'both';
		$out['theme']           = in_array( $input['theme'] ?? 'light', array( 'light', 'dark' ), true ) ? $input['theme'] : 'light';
		$out['consent_mode']    = in_array( $input['consent_mode'] ?? 'required', array( 'required', 'optional', 'hidden' ), true ) ? $input['consent_mode'] : 'required';

		foreach ( array( 'collect_rating', 'show_role', 'show_social', 'show_photo', 'show_title', 'form_show_wall', 'verify_email', 'ios_no_record', 'thankyou_show_image' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$out['logo_id']           = absint( $input['logo_id'] ?? 0 );
		$out['thankyou_image_id'] = absint( $input['thankyou_image_id'] ?? 0 );

		foreach ( array( 'color_primary', 'color_accent', 'color_bg', 'color_text_on_primary' ) as $color_key ) {
			$val               = sanitize_hex_color( $input[ $color_key ] ?? '' );
			$out[ $color_key ] = $val ? $val : $defaults[ $color_key ];
		}

		$out['per_page']          = max( 1, min( 24, absint( $input['per_page'] ?? $defaults['per_page'] ) ) );
		$out['max_chars']         = min( 10000, absint( $input['max_chars'] ?? 0 ) );
		$out['video_max_seconds'] = max( 10, min( 600, absint( $input['video_max_seconds'] ?? $defaults['video_max_seconds'] ) ) );
		$out['video_max_mb']      = max( 5, min( 1024, absint( $input['video_max_mb'] ?? $defaults['video_max_mb'] ) ) );

		$out['events'] = sanitize_textarea_field( $input['events'] ?? '' );

		$notify              = sanitize_email( $input['notify_email'] ?? '' );
		$out['notify_email'] = $notify ? $notify : $defaults['notify_email'];

		foreach ( array_keys( TC_Strings::overridable_keys() ) as $key ) {
			foreach ( array( 'hu', 'en' ) as $lang ) {
				$field         = $key . '_' . $lang;
				$out[ $field ] = sanitize_textarea_field( $input[ $field ] ?? '' );
			}
		}

		return $out;
	}

	/**
	 * A HU/EN override textarea pair.
	 */
	protected static function text_pair( $s, $key, $label, $rows = 2 ) {
		$default_hu = TC_Strings::table( 'hu' )[ $key ] ?? '';
		$default_en = TC_Strings::table( 'en' )[ $key ] ?? '';
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<p class="tc-pair">
					<span class="tc-pair-lang">HU</span>
					<textarea data-tc-text="<?php echo esc_attr( $key ); ?>" data-tc-lang="hu" name="<?php echo esc_attr( TC_OPTION ); ?>[<?php echo esc_attr( $key ); ?>_hu]" rows="<?php echo esc_attr( $rows ); ?>" class="large-text" placeholder="<?php echo esc_attr( $default_hu ); ?>"><?php echo esc_textarea( $s[ $key . '_hu' ] ?? '' ); ?></textarea>
				</p>
				<p class="tc-pair">
					<span class="tc-pair-lang">EN</span>
					<textarea data-tc-text="<?php echo esc_attr( $key ); ?>" data-tc-lang="en" name="<?php echo esc_attr( TC_OPTION ); ?>[<?php echo esc_attr( $key ); ?>_en]" rows="<?php echo esc_attr( $rows ); ?>" class="large-text" placeholder="<?php echo esc_attr( $default_en ); ?>"><?php echo esc_textarea( $s[ $key . '_en' ] ?? '' ); ?></textarea>
				</p>
			</td>
		</tr>
		<?php
	}

	protected static function checkbox( $s, $key, $label ) {
		?>
		<label><input type="checkbox" data-tc-flag="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( TC_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label><br>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s            = tc_get_settings();
		$opt          = TC_OPTION;
		$logo_url     = $s['logo_id'] ? wp_get_attachment_image_url( $s['logo_id'], 'medium' ) : '';
		$ty_url       = $s['thankyou_image_id'] ? wp_get_attachment_image_url( $s['thankyou_image_id'], 'large' ) : '';
		?>
		<div class="wrap tc-settings-wrap">
			<h1><?php esc_html_e( 'Testimonial Settings', 'testimonial-collector' ); ?></h1>
			<p>
				<?php esc_html_e( 'Shortcodes:', 'testimonial-collector' ); ?>
				<code>[testimonial_form]</code> — <?php esc_html_e( 'submission form', 'testimonial-collector' ); ?>,
				<code>[testimonial_wall]</code> — <?php esc_html_e( 'approved testimonials wall', 'testimonial-collector' ); ?>
			</p>

			<div class="tc-settings-layout">
				<div class="tc-settings-main">
					<h2 class="nav-tab-wrapper tc-nav-tabs">
						<a href="#tc-tab-basic" class="nav-tab nav-tab-active">⚙️ <?php esc_html_e( 'Basic', 'testimonial-collector' ); ?></a>
						<a href="#tc-tab-texts" class="nav-tab">💬 <?php esc_html_e( 'Texts & Questions', 'testimonial-collector' ); ?></a>
						<a href="#tc-tab-thankyou" class="nav-tab">💚 <?php esc_html_e( 'Thank you page', 'testimonial-collector' ); ?></a>
						<a href="#tc-tab-extra" class="nav-tab">🎛️ <?php esc_html_e( 'Extra settings', 'testimonial-collector' ); ?></a>
						<a href="#tc-tab-notify" class="nav-tab">✉️ <?php esc_html_e( 'Notifications', 'testimonial-collector' ); ?></a>
					</h2>

					<form method="post" action="options.php">
						<?php settings_fields( 'tc_settings_group' ); ?>

						<div id="tc-tab-basic" class="tc-tab-panel">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="tc_language"><?php esc_html_e( 'Language', 'testimonial-collector' ); ?></label></th>
									<td>
										<select id="tc_language" data-tc-field="language" name="<?php echo esc_attr( $opt ); ?>[language]">
											<option value="auto" <?php selected( $s['language'], 'auto' ); ?>><?php esc_html_e( 'Automatic (site language)', 'testimonial-collector' ); ?></option>
											<option value="hu" <?php selected( $s['language'], 'hu' ); ?>>Magyar</option>
											<option value="en" <?php selected( $s['language'], 'en' ); ?>>English</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="tc_collection_type"><?php esc_html_e( 'Collection type', 'testimonial-collector' ); ?></label></th>
									<td>
										<select id="tc_collection_type" data-tc-field="collection_type" name="<?php echo esc_attr( $opt ); ?>[collection_type]">
											<option value="both" <?php selected( $s['collection_type'], 'both' ); ?>><?php esc_html_e( 'Text and video', 'testimonial-collector' ); ?></option>
											<option value="text" <?php selected( $s['collection_type'], 'text' ); ?>><?php esc_html_e( 'Text only', 'testimonial-collector' ); ?></option>
											<option value="video" <?php selected( $s['collection_type'], 'video' ); ?>><?php esc_html_e( 'Video only', 'testimonial-collector' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Star ratings', 'testimonial-collector' ); ?></th>
									<td><?php self::checkbox( $s, 'collect_rating', __( 'Collect and display star ratings', 'testimonial-collector' ) ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Collect extra information', 'testimonial-collector' ); ?></th>
									<td>
										<?php
										self::checkbox( $s, 'show_role', __( 'Company / role', 'testimonial-collector' ) );
										self::checkbox( $s, 'show_social', __( 'Social / website link', 'testimonial-collector' ) );
										self::checkbox( $s, 'show_photo', __( 'Photo upload', 'testimonial-collector' ) );
										self::checkbox( $s, 'show_title', __( 'Testimonial title (text submissions)', 'testimonial-collector' ) );
										?>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="tc_events"><?php esc_html_e( 'Events / programs / trainings', 'testimonial-collector' ); ?></label></th>
									<td>
										<textarea id="tc_events" name="<?php echo esc_attr( $opt ); ?>[events]" rows="4" class="large-text" placeholder="<?php esc_attr_e( "Leadership training 2026\nTheory U workshop\nTeam retreat", 'testimonial-collector' ); ?>"><?php echo esc_textarea( $s['events'] ); ?></textarea>
										<p class="description"><?php esc_html_e( 'One per line. If filled, submitters pick from a dropdown which event or program their testimonial is about. Leave empty to hide the dropdown.', 'testimonial-collector' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="tc_theme"><?php esc_html_e( 'Theme', 'testimonial-collector' ); ?></label></th>
									<td>
										<select id="tc_theme" data-tc-field="theme" name="<?php echo esc_attr( $opt ); ?>[theme]">
											<option value="light" <?php selected( $s['theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'testimonial-collector' ); ?></option>
											<option value="dark" <?php selected( $s['theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'testimonial-collector' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Logo', 'testimonial-collector' ); ?></th>
									<td>
										<div class="tc-media-preview" data-tc-media-preview="logo" <?php echo $logo_url ? '' : 'hidden'; ?>>
											<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
										</div>
										<input type="hidden" data-tc-media="logo" name="<?php echo esc_attr( $opt ); ?>[logo_id]" value="<?php echo esc_attr( $s['logo_id'] ); ?>">
										<button type="button" class="button" data-tc-media-select="logo"><?php esc_html_e( 'Select image', 'testimonial-collector' ); ?></button>
										<button type="button" class="button" data-tc-media-remove="logo" <?php echo $s['logo_id'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'testimonial-collector' ); ?></button>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Colors', 'testimonial-collector' ); ?></th>
									<td>
										<p><label><?php esc_html_e( 'Primary (cards, buttons)', 'testimonial-collector' ); ?><br><input type="text" class="tc-color" data-tc-color="primary" name="<?php echo esc_attr( $opt ); ?>[color_primary]" value="<?php echo esc_attr( $s['color_primary'] ); ?>"></label></p>
										<p><label><?php esc_html_e( 'Accent (stars)', 'testimonial-collector' ); ?><br><input type="text" class="tc-color" data-tc-color="accent" name="<?php echo esc_attr( $opt ); ?>[color_accent]" value="<?php echo esc_attr( $s['color_accent'] ); ?>"></label></p>
										<p><label><?php esc_html_e( 'Background (wall)', 'testimonial-collector' ); ?><br><input type="text" class="tc-color" data-tc-color="bg" name="<?php echo esc_attr( $opt ); ?>[color_bg]" value="<?php echo esc_attr( $s['color_bg'] ); ?>"></label></p>
										<p><label><?php esc_html_e( 'Text on primary', 'testimonial-collector' ); ?><br><input type="text" class="tc-color" data-tc-color="on-primary" name="<?php echo esc_attr( $opt ); ?>[color_text_on_primary]" value="<?php echo esc_attr( $s['color_text_on_primary'] ); ?>"></label></p>
									</td>
								</tr>
							</table>
						</div>

						<div id="tc-tab-texts" class="tc-tab-panel" hidden>
							<p class="description"><?php esc_html_e( 'Leave a field empty to use the built-in default for that language.', 'testimonial-collector' ); ?></p>
							<table class="form-table" role="presentation">
								<?php
								self::text_pair( $s, 'wall_title', __( 'Wall title', 'testimonial-collector' ), 1 );
								self::text_pair( $s, 'wall_subtitle', __( 'Wall subtitle', 'testimonial-collector' ) );
								self::text_pair( $s, 'form_title', __( 'Form header title', 'testimonial-collector' ), 1 );
								self::text_pair( $s, 'form_intro', __( 'Form custom message', 'testimonial-collector' ) );
								self::text_pair( $s, 'questions_title', __( 'Questions heading', 'testimonial-collector' ), 1 );
								self::text_pair( $s, 'questions', __( 'Guiding questions (one per line)', 'testimonial-collector' ), 4 );
								self::text_pair( $s, 'tab_video', __( 'Video button text', 'testimonial-collector' ), 1 );
								self::text_pair( $s, 'tab_text', __( 'Text button text', 'testimonial-collector' ), 1 );
								self::text_pair( $s, 'btn_submit', __( 'Submit button', 'testimonial-collector' ), 1 );
								self::text_pair( $s, 'label_consent', __( 'Consent statement', 'testimonial-collector' ) );
								?>
							</table>
						</div>

						<div id="tc-tab-thankyou" class="tc-tab-panel" hidden>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Image', 'testimonial-collector' ); ?></th>
									<td>
										<?php self::checkbox( $s, 'thankyou_show_image', __( 'Show the image', 'testimonial-collector' ) ); ?>
										<div class="tc-media-preview" data-tc-media-preview="thankyou" <?php echo $ty_url ? '' : 'hidden'; ?>>
											<img src="<?php echo esc_url( $ty_url ); ?>" alt="">
										</div>
										<input type="hidden" data-tc-media="thankyou" name="<?php echo esc_attr( $opt ); ?>[thankyou_image_id]" value="<?php echo esc_attr( $s['thankyou_image_id'] ); ?>">
										<button type="button" class="button" data-tc-media-select="thankyou"><?php esc_html_e( 'Select image', 'testimonial-collector' ); ?></button>
										<button type="button" class="button" data-tc-media-remove="thankyou" <?php echo $s['thankyou_image_id'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'testimonial-collector' ); ?></button>
									</td>
								</tr>
								<?php
								self::text_pair( $s, 'thankyou_title', __( 'Thank you title', 'testimonial-collector' ), 1 );
								self::text_pair( $s, 'msg_thanks', __( 'Thank you message', 'testimonial-collector' ) );
								?>
							</table>
						</div>

						<div id="tc-tab-extra" class="tc-tab-panel" hidden>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="tc_max_chars"><?php esc_html_e( 'Max characters for the text testimonial', 'testimonial-collector' ); ?></label></th>
									<td>
										<input type="number" id="tc_max_chars" min="0" max="10000" name="<?php echo esc_attr( $opt ); ?>[max_chars]" value="<?php echo esc_attr( $s['max_chars'] ); ?>" class="small-text">
										<p class="description"><?php esc_html_e( 'Setting it to 0 removes the limit.', 'testimonial-collector' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="tc_consent_mode"><?php esc_html_e( 'Consent display', 'testimonial-collector' ); ?></label></th>
									<td>
										<select id="tc_consent_mode" data-tc-field="consent_mode" name="<?php echo esc_attr( $opt ); ?>[consent_mode]">
											<option value="required" <?php selected( $s['consent_mode'], 'required' ); ?>><?php esc_html_e( 'Required', 'testimonial-collector' ); ?></option>
											<option value="optional" <?php selected( $s['consent_mode'], 'optional' ); ?>><?php esc_html_e( 'Optional', 'testimonial-collector' ); ?></option>
											<option value="hidden" <?php selected( $s['consent_mode'], 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'testimonial-collector' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Email verification', 'testimonial-collector' ); ?></th>
									<td>
										<?php self::checkbox( $s, 'verify_email', __( 'Verify submitter email address (confirmation link before the testimonial reaches the approval queue)', 'testimonial-collector' ) ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Wall under the form', 'testimonial-collector' ); ?></th>
									<td><?php self::checkbox( $s, 'form_show_wall', __( 'Show approved testimonials below the submission form', 'testimonial-collector' ) ); ?></td>
								</tr>
								<tr>
									<th scope="row"><label for="tc_video_max_seconds"><?php esc_html_e( 'Max video duration (seconds)', 'testimonial-collector' ); ?></label></th>
									<td><input type="number" id="tc_video_max_seconds" min="10" max="600" name="<?php echo esc_attr( $opt ); ?>[video_max_seconds]" value="<?php echo esc_attr( $s['video_max_seconds'] ); ?>" class="small-text"></td>
								</tr>
								<tr>
									<th scope="row"><label for="tc_video_max_mb"><?php esc_html_e( 'Max video file size (MB)', 'testimonial-collector' ); ?></label></th>
									<td><input type="number" id="tc_video_max_mb" min="5" max="1024" name="<?php echo esc_attr( $opt ); ?>[video_max_mb]" value="<?php echo esc_attr( $s['video_max_mb'] ); ?>" class="small-text"></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'iPhone / iPad recording', 'testimonial-collector' ); ?></th>
									<td><?php self::checkbox( $s, 'ios_no_record', __( 'Disable in-browser video recording on iOS (offer a file upload instead)', 'testimonial-collector' ) ); ?></td>
								</tr>
								<tr>
									<th scope="row"><label for="tc_per_page"><?php esc_html_e( 'Testimonials per page (wall)', 'testimonial-collector' ); ?></label></th>
									<td><input type="number" id="tc_per_page" min="1" max="24" name="<?php echo esc_attr( $opt ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ); ?>" class="small-text"></td>
								</tr>
							</table>
						</div>

						<div id="tc-tab-notify" class="tc-tab-panel" hidden>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="tc_notify_email"><?php esc_html_e( 'Notification email for new submissions', 'testimonial-collector' ); ?></label></th>
									<td><input type="email" id="tc_notify_email" name="<?php echo esc_attr( $opt ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" class="regular-text"></td>
								</tr>
							</table>
						</div>

						<?php submit_button(); ?>
					</form>
				</div>

				<div class="tc-settings-preview">
					<div class="tc-preview-header">
						<span class="tc-preview-dot"></span> <?php esc_html_e( 'LIVE PREVIEW', 'testimonial-collector' ); ?>
						<span class="tc-preview-switch">
							<button type="button" class="button button-small tc-preview-mode-btn active" data-mode="form"><?php esc_html_e( 'Form', 'testimonial-collector' ); ?></button>
							<button type="button" class="button button-small tc-preview-mode-btn" data-mode="thanks"><?php esc_html_e( 'Thank you', 'testimonial-collector' ); ?></button>
						</span>
					</div>

					<div class="tc-preview-stage">
						<div class="tc-container tc-form-container" id="tc-preview-form">
							<div class="tc-logo-wrap" data-pv="logo-wrap" <?php echo $logo_url ? '' : 'hidden'; ?>><img class="tc-logo" data-pv="logo" src="<?php echo esc_url( $logo_url ); ?>" alt=""></div>
							<h2 class="tc-title" data-pv="form_title"></h2>
							<p class="tc-subtitle" data-pv="form_intro"></p>
							<div class="tc-questions">
								<h3 class="tc-questions-title" data-pv="questions_title"></h3>
								<ul data-pv="questions"></ul>
							</div>
							<div class="tc-preview-cta">
								<button type="button" class="tc-btn tc-btn-submit" data-pv="tab_video">🎥</button>
								<button type="button" class="tc-btn tc-btn-dark" data-pv="tab_text">✏️</button>
							</div>
							<div class="tc-rating-input tc-preview-stars" data-pv="stars">
								<span class="tc-rating-star tc-star-on">★</span><span class="tc-rating-star tc-star-on">★</span><span class="tc-rating-star tc-star-on">★</span><span class="tc-rating-star tc-star-on">★</span><span class="tc-rating-star tc-star-on">★</span>
							</div>
							<p class="tc-consent" data-pv="label_consent"></p>
						</div>

						<div class="tc-container tc-form-container" id="tc-preview-thanks" hidden>
							<img class="tc-thanks-img" data-pv="thankyou-img" src="<?php echo esc_url( $ty_url ); ?>" alt="" <?php echo $ty_url ? '' : 'hidden'; ?>>
							<h2 class="tc-title" data-pv="thankyou_title"></h2>
							<p class="tc-subtitle" data-pv="msg_thanks"></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
