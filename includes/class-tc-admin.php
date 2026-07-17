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
		add_action( 'admin_menu', array( __CLASS__, 'pending_badge' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && TC_CPT === $screen->post_type ) {
			wp_enqueue_style( 'tc-admin', TC_PLUGIN_URL . 'assets/css/tc-admin.css', array(), TC_VERSION );
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
		$data = TC_CPT::get_data( $post->ID );
		?>
		<p><strong><?php esc_html_e( 'Name:', 'testimonial-collector' ); ?></strong> <?php echo esc_html( $data['name'] ); ?></p>
		<p><strong><?php esc_html_e( 'Email:', 'testimonial-collector' ); ?></strong> <?php echo esc_html( $data['email'] ); ?></p>
		<?php if ( $data['role'] ) : ?>
			<p><strong><?php esc_html_e( 'Company / role:', 'testimonial-collector' ); ?></strong> <?php echo esc_html( $data['role'] ); ?></p>
		<?php endif; ?>
		<?php if ( $data['social'] ) : ?>
			<p><strong><?php esc_html_e( 'Link:', 'testimonial-collector' ); ?></strong> <a href="<?php echo esc_url( $data['social'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $data['social'] ); ?></a></p>
		<?php endif; ?>
		<p><strong><?php esc_html_e( 'Rating:', 'testimonial-collector' ); ?></strong> <?php echo esc_html( str_repeat( '★', max( 0, min( 5, $data['rating'] ) ) ) ); ?></p>
		<p><strong><?php esc_html_e( 'Consent given:', 'testimonial-collector' ); ?></strong> <?php echo $data['consent'] ? esc_html__( 'Yes', 'testimonial-collector' ) : esc_html__( 'No', 'testimonial-collector' ); ?></p>
		<?php if ( $data['avatar_id'] ) : ?>
			<p><?php echo wp_get_attachment_image( $data['avatar_id'], 'thumbnail', false, array( 'style' => 'border-radius:50%;max-width:80px;height:auto;' ) ); ?></p>
		<?php endif; ?>
		<?php if ( 'video' === $data['type'] && $data['video_id'] ) : ?>
			<video src="<?php echo esc_url( wp_get_attachment_url( $data['video_id'] ) ); ?>" controls preload="metadata" style="width:100%;border-radius:6px;"></video>
		<?php endif; ?>
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
