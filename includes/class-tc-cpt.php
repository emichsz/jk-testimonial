<?php
/**
 * Custom post type + meta for testimonials.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			TC_CPT,
			array(
				'labels'              => array(
					'name'               => __( 'Testimonials', 'testimonial-collector' ),
					'singular_name'      => __( 'Testimonial', 'testimonial-collector' ),
					'menu_name'          => __( 'Testimonials', 'testimonial-collector' ),
					'all_items'          => __( 'All Testimonials', 'testimonial-collector' ),
					'edit_item'          => __( 'Edit Testimonial', 'testimonial-collector' ),
					'view_item'          => __( 'View Testimonial', 'testimonial-collector' ),
					'search_items'       => __( 'Search Testimonials', 'testimonial-collector' ),
					'not_found'          => __( 'No testimonials found', 'testimonial-collector' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-format-quote',
				'menu_position'       => 26,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'show_in_rest'        => false,
			)
		);
	}

	/**
	 * Meta keys used on a testimonial.
	 */
	public static function meta_keys() {
		return array(
			'_tc_name',      // submitter name
			'_tc_email',     // submitter email (private)
			'_tc_role',      // role / company
			'_tc_rating',    // 1-5
			'_tc_type',      // text | video
			'_tc_video_id',  // attachment ID of video
			'_tc_avatar_id', // attachment ID of photo
			'_tc_consent',   // 1 if consent given
			'_tc_social',    // social / website URL
			'_tc_headline',  // optional testimonial title
			'_tc_verify_key' // email verification key (empty once verified)
		);
	}

	/**
	 * Helper: read all display meta of a testimonial.
	 */
	public static function get_data( $post_id ) {
		return array(
			'name'      => get_post_meta( $post_id, '_tc_name', true ),
			'email'     => get_post_meta( $post_id, '_tc_email', true ),
			'role'      => get_post_meta( $post_id, '_tc_role', true ),
			'rating'    => (int) get_post_meta( $post_id, '_tc_rating', true ),
			'type'      => get_post_meta( $post_id, '_tc_type', true ),
			'video_id'  => (int) get_post_meta( $post_id, '_tc_video_id', true ),
			'avatar_id' => (int) get_post_meta( $post_id, '_tc_avatar_id', true ),
			'consent'   => (bool) get_post_meta( $post_id, '_tc_consent', true ),
			'social'    => get_post_meta( $post_id, '_tc_social', true ),
			'headline'  => get_post_meta( $post_id, '_tc_headline', true ),
		);
	}
}
