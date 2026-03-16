<?php
/**
 * Cleanup on plugin uninstall.
 *
 * @package CartFlush
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cartflush_expiration_time' );
delete_option( 'cartflush_import_rules' );
