<?php
/**
 * Admin: approval workflow, list columns, preview metabox, pending badge.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Admin {

	public static function init() {
		add_filter( 'manage_' . TC_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . TC_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'admin_post_tc_moderate', array( __CLASS__, 'handle_moderate' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'save_post_' . TC_CPT, array( __CLASS__, 'save_metabox' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'pending_badge' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && TC_CPT === $screen->post_type ) {
			wp_enqueue_style( 'tc-admin', TC_PLUGIN_URL . 'assets/css/tc-admin.css', array(), TC_VERSION );

			// Editable details metabox needs the media picker on the edit/new screen.
			if ( 'post' === $screen->base ) {
				wp_enqueue_media();
				wp_enqueue_script( 'tc-admin-edit', TC_PLUGIN_URL . 'assets/js/tc-admin-edit.js', array( 'jquery' ), TC_VERSION, true );
			}
		}
	}

	/**
	 * Pending count badge on the admin menu.
	 */
	public static function pending_badge() {
		global $menu;
		$count = wp_count_posts( TC_CPT );
		$pending = isset( $count->pending ) ? (int) $count->pending : 0;
		if ( $pending < 1 || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . TC_CPT === $item[2] ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$menu[ $key ][0] .= ' <span class="awaiting-mod count-' . $pending . '"><span class="pending-count">' . $pending . '</span></span>';
				break;
			}
		}
	}

	public static function columns( $columns ) {
		return array(
			'cb'         => $columns['cb'],
			'title'      => __( 'Name', 'testimonial-collector' ),
			'tc_type'    => __( 'Type', 'testimonial-collector' ),
			'tc_rating'  => __( 'Rating', 'testimonial-collector' ),
			'tc_excerpt' => __( 'Testimonial', 'testimonial-collector' ),
			'tc_contact' => __( 'Contact', 'testimonial-collector' ),
			'tc_status'  => __( 'Status', 'testimonial-collector' ),
			'date'       => __( 'Date', 'testimonial-collector' ),
		);
	}

	public static function column_content( $column, $post_id ) {
		$data = TC_CPT::get_data( $post_id );

		switch ( $column ) {
			case 'tc_type':
				if ( 'video' === $data['type'] ) {
					echo '<span class="dashicons dashicons-video-alt3" title="' . esc_attr__( 'Video', 'testimonial-collector' ) . '"></span> ' . esc_html__( 'Video', 'testimonial-collector' );
				} else {
					echo '<span class="dashicons dashicons-text" title="' . esc_attr__( 'Text', 'testimonial-collector' ) . '"></span> ' . esc_html__( 'Text', 'testimonial-collector' );
				}
				break;

			case 'tc_rating':
				$rating = max( 0, min( 5, $data['rating'] ) );
				echo '<span class="tc-admin-stars">' . esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ) . '</span>';
				break;

			case 'tc_excerpt':
				if ( 'video' === $data['type'] && $data['video_id'] ) {
					$url = wp_get_attachment_url( $data['video_id'] );
					if ( $url ) {
						echo '<video src="' . esc_url( $url ) . '" controls preload="metadata" class="tc-admin-video"></video>';
					}
				} else {
					echo esc_html( wp_trim_words( get_post_field( 'post_content', $post_id ), 25 ) );
				}
				break;

			case 'tc_contact':
				echo esc_html( $data['email'] );
				if ( $data['role'] ) {
					echo '<br><em>' . esc_html( $data['role'] ) . '</em>';
				}
				if ( $data['event'] ) {
					echo '<br>' . esc_html( $data['event'] );
				}
				break;

			case 'tc_status':
				$status = get_post_status( $post_id );
				if ( 'publish' === $status ) {
					echo '<strong class="tc-status tc-status-approved">' . esc_html__( 'Approved', 'testimonial-collector' ) . '</strong>';
					echo '<br>' . self::moderate_link( $post_id, 'unapprove', __( 'Unapprove', 'testimonial-collector' ) );
				} elseif ( 'pending' === $status ) {
					echo '<strong class="tc-status tc-status-pending">' . esc_html__( 'Pending', 'testimonial-collector' ) . '</strong><br>';
					echo self::moderate_link( $post_id, 'approve', __( 'Approve', 'testimonial-collector' ), 'button button-primary button-small' );
					echo ' ' . self::moderate_link( $post_id, 'reject', __( 'Reject', 'testimonial-collector' ), 'button button-small' );
				} else {
					echo esc_html( $status );
				}
				break;
		}
	}

	protected static function moderate_link( $post_id, $op, $label, $class = '' ) {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=tc_moderate&op=' . $op . '&post_id=' . $post_id ),
			'tc_moderate_' . $post_id
		);
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Approve / reject / unapprove handler.
	 */
	public static function handle_moderate() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$op      = isset( $_GET['op'] ) ? sanitize_key( $_GET['op'] ) : '';

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'testimonial-collector' ) );
		}
		check_admin_referer( 'tc_moderate_' . $post_id );

		if ( get_post_type( $post_id ) !== TC_CPT ) {
			wp_die( esc_html__( 'Invalid item.', 'testimonial-collector' ) );
		}

		if ( 'approve' === $op ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		} elseif ( 'unapprove' === $op ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
		} elseif ( 'reject' === $op ) {
			wp_trash_post( $post_id );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . TC_CPT ) );
		exit;
	}

	/**
	 * Preview metabox on the edit screen.
	 */
	public static function metabox() {
		add_meta_box(
			'tc_details',
			__( 'Testimonial details', 'testimonial-collector' ),
			array( __CLASS__, 'render_metabox' ),
			TC_CPT,
			'side',
			'high'
		);
	}

	public static function render_metabox( $post ) {
		$data       = TC_CPT::get_data( $post->ID );
		$settings   = tc_get_settings();
		$events     = array_filter( array_map( 'trim', explode( "\n", (string) $settings['events'] ) ) );
		$avatar_url = $data['avatar_id'] ? wp_get_attachment_image_url( $data['avatar_id'], 'thumbnail' ) : '';
		$video_url  = $data['video_id'] ? wp_get_attachment_url( $data['video_id'] ) : '';
		wp_nonce_field( 'tc_save_details', 'tc_details_nonce' );
		?>
		<p class="tc-mb-field">
			<label for="tc_mb_name"><strong><?php esc_html_e( 'Name', 'testimonial-collector' ); ?></strong></label>
			<input type="text" id="tc_mb_name" name="tc_name" value="<?php echo esc_attr( $data['name'] ); ?>" class="widefat">
		</p>
		<p class="tc-mb-field">
			<label for="tc_mb_email"><strong><?php esc_html_e( 'Email', 'testimonial-collector' ); ?></strong></label>
			<input type="email" id="tc_mb_email" name="tc_email" value="<?php echo esc_attr( $data['email'] ); ?>" class="widefat">
		</p>
		<p class="tc-mb-field">
			<label for="tc_mb_role"><strong><?php esc_html_e( 'Company / role', 'testimonial-collector' ); ?></strong></label>
			<input type="text" id="tc_mb_role" name="tc_role" value="<?php echo esc_attr( $data['role'] ); ?>" class="widefat">
		</p>
		<p class="tc-mb-field">
			<label for="tc_mb_social"><strong><?php esc_html_e( 'Link', 'testimonial-collector' ); ?></strong></label>
			<input type="url" id="tc_mb_social" name="tc_social" value="<?php echo esc_attr( $data['social'] ); ?>" class="widefat" placeholder="https://">
		</p>
		<p class="tc-mb-field">
			<label for="tc_mb_headline"><strong><?php esc_html_e( 'Headline', 'testimonial-collector' ); ?></strong></label>
			<input type="text" id="tc_mb_headline" name="tc_headline" value="<?php echo esc_attr( $data['headline'] ); ?>" class="widefat">
		</p>
		<p class="tc-mb-field">
			<label for="tc_mb_event"><strong><?php esc_html_e( 'Event / program', 'testimonial-collector' ); ?></strong></label>
			<?php if ( ! empty( $events ) ) : ?>
				<select id="tc_mb_event" name="tc_event" class="widefat">
					<option value=""><?php esc_html_e( '— None —', 'testimonial-collector' ); ?></option>
					<?php
					$known = false;
					foreach ( $events as $ev ) {
						$sel = selected( $data['event'], $ev, false );
						if ( '' !== $sel ) {
							$known = true;
						}
						echo '<option value="' . esc_attr( $ev ) . '" ' . $sel . '>' . esc_html( $ev ) . '</option>';
					}
					// Keep an existing value that is no longer in the configured list.
					if ( ! $known && '' !== $data['event'] ) {
						echo '<option value="' . esc_attr( $data['event'] ) . '" selected>' . esc_html( $data['event'] ) . '</option>';
					}
					?>
				</select>
			<?php else : ?>
				<input type="text" id="tc_mb_event" name="tc_event" value="<?php echo esc_attr( $data['event'] ); ?>" class="widefat">
			<?php endif; ?>
		</p>
		<p class="tc-mb-field">
			<label for="tc_mb_rating"><strong><?php esc_html_e( 'Rating', 'testimonial-collector' ); ?></strong></label>
			<select id="tc_mb_rating" name="tc_rating" class="widefat">
				<?php
				$rating = max( 1, min( 5, $data['rating'] ? $data['rating'] : 5 ) );
				for ( $i = 5; $i >= 1; $i-- ) {
					echo '<option value="' . esc_attr( $i ) . '" ' . selected( $rating, $i, false ) . '>' . esc_html( str_repeat( '★', $i ) . str_repeat( '☆', 5 - $i ) ) . '</option>';
				}
				?>
			</select>
		</p>
		<p class="tc-mb-field">
			<label for="tc_mb_type"><strong><?php esc_html_e( 'Type', 'testimonial-collector' ); ?></strong></label>
			<select id="tc_mb_type" name="tc_type" class="widefat">
				<option value="text" <?php selected( $data['type'] ? $data['type'] : 'text', 'text' ); ?>><?php esc_html_e( 'Text', 'testimonial-collector' ); ?></option>
				<option value="video" <?php selected( $data['type'], 'video' ); ?>><?php esc_html_e( 'Video', 'testimonial-collector' ); ?></option>
			</select>
			<span class="description"><?php esc_html_e( 'The text testimonial body is edited in the main content editor.', 'testimonial-collector' ); ?></span>
		</p>
		<p class="tc-mb-field">
			<label class="tc-mb-consent"><input type="checkbox" name="tc_consent" value="1" <?php checked( $data['consent'] ); ?>> <strong><?php esc_html_e( 'Consent given', 'testimonial-collector' ); ?></strong></label>
		</p>

		<p class="tc-mb-field">
			<strong><?php esc_html_e( 'Photo / avatar', 'testimonial-collector' ); ?></strong><br>
			<span data-tc-media-preview="avatar" <?php echo $avatar_url ? '' : 'hidden'; ?>>
				<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" style="border-radius:50%;max-width:64px;height:auto;">
			</span>
			<input type="hidden" name="tc_avatar_id" data-tc-media="avatar" value="<?php echo esc_attr( $data['avatar_id'] ); ?>">
			<button type="button" class="button button-small" data-tc-media-select="avatar"><?php esc_html_e( 'Choose image', 'testimonial-collector' ); ?></button>
			<button type="button" class="button button-small" data-tc-media-remove="avatar" <?php echo $avatar_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'testimonial-collector' ); ?></button>
		</p>

		<p class="tc-mb-field tc-mb-video">
			<strong><?php esc_html_e( 'Video', 'testimonial-collector' ); ?></strong><br>
			<span data-tc-media-preview="tcvideo" <?php echo $video_url ? '' : 'hidden'; ?>>
				<video src="<?php echo esc_url( $video_url ); ?>" controls preload="metadata" style="width:100%;border-radius:6px;"></video>
			</span>
			<input type="hidden" name="tc_video_id" data-tc-media="tcvideo" value="<?php echo esc_attr( $data['video_id'] ); ?>">
			<button type="button" class="button button-small" data-tc-media-select="tcvideo"><?php esc_html_e( 'Choose video', 'testimonial-collector' ); ?></button>
			<button type="button" class="button button-small" data-tc-media-remove="tcvideo" <?php echo $video_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'testimonial-collector' ); ?></button>
		</p>

		<hr>
		<?php
		$status = get_post_status( $post->ID );
		if ( 'pending' === $status ) {
			echo self::moderate_link( $post->ID, 'approve', __( 'Approve', 'testimonial-collector' ), 'button button-primary' );
			echo ' ' . self::moderate_link( $post->ID, 'reject', __( 'Reject', 'testimonial-collector' ), 'button' );
		} elseif ( 'publish' === $status ) {
			echo self::moderate_link( $post->ID, 'unapprove', __( 'Unapprove', 'testimonial-collector' ), 'button' );
		}
	}

	/**
	 * Save the editable details metabox.
	 */
	public static function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['tc_details_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tc_details_nonce'] ) ), 'tc_save_details' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( TC_CPT !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$settings = tc_get_settings();

		// Text fields: empty removes the meta to keep things clean.
		$text_map = array(
			'_tc_role'     => 'tc_role',
			'_tc_social'   => 'tc_social',
			'_tc_headline' => 'tc_headline',
		);
		foreach ( $text_map as $meta_key => $field ) {
			$val = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( '_tc_social' === $meta_key ) {
				$val = isset( $_POST[ $field ] ) ? esc_url_raw( wp_unslash( $_POST[ $field ] ) ) : '';
			}
			if ( '' !== $val ) {
				update_post_meta( $post_id, $meta_key, $val );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}

		// Name: keep meta and the post title in sync.
		$name = isset( $_POST['tc_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tc_name'] ) ) : '';
		if ( '' === $name ) {
			$name = $post->post_title;
		}
		update_post_meta( $post_id, '_tc_name', $name );
		if ( $name !== $post->post_title ) {
			remove_action( 'save_post_' . TC_CPT, array( __CLASS__, 'save_metabox' ), 10 );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $name ) );
			add_action( 'save_post_' . TC_CPT, array( __CLASS__, 'save_metabox' ), 10, 2 );
		}

		// Email.
		$email = isset( $_POST['tc_email'] ) ? sanitize_email( wp_unslash( $_POST['tc_email'] ) ) : '';
		update_post_meta( $post_id, '_tc_email', $email );

		// Rating 1-5.
		$rating = isset( $_POST['tc_rating'] ) ? max( 1, min( 5, absint( $_POST['tc_rating'] ) ) ) : 5;
		update_post_meta( $post_id, '_tc_rating', $rating );

		// Type.
		$type = ( isset( $_POST['tc_type'] ) && 'video' === $_POST['tc_type'] ) ? 'video' : 'text';
		update_post_meta( $post_id, '_tc_type', $type );

		// Consent.
		update_post_meta( $post_id, '_tc_consent', empty( $_POST['tc_consent'] ) ? 0 : 1 );

		// Event: only accept a value from the configured list; free text if no list.
		$event  = isset( $_POST['tc_event'] ) ? sanitize_text_field( wp_unslash( $_POST['tc_event'] ) ) : '';
		$events = array_filter( array_map( 'trim', explode( "\n", (string) $settings['events'] ) ) );
		if ( '' === $event || empty( $events ) || in_array( $event, $events, true ) ) {
			if ( '' !== $event ) {
				update_post_meta( $post_id, '_tc_event', $event );
			} else {
				delete_post_meta( $post_id, '_tc_event' );
			}
		}

		// Attachments: id or 0 (clear). Also parent the attachment to this post.
		foreach ( array( '_tc_avatar_id' => 'tc_avatar_id', '_tc_video_id' => 'tc_video_id' ) as $meta_key => $field ) {
			$att_id = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
			if ( $att_id > 0 ) {
				update_post_meta( $post_id, $meta_key, $att_id );
				if ( (int) wp_get_post_parent_id( $att_id ) === 0 ) {
					wp_update_post( array( 'ID' => $att_id, 'post_parent' => $post_id ) );
				}
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}

	/**
	 * Email notification about a new submission.
	 */
	public static function notify_new_submission( $post_id ) {
		$settings = tc_get_settings();
		$data     = TC_CPT::get_data( $post_id );
		$subject  = sprintf(
			/* translators: %s: submitter name */
			__( 'New testimonial awaiting approval: %s', 'testimonial-collector' ),
			$data['name']
		);
		$body  = __( 'A new testimonial was submitted.', 'testimonial-collector' ) . "\n\n";
		$body .= __( 'Name:', 'testimonial-collector' ) . ' ' . $data['name'] . "\n";
		$body .= __( 'Email:', 'testimonial-collector' ) . ' ' . $data['email'] . "\n";
		$body .= __( 'Type:', 'testimonial-collector' ) . ' ' . $data['type'] . "\n";
		$body .= __( 'Rating:', 'testimonial-collector' ) . ' ' . $data['rating'] . "/5\n\n";
		if ( 'text' === $data['type'] ) {
			$body .= get_post_field( 'post_content', $post_id ) . "\n\n";
		}
		$body .= __( 'Review it here:', 'testimonial-collector' ) . ' ' . admin_url( 'edit.php?post_status=pending&post_type=' . TC_CPT );

		wp_mail( $settings['notify_email'], $subject, $body );
	}
}
