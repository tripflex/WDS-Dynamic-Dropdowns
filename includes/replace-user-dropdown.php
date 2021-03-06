<?php
/**
 * Replace the user dropdown with a AJAX dropdown.
 *
 * @package WDS Dynamics Dropdowns
 */

/**
 * Class to replace the user dropdown.
 */
class WDSDD_Replace_User_Dropdown {
	/**
	 * Parent plugin class
	 *
	 * @var WDS_Dynamic_Dropdowns
	 * @since  0.1.0
	 */
	protected $plugin = null;

	/**
	 * Constructor - add our hooks
	 *
	 * @since     0.1.0
	 *
	 * @param WDS_Dynamic_Dropdowns $plugin Main plugin class.
	 * @return    null
	 */
	public function __construct( $plugin ) {
		// Main plugin class.
		$this->plugin = $plugin;

		// Initiate hooks.
		$this->hooks();
	}

	/**
	 * Initiate hooks
	 *
	 * @since   0.1.0
	 *
	 * @return  void
	 */
	public function hooks() {
		add_action( 'wp_ajax_wds_replace_user_dropdown', array( $this, 'ajax_get_users' ) );
		add_action( 'wp_ajax_nopriv_wds_replace_user_dropdown', array( $this, 'ajax_get_users' ) );

		add_filter( 'wp_dropdown_users', array( $this, 'dropdown_users_callback' ) );
	}

	/**
	 * Callback for wp_dropdown_users
	 *
	 * @since  0.1.0
	 *
	 * @param  string $output Current markup for output.
	 * @return string         Modified markup for output.
	 */
	public function dropdown_users_callback( $output ) {
		global $post;

		$author_id = isset( $post->post_author ) ? $post->post_author : null;
		$author_data = get_userdata( $author_id );

		// Enqueue scripts/styles.
		$this->enqueue();

		return '
			<input type="text" name="post_author_override" id="wds-user-search" value="' . $author_id . '"/>
		';
	}

	/**
	 * Search for posts using post_title
	 *
	 * @since     0.1.0
	 *
	 * @return void
	 */
	public function ajax_get_users() {
		$security_check_passes = (
			! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& 'xmlhttprequest' === strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& isset( $_GET['nonce'], $_GET['q'] )
			&& wp_verify_nonce( $_GET['nonce'],  'wds-replace-user-dd-nonce' )
		);

		// Bail early if security checks don't pass.
		if ( ! $security_check_passes ) {
			wp_send_json_error();
		}

		// If we have an author id, get the display_name.
		if ( ! empty( $_GET['id'] ) ) {
			$author_data = get_userdata( absint( $_GET['id'] ) );

			// Bail early if user doesn't exist.
			if ( ! ( $author_data instanceof WP_User ) ) {
				wp_send_json_error();
			}

			$results = array(
				array(
					'id' => $author_data->ID,
					'text' => $author_data->display_name . ' (' . $author_data->user_email . ')',
				),
			);

			wp_send_json_success( $results );
		}

		// Sanitize search field.
		$search = sanitize_text_field( $_GET['q'] );

		// User query arguments.
		$args = array(
			'search' => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'ID' ),
			'who' => 'authors',
			'number' => 10,
		);

		/**
		 * Filter user query arguments.
		 *
		 * Modify user query arguments for the dynamic dropdown.
		 *
	 	 * @since  0.1.1
		 *
		 * @param array The current query arguments.
		 */
		$args = apply_filters( 'wp_dropdown_users_args', $args, array() );

		// Execute the user query.
		$user_query = new WP_User_Query( $args );

		// Bail if we don't have any results.
		if ( is_wp_error( $user_query ) ) {
			wp_send_json_error();
		}

		// Hold results for select2.
		$results  = array();

		foreach ( (array) $user_query->results as $user ) {
			$results[] = array(
				'id' => $user->ID,
				'text' => $user->display_name . ' (' . $user->user_email . ')',
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @since  0.1.0
	 *
	 * @return void
	 */
	protected function enqueue() {
		$data = array(
			'ajax_callback'	   => 'wds_replace_user_dropdown',
			'post_author'      => isset( $author_id ) ? $author_id : false,
			'display_name'     => isset( $author_data->display_name ) ? $author_data->display_name . '( ' . $author_data->user_email . ')' : null,
			'post_type'        => get_post_type(),
			'nonce'            => wp_create_nonce( 'wds-replace-user-dd-nonce' ),
			'placeholder_text' => esc_html__( 'Select an Author', 'wds-replace-user-dropdown' ),
		);

		// Enqueue select 2.
		wp_enqueue_script( 'select2', $this->plugin->url . 'assets/js/select2-3.5.0/select2.min.js', array( 'jquery' ), '3.5.0', true );
		wp_enqueue_style( 'select2', $this->plugin->url . 'assets/js/select2-3.5.0/select2.css', array(), '3.5.0' );
		wp_enqueue_script( 'wds-replace-user-dropdown', $this->plugin->url . 'assets/js/replace-user-dropdown.js', array( 'jquery', 'select2' ), $this->plugin->version, true );

		wp_localize_script( 'wds-replace-user-dropdown', 'wds_rud_config', $data );
	}
}
