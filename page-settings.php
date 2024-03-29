<?php
/**
 * Puro page settings.
 * A basic settings class used to add settings metaboxes to pages.
 *
 * Class Puro_Extras_Page_Settings 
 *
 * @package puro-extras
 * @license GPL 2.0 
 */

class Puro_Extras_Page_Settings {
	private $meta;

	function __construct() {
		$this->meta = array();

		add_action( 'init', array( $this, 'add_page_settings_support' ) );

		// Meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10 );
		add_action( 'save_post', array( $this, 'save_post' ) );
	}

	/**
	 * Get the singular instance.
	 *
	 * @return Puro_Page_Settings
	 */
	static function single() {
		static $single;
		if ( empty( $single ) ) {
			$single = new self();
		}

		return $single;
	}

	/**
	 * Get a settings value.
	 *
	 * @param $key
	 * @param $default
	 *
	 * @return null
	 */
	static function get( $key = false, $default = null ) {
		$single = self::single();

		static $type = false;
		static $id = false;

		if ( $type === false && $id === false ) {
			list( $type, $id ) = self::get_current_page();
		}

		if ( empty( $single->meta[ $type . '_' . $id ] ) ) {
			$single->meta[ $type . '_' . $id ] = $single->get_settings_values( $type, $id );
		}

		// Return the value.
		if ( empty( $key ) ) {
			return $single->meta[ $type . '_' . $id ];
		}
		else {
			return isset( $single->meta[ $type . '_' . $id ][ $key ] ) ? $single->meta[ $type . '_' . $id ][ $key ] : $default;
		}
	}

	function get_settings( $type, $id ) {
		return apply_filters( 'puro_page_settings', array(), $type, $id );
	}

	function add_page_settings_support() {
		add_post_type_support( 'page', 'puro-page-settings' );
		add_post_type_support( 'post', 'puro-page-settings' );
		if ( post_type_exists( 'jetpack-portfolio' ) ) {
			add_post_type_support( 'jetpack-portfolio', 'puro-page-settings' );
		}
	}

	function get_settings_defaults( $type, $id ) {
		return apply_filters( 'puro_page_settings_defaults', array(), $type, $id );
	}

	static function get_current_page() {
		global $wp_query;

		if ( $wp_query->is_home() ) {
			$type = 'template';
			$id = 'home';
		}
		elseif ( $wp_query->is_search() ) {
			$type = 'template';
			$id = 'search';
		}
		elseif ( $wp_query->is_404() ) {
			$type = 'template';
			$id = '404';
		}
		elseif ( $wp_query->is_date() ) {
			$type = 'template';
			$id = 'date';
		}
		else if ( $wp_query->is_post_type_archive() ) {
			$type = 'archive';
			$id = $wp_query->get( 'post_type' );
		}
		else {
			$object = get_queried_object();
			if ( ! empty( $object ) ) {
				switch ( get_class( $object ) ) {
					case 'WP_Term':
						$type = 'taxonomy';
						$id = $object->taxonomy;
						break;

					case 'WP_Post':
						$type = 'post';
						$id = $object->ID;
						break;

					case 'WP_User':
						$type = 'template';
						$id = 'author';
						break;
				}
			}
			else {
				$type = 'template';
				$id = 'default';
			}
		}

		return array( $type, $id );
	}

	/**
	 * Get the settings post meta and add the default values.
	 *
	 * @param $type
	 * @param $id
	 *
	 * @return array|mixed
	 */
	function get_settings_values( $type, $id ) {
		$defaults = $this->get_settings_defaults( $type, $id );

		switch ( $type ) {
			case 'post':
				$values = get_post_meta( $id, 'puro_page_settings', true );
				break;

			default:
				$values = get_theme_mod( 'page_settings_' . $type . '_' . $id );
				break;
		}

		if ( empty($values) ) $values = array();
		$values = apply_filters( 'puro_page_settings_values', $values, $type, $id );

		return wp_parse_args( $values, $defaults );
	}

	/**
	 * Add the meta box.
	 *
	 * @param $post_type
	 */
	function add_meta_box( $post_type ) {

		if ( ! empty( $post_type ) && post_type_supports( $post_type, 'puro-page-settings' ) ) {
			add_meta_box(
				'puro_page_settings',
				esc_html__( 'Page Settings', 'puro' ),
				array( $this, 'display_post_meta_box' ),
				$post_type,
				'side'
			);
		}
	}

	/**
	 * Display the Meta Box.
	 */
	function display_post_meta_box( $post ) {
		$settings = $this->get_settings( 'post', $post->ID );
		$values = $this->get_settings_values( 'post', $post->ID );

		do_action( 'puro_settings_before_page_settings_meta_box', $post );

		foreach ( $settings as $id => $field ) {
			if ( empty( $values[ $id ] ) ) $values[ $id ] = false;

			?><p><label for="puro-page-settings-<?php echo esc_attr( $id ) ?>"><strong><?php echo esc_html( $field['label'] ) ?></strong></label></p><?php

			switch ( $field['type'] ) {

				case 'select' :
					?>
					<select name="puro_page_settings[<?php echo esc_attr( $id ) ?>]" id="puro-page-settings-<?php echo esc_attr( $id ) ?>">
						<?php foreach ( $field['options'] as $v => $n ) : ?>
							<option value="<?php echo esc_attr( $v ) ?>" <?php selected( $values[ $id ], $v ) ?>><?php echo esc_html( $n ) ?></option>
						<?php endforeach; ?>
					</select>
					<?php

					break;

				case 'checkbox' :
					?>
					<label><input type="checkbox" name="puro_page_settings[<?php echo esc_attr( $id ) ?>]" <?php checked( $values[ $id ] ) ?> /><?php echo esc_html( $field['checkbox_label'] ) ?></label>
					<?php
					break;

				case 'text' :
				default :
					?><input type="text" name="puro_page_settings[<?php echo esc_attr( $id ) ?>]" id="puro-page-settings-<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $values[ $id ] ) ?>" /><?php
					break;

			}

			if ( ! empty( $field['description'] ) ) {
				?><p class="description"><?php echo esc_html( $field['description'] ) ?></p><?php
			}
		}

		wp_nonce_field( 'save_page_settings', '_puro_page_settings_nonce' );

		do_action( 'puro_settings_after_page_settings_meta_box', $post );
	}

	/**
	 * Save settings.
	 *
	 * @param $post_id
	 */
	function save_post( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( empty( $_POST['_puro_page_settings_nonce'] ) || ! wp_verify_nonce( $_POST['_puro_page_settings_nonce'], 'save_page_settings' ) ) return;
		if ( empty( $_POST['puro_page_settings'] ) ) return;

		$settings = stripslashes_deep( $_POST['puro_page_settings'] );

		foreach ( $this->get_settings( 'post', $post_id ) as $id => $field ) {
			switch ( $field['type'] ) {
				case 'select':
					if ( ! in_array( $settings[ $id ], array_keys( $field['options'] ) ) ) {
						$settings[ $id ] = isset( $field['default'] ) ? $field['default'] : null;
					}
					break;

				case 'checkbox':
					$settings[ $id ] = ! empty( $settings[ $id ] );
					break;

				case 'text':
				default :
					$settings[ $id ] = sanitize_text_field( $settings[ $id ] );
					break;
			}
		}

		update_post_meta( $post_id, 'puro_page_settings', $settings );
	}

}

// Setup the single.
Puro_Extras_Page_Settings::single();

function puro_page_setting( $setting = false, $default = false ) {
	return Puro_Extras_Page_Settings::single()->get( $setting, $default );
}
