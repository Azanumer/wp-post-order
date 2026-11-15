<?php
/**
 * Plugin Name: Post Order
 * Plugin URI: https://github.com/Azanumer/wp-post-order
 * Description: Drag-and-drop ordering for posts, pages and custom post types — set a manual order in the admin list and have the front end honour it automatically.
 * Version: 1.0.0
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * Text Domain: wp-post-order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPO_VERSION', '1.0.0' );
define( 'WPO_OPTION', 'wpo_settings' );

/**
 * Default settings.
 *
 * @return array
 */
function wpo_default_settings() {
	return array(
		'post_types' => array( 'post', 'page' ),
	);
}

/**
 * Get merged settings.
 *
 * @return array
 */
function wpo_get_settings() {
	$saved = get_option( WPO_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( wpo_default_settings(), $saved );
}

/**
 * Post types that get drag-and-drop ordering.
 *
 * @return string[]
 */
function wpo_post_types() {
	$settings = wpo_get_settings();
	$types    = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );
	$types    = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
	return apply_filters( 'wpo_post_types', $types );
}

/**
 * Add page-attributes support (menu_order) to the ordered post types.
 */
function wpo_add_menu_order_support() {
	foreach ( wpo_post_types() as $type ) {
		if ( post_type_exists( $type ) ) {
			add_post_type_support( $type, 'page-attributes' );
		}
	}
}
add_action( 'init', 'wpo_add_menu_order_support' );

/**
 * Honour the manual order on the front end: when a query asks for an
 * ordered post type without an explicit orderby, sort by menu_order.
 *
 * @param WP_Query $query Query object.
 */
function wpo_apply_front_end_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$types = wpo_post_types();
	if ( empty( $types ) ) {
		return;
	}
	$queried = $query->get( 'post_type' );
	if ( empty( $queried ) ) {
		$queried = 'post';
	}
	$queried = (array) $queried;
	if ( empty( array_intersect( $queried, $types ) ) ) {
		return;
	}
	// Respect an explicit orderby chosen by the theme or another plugin.
	if ( '' !== $query->get( 'orderby' ) ) {
		return;
	}
	$query->set( 'orderby', 'menu_order' );
	$query->set( 'order', 'ASC' );
}
add_action( 'pre_get_posts', 'wpo_apply_front_end_order' );

/**
 * Add an Order column to the list tables of ordered post types.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function wpo_order_column( $columns ) {
	$columns['wpo_order'] = __( 'Order', 'wp-post-order' );
	return $columns;
}

/**
 * Render the Order column value.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function wpo_order_column_content( $column, $post_id ) {
	if ( 'wpo_order' !== $column ) {
		return;
	}
	echo (int) get_post_field( 'menu_order', $post_id );
}

/**
 * Make the Order column sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function wpo_sortable_order_column( $columns ) {
	$columns['wpo_order'] = 'menu_order';
	return $columns;
}

/**
 * Hook the columns in for every ordered post type.
 */
function wpo_register_list_columns() {
	foreach ( wpo_post_types() as $type ) {
		add_filter( "manage_{$type}_posts_columns", 'wpo_order_column' );
		add_filter( "manage_edit-{$type}_sortable_columns", 'wpo_sortable_order_column' );
		add_action( "manage_{$type}_posts_custom_column", 'wpo_order_column_content', 10, 2 );
	}
}
add_action( 'admin_init', 'wpo_register_list_columns' );

/**
 * Enqueue the drag-and-drop script on the ordered post-type list screens.
 *
 * @param string $hook_suffix Current admin page.
 */
function wpo_enqueue_order_script( $hook_suffix ) {
	if ( 'edit.php' !== $hook_suffix ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'edit' !== $screen->base || ! in_array( $screen->post_type, wpo_post_types(), true ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script(
		'wpo-order',
		plugins_url( 'assets/js/wpo-order.js', __FILE__ ),
		array( 'jquery', 'jquery-ui-sortable' ),
		WPO_VERSION,
		true
	);
	wp_localize_script(
		'wpo-order',
		'wpoOrder',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpo_save_order' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'wpo_enqueue_order_script' );

/**
 * AJAX handler: save the dragged order as menu_order values.
 */
function wpo_ajax_save_order() {
	check_ajax_referer( 'wpo_save_order', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to reorder posts.', 'wp-post-order' ) ), 403 );
	}

	$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'absint', wp_unslash( $_POST['order'] ) ) : array();
	$type  = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';

	if ( empty( $order ) || ! in_array( $type, wpo_post_types(), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'wp-post-order' ) ), 400 );
	}

	$position = 0;
	foreach ( $order as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== $type ) {
			continue;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			continue;
		}
		// wp_update_post fires save_post; menu_order is a plain field, so
		// this cannot trigger an infinite loop here.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'menu_order' => $position,
			)
		);
		$position++;
	}

	wp_send_json_success( array( 'saved' => $position ) );
}
add_action( 'wp_ajax_wpo_save_order', 'wpo_ajax_save_order' );

/**
 * Settings page under Settings → Post Order.
 */
function wpo_register_settings_page() {
	add_options_page(
		__( 'Post Order', 'wp-post-order' ),
		__( 'Post Order', 'wp-post-order' ),
		'manage_options',
		'wpo-settings',
		'wpo_render_settings_page'
	);
}
add_action( 'admin_menu', 'wpo_register_settings_page' );

/**
 * Render the settings page.
 */
function wpo_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-post-order' ) );
	}

	if ( isset( $_POST['wpo_save'] ) ) {
		check_admin_referer( 'wpo_save_settings' );
		$types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) ) : array();
		update_option(
			WPO_OPTION,
			array(
				'post_types' => array_values( array_filter( $types ) ),
			)
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-post-order' ) . '</p></div>';
	}

	$settings   = wpo_get_settings();
	$all_types  = get_post_types( array( 'show_ui' => true ), 'objects' );
	$enabled    = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Post Order', 'wp-post-order' ); ?></h1>
		<p><?php esc_html_e( 'Choose which post types get drag-and-drop ordering. Drag rows on the post list screen; the front end lists that post type in the same order.', 'wp-post-order' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'wpo_save_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Post types', 'wp-post-order' ); ?></th>
					<td>
						<?php foreach ( $all_types as $type ) : ?>
							<label>
								<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $enabled, true ) ); ?> />
								<?php echo esc_html( $type->labels->singular_name ); ?> (<?php echo esc_html( $type->name ); ?>)
							</label><br />
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Hierarchical types (like pages) already support ordering; this adds the same behaviour to flat types such as posts.', 'wp-post-order' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'wp-post-order' ), 'primary', 'wpo_save' ); ?>
		</form>
	</div>
	<?php
}
