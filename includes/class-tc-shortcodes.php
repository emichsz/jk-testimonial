<?php
/**
 * Shortcodes: [testimonial_form] and [testimonial_wall].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Shortcodes {

	public static function init() {
		add_shortcode( 'testimonial_form', array( __CLASS__, 'render_form' ) );
		add_shortcode( 'testimonial_wall', array( __CLASS__, 'render_wall' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'tc-frontend', TC_PLUGIN_URL . 'assets/css/tc-frontend.css', array(), TC_VERSION );
		wp_register_script( 'tc-form', TC_PLUGIN_URL . 'assets/js/tc-form.js', array(), TC_VERSION, true );
		wp_register_script( 'tc-wall', TC_PLUGIN_URL . 'assets/js/tc-wall.js', array(), TC_VERSION, true );
	}

	protected static function enqueue_common() {
		wp_enqueue_style( 'tc-frontend' );

		$settings = tc_get_settings();
		$css      = sprintf(
			':root{--tc-primary:%1$s;--tc-accent:%2$s;--tc-bg:%3$s;--tc-on-primary:%4$s;}',
			esc_html( $settings['color_primary'] ),
			esc_html( $settings['color_accent'] ),
			esc_html( $settings['color_bg'] ),
			esc_html( $settings['color_text_on_primary'] )
		);
		wp_add_inline_style( 'tc-frontend', $css );
	}

	protected static function stars_html( $rating ) {
		$rating = max( 0, min( 5, (int) $rating ) );
		$html   = '<span class="tc-stars" aria-label="' . esc_attr( $rating . '/5' ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$html .= '<span class="tc-star' . ( $i <= $rating ? ' tc-star-on' : '' ) . '">★</span>';
		}
		return $html . '</span>';
	}

	protected static function logo_html() {
		$settings = tc_get_settings();
		if ( ! $settings['logo_id'] ) {
			return '';
		}
		$img = wp_get_attachment_image( $settings['logo_id'], 'medium', false, array( 'class' => 'tc-logo', 'alt' => get_bloginfo( 'name' ) ) );
		return $img ? '<div class="tc-logo-wrap">' . $img . '</div>' : '';
	}

	/**
	 * [testimonial_form]
	 */
	public static function render_form( $atts ) {
		self::enqueue_common();
		wp_enqueue_script( 'tc-form' );

		$settings = tc_get_settings();
		$strings  = TC_Strings::get();

		wp_localize_script(
			'tc-form',
			'tcForm',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'tc_submit' ),
				'maxSeconds' => (int) $settings['video_max_seconds'],
				'maxMb'      => (int) $settings['video_max_mb'],
				'iosNoRecord' => (int) $settings['ios_no_record'],
				'consentRequired' => ( 'required' === $settings['consent_mode'] ) ? 1 : 0,
				'i18n'       => array(
					'camError'     => $strings['msg_cam_error'],
					'tooLong'      => $strings['msg_too_long'],
					'required'     => $strings['msg_required'],
					'videoMissing' => $strings['msg_video_missing'],
					'error'        => $strings['msg_error'],
					'uploading'    => $strings['msg_uploading'],
					'record'       => $strings['btn_record'],
					'stop'         => $strings['btn_stop'],
					'retake'       => $strings['btn_retake'],
					'secLeft'      => $strings['sec_left'],
				),
			)
		);

		$collection = $settings['collection_type'];
		$questions  = array_filter( array_map( 'trim', explode( "\n", $strings['questions'] ) ) );
		$theme_cls  = ( 'dark' === $settings['theme'] ) ? ' tc-theme-dark' : '';
		$default_type = ( 'video' === $collection ) ? 'video' : 'text';

		ob_start();
		?>
		<div class="tc-container tc-form-container<?php echo esc_attr( $theme_cls ); ?>">
			<?php echo self::logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h2 class="tc-title"><?php echo esc_html( $strings['form_title'] ); ?></h2>
			<p class="tc-subtitle"><?php echo esc_html( $strings['form_intro'] ); ?></p>

			<?php if ( ! empty( $questions ) ) : ?>
				<div class="tc-questions">
					<h3 class="tc-questions-title"><?php echo esc_html( $strings['questions_title'] ); ?></h3>
					<ul>
						<?php foreach ( $questions as $q ) : ?>
							<li><?php echo esc_html( $q ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form class="tc-form" novalidate>
				<?php if ( 'both' === $collection ) : ?>
					<div class="tc-tabs" role="tablist">
						<button type="button" class="tc-tab tc-tab-active" data-type="text" role="tab" aria-selected="true"><?php echo esc_html( $strings['tab_text'] ); ?></button>
						<button type="button" class="tc-tab" data-type="video" role="tab" aria-selected="false"><?php echo esc_html( $strings['tab_video'] ); ?></button>
					</div>
				<?php endif; ?>

				<input type="hidden" name="type" value="<?php echo esc_attr( $default_type ); ?>">
				<input type="text" name="tc_website" class="tc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

				<div class="tc-panel tc-panel-text" <?php echo ( 'video' === $collection ) ? 'hidden' : ''; ?>>
					<?php if ( $settings['show_title'] ) : ?>
						<label class="tc-label"><?php echo esc_html( $strings['label_headline'] ); ?>
							<input type="text" name="headline" class="tc-input">
						</label>
					<?php endif; ?>
					<label class="tc-label"><?php echo esc_html( $strings['label_text'] ); ?> *
						<textarea name="content" rows="5" class="tc-input" <?php echo $settings['max_chars'] > 0 ? 'maxlength="' . esc_attr( $settings['max_chars'] ) . '"' : ''; ?>></textarea>
					</label>
					<?php if ( $settings['max_chars'] > 0 ) : ?>
						<div class="tc-charcount"><span class="tc-chars-used">0</span> / <?php echo esc_html( $settings['max_chars'] ); ?></div>
					<?php endif; ?>
				</div>

				<div class="tc-panel tc-panel-video" <?php echo ( 'video' !== $collection ) ? 'hidden' : ''; ?>>
					<div class="tc-recorder">
						<video class="tc-preview" playsinline muted></video>
						<video class="tc-playback" controls hidden></video>
						<div class="tc-rec-status" aria-live="polite"></div>
						<div class="tc-rec-controls">
							<button type="button" class="tc-btn tc-btn-secondary tc-btn-cam"><?php echo esc_html( $strings['btn_start_cam'] ); ?></button>
							<button type="button" class="tc-btn tc-btn-record" hidden><?php echo esc_html( $strings['btn_record'] ); ?></button>
							<button type="button" class="tc-btn tc-btn-secondary tc-btn-retake" hidden><?php echo esc_html( $strings['btn_retake'] ); ?></button>
						</div>
						<label class="tc-label tc-video-upload" hidden><?php echo esc_html( $strings['label_upload_video'] ); ?>
							<input type="file" name="video_file" accept="video/mp4,video/webm,video/quicktime" class="tc-input tc-input-file">
						</label>
					</div>
				</div>

				<div class="tc-fields">
					<label class="tc-label"><?php echo esc_html( $strings['label_name'] ); ?> *
						<input type="text" name="name" class="tc-input" required>
					</label>
					<label class="tc-label"><?php echo esc_html( $strings['label_email'] ); ?> *
						<input type="email" name="email" class="tc-input" required>
					</label>
					<?php if ( $settings['show_role'] ) : ?>
						<label class="tc-label"><?php echo esc_html( $strings['label_role'] ); ?>
							<input type="text" name="role" class="tc-input">
						</label>
					<?php endif; ?>
					<?php if ( $settings['show_social'] ) : ?>
						<label class="tc-label"><?php echo esc_html( $strings['label_social'] ); ?>
							<input type="url" name="social" class="tc-input" placeholder="https://">
						</label>
					<?php endif; ?>
					<?php if ( $settings['collect_rating'] ) : ?>
						<div class="tc-label">
							<?php echo esc_html( $strings['label_rating'] ); ?>
							<div class="tc-rating-input" data-rating="5">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<button type="button" class="tc-rating-star tc-star-on" data-value="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( $i ); ?>">★</button>
								<?php endfor; ?>
							</div>
							<input type="hidden" name="rating" value="5">
						</div>
					<?php endif; ?>
					<?php if ( $settings['show_photo'] ) : ?>
						<label class="tc-label"><?php echo esc_html( $strings['label_photo'] ); ?>
							<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="tc-input tc-input-file">
						</label>
					<?php endif; ?>
					<?php if ( 'hidden' !== $settings['consent_mode'] ) : ?>
						<label class="tc-consent">
							<input type="checkbox" name="consent" value="1">
							<span><?php echo esc_html( $strings['label_consent'] ); ?><?php echo ( 'required' === $settings['consent_mode'] ) ? ' *' : ''; ?></span>
						</label>
					<?php endif; ?>
				</div>

				<div class="tc-message" role="status" aria-live="polite" hidden></div>

				<button type="submit" class="tc-btn tc-btn-submit"><?php echo esc_html( $strings['btn_submit'] ); ?></button>
			</form>

			<div class="tc-thanks" hidden>
				<?php
				if ( $settings['thankyou_show_image'] && $settings['thankyou_image_id'] ) {
					echo wp_get_attachment_image( $settings['thankyou_image_id'], 'large', false, array( 'class' => 'tc-thanks-img', 'alt' => '' ) );
				}
				?>
				<h2 class="tc-title"><?php echo esc_html( $strings['thankyou_title'] ); ?></h2>
				<p class="tc-subtitle tc-thanks-msg"><?php echo esc_html( $strings['msg_thanks'] ); ?></p>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		if ( $settings['form_show_wall'] ) {
			$html .= self::render_wall( array() );
		}
		return $html;
	}

	/**
	 * [testimonial_wall per_page="6"]
	 */
	public static function render_wall( $atts ) {
		self::enqueue_common();
		wp_enqueue_script( 'tc-wall' );

		$settings = tc_get_settings();
		$strings  = TC_Strings::get();
		$atts     = shortcode_atts( array( 'per_page' => $settings['per_page'] ), $atts, 'testimonial_wall' );
		$per_page = max( 1, min( 24, (int) $atts['per_page'] ) );

		$theme_cls = ( 'dark' === $settings['theme'] ) ? ' tc-theme-dark' : '';

		$query = new WP_Query(
			array(
				'post_type'      => TC_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		ob_start();
		?>
		<div class="tc-container tc-wall<?php echo esc_attr( $theme_cls ); ?>">
			<?php echo self::logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h2 class="tc-title"><?php echo esc_html( $strings['wall_title'] ); ?></h2>
			<p class="tc-subtitle"><?php echo esc_html( $strings['wall_subtitle'] ); ?></p>

			<?php if ( ! $query->have_posts() ) : ?>
				<p class="tc-empty"><?php echo esc_html( $strings['no_items'] ); ?></p>
			<?php else : ?>
				<div class="tc-carousel" data-per-page="<?php echo esc_attr( $per_page ); ?>">
					<button type="button" class="tc-arrow tc-arrow-prev" aria-label="<?php echo esc_attr( $strings['prev'] ); ?>">&#10094;</button>
					<div class="tc-pages">
						<div class="tc-grid">
							<?php
							while ( $query->have_posts() ) {
								$query->the_post();
								self::render_card( get_the_ID(), $strings );
							}
							wp_reset_postdata();
							?>
						</div>
					</div>
					<button type="button" class="tc-arrow tc-arrow-next" aria-label="<?php echo esc_attr( $strings['next'] ); ?>">&#10095;</button>
					<div class="tc-dots" role="tablist"></div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function render_card( $post_id, $strings ) {
		$data     = TC_CPT::get_data( $post_id );
		$settings = tc_get_settings();
		$show_stars = ! empty( $settings['collect_rating'] );

		if ( 'video' === $data['type'] && $data['video_id'] ) {
			$video_url = wp_get_attachment_url( $data['video_id'] );
			?>
			<div class="tc-card tc-card-video">
				<span class="tc-badge">👍 <?php echo esc_html( $strings['badge'] ); ?></span>
				<video src="<?php echo esc_url( $video_url ); ?>" preload="metadata" controls playsinline></video>
				<div class="tc-video-meta">
					<span class="tc-video-name"><?php echo esc_html( $data['name'] ); ?></span>
					<?php if ( $show_stars ) { echo self::stars_html( $data['rating'] ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ } ?>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="tc-card tc-card-text">
				<div class="tc-card-head">
					<?php if ( $data['avatar_id'] ) : ?>
						<?php echo wp_get_attachment_image( $data['avatar_id'], 'thumbnail', false, array( 'class' => 'tc-avatar', 'alt' => $data['name'] ) ); ?>
					<?php else : ?>
						<span class="tc-avatar tc-avatar-fallback"><?php echo esc_html( mb_substr( $data['name'], 0, 1 ) ); ?></span>
					<?php endif; ?>
					<?php if ( $data['social'] ) : ?>
						<a class="tc-name" href="<?php echo esc_url( $data['social'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $data['name'] ); ?></a>
					<?php else : ?>
						<span class="tc-name"><?php echo esc_html( $data['name'] ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $data['role'] ) : ?>
					<div class="tc-role"><?php echo esc_html( $data['role'] ); ?></div>
				<?php endif; ?>
				<?php if ( $show_stars ) { echo self::stars_html( $data['rating'] ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ } ?>
				<?php if ( $data['headline'] ) : ?>
					<div class="tc-headline"><?php echo esc_html( $data['headline'] ); ?></div>
				<?php endif; ?>
				<div class="tc-text"><?php echo wp_kses_post( wpautop( get_post_field( 'post_content', $post_id ) ) ); ?></div>
			</div>
			<?php
		}
	}
}
