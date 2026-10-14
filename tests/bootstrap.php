<?php
/**
 * Load the real WordPress integration test framework and plugin.
 *
 * @package CommentEditLite\Tests
 */

namespace DLXPlugins\CommentEditLite\Tests;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$sce_version   = getenv( 'WP_VERSION' ) ? getenv( 'WP_VERSION' ) : '6.5';
$sce_cache_dir = getenv( 'WP_TESTS_CACHE_DIR' ) ? getenv( 'WP_TESTS_CACHE_DIR' ) : getenv( 'HOME' ) . '/.cache/simple-comment-editing-tests';
$sce_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : $sce_cache_dir . '/' . $sce_version . '/tests';

if ( ! is_readable( $sce_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WordPress is not loaded yet.
	fwrite( STDERR, "WordPress tests missing. Run bash bin/install-wp-tests.sh first.\n" );
	exit( 1 );
}

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
if ( '1' === getenv( 'WP_MULTISITE' ) ) {
	define( 'WP_TESTS_MULTISITE', true );
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST']   = 'example.org';
$_SERVER['SERVER_NAME'] = 'example.org';
$_SERVER['REQUEST_URI'] = '/';

require_once $sce_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/index.php';
	}
);

require $sce_tests_dir . '/includes/bootstrap.php';
if ( is_multisite() ) {
	update_site_option( 'active_sitewide_plugins', array( SCE_SLUG => time() ) );
}
require_once __DIR__ . '/trait-plugin-state.php';
