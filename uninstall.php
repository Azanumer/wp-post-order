<?php
/**
 * Uninstall handler for Post Order.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpo_settings' );
