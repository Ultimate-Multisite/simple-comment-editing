<?php
/**
 * Environment-only credentials for a disposable WordPress test database.
 *
 * WordPress installs (and drops) its test tables on every suite run.
 * Never point this configuration at a development or production database.
 *
 * @package CommentEditLite\Tests
 */

$sce_db_name = getenv( 'WP_TEST_DB_NAME' );
if ( ! is_string( $sce_db_name ) || ! preg_match( '/^[a-zA-Z0-9_]+_test$/D', $sce_db_name ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WordPress is not loaded yet.
	fwrite( STDERR, "Set WP_TEST_DB_NAME to a disposable database ending in _test.\n" );
	exit( 1 );
}

$sce_version   = getenv( 'WP_VERSION' ) ? getenv( 'WP_VERSION' ) : '6.5';
$sce_cache_dir = getenv( 'WP_TESTS_CACHE_DIR' ) ? getenv( 'WP_TESTS_CACHE_DIR' ) : getenv( 'HOME' ) . '/.cache/simple-comment-editing-tests';
$sce_core_dir  = getenv( 'WP_CORE_DIR' ) ? getenv( 'WP_CORE_DIR' ) : $sce_cache_dir . '/' . $sce_version . '/wordpress';
define( 'ABSPATH', rtrim( $sce_core_dir, '/\\' ) . '/' );
define( 'DB_NAME', $sce_db_name );
define( 'DB_USER', getenv( 'WP_TEST_DB_USER' ) ? getenv( 'WP_TEST_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', false !== getenv( 'WP_TEST_DB_PASSWORD' ) ? getenv( 'WP_TEST_DB_PASSWORD' ) : '' );
define( 'DB_HOST', getenv( 'WP_TEST_DB_HOST' ) ? getenv( 'WP_TEST_DB_HOST' ) : '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Simple Comment Editing tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );

$table_prefix = 'scetests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by the WordPress test installer.
