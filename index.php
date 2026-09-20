<?php
/**
 * Simple Comment Editing main file.
 *
 * @package SimpleCommentEditing
 */

/**
 * Plugin Name: Simple Comment Editing
 * Plugin URI: https://github.com/ronalfy/simple-comment-editing
 * Description: Allow your users to edit their comments.
 * Author: Ronald Huereca
 * Version: 3.4.1
 * Requires PHP: 7.2
 * Requires at least: 6.5
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://github.com/ronalfy/simple-comment-editing
 * Contributors: ronalfy
 * Text Domain: simple-comment-editing
 * Domain Path: /languages
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}
define( 'SCE_SLUG', plugin_basename( __FILE__ ) );
define( 'SCE_VERSION', '3.4.1' );
define( 'SCE_FILE', __FILE__ );
define( 'SCE_SPONSORS_URL', 'https://github.com/sponsors/ronalfy' );

require_once 'lib/autoload.php';
require 'simple-comment-editing.php';
