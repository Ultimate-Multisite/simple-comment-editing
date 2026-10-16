<?php
/**
 * Settings persistence and validation tests.
 *
 * @package CommentEditLite\Tests
 */

namespace DLXPlugins\CommentEditLite\Tests;

use DLXPlugins\CommentEditLite\Options;

/**
 * Exercise real WordPress option storage and plugin validation.
 */
class Options_Test extends \WP_UnitTestCase {
	use Plugin_State;

	/** Start with default settings. */
	public function set_up() {
		parent::set_up();
		$this->set_up_plugin_state();
		delete_site_option( 'sce_options' );
	}

	/** Restore caches before the database rollback. */
	public function tear_down() {
		$this->tear_down_plugin_state();
		parent::tear_down();
	}

	/** Defaults are safe and predictable. */
	public function test_defaults() {
		$this->assertSame( 5, Options::get_options( true, 'timer' ) );
		$this->assertTrue( Options::get_options( false, 'allow_delete' ) );
		$this->assertFalse( Options::get_options( false, 'allow_unlimited_editing' ) );
		$this->assertSame( 'trash', Options::get_options( false, 'delete_behavior_users' ) );
		$this->assertFalse( Options::get_options( false, 'missing_setting' ) );
	}

	/** Partial saves preserve defaults and normalize booleans. */
	public function test_partial_update_persists_validated_options() {
		Options::update_options(
			array(
				'show_timer'   => 'false',
				'allow_delete' => 'true',
				'timer'        => '12',
			)
		);
		$this->assertFalse( Options::get_options( true, 'show_timer' ) );
		$this->assertTrue( Options::get_options( false, 'allow_delete' ) );
		$this->assertSame( 12, Options::get_options( false, 'timer' ) );
		$this->assertSame( 'default', Options::get_options( false, 'button_theme' ) );
		$this->assertSame( 12, get_site_option( 'sce_options' )['timer'] );
	}

	/** Invalid timers fall back to the default. */
	public function test_invalid_timer_falls_back_to_five_minutes() {
		Options::update_options( array( 'timer' => 'not-a-number' ) );
		$this->assertSame( 5, Options::get_options( true, 'timer' ) );
	}

	/** Destructive behaviour requires an explicit supported value. */
	public function test_invalid_delete_behaviour_defaults_to_trash() {
		Options::update_options(
			array(
				'delete_behavior_users'    => 'invalid',
				'spam_behavior_moderators' => 'invalid',
			)
		);
		$this->assertSame( 'trash', Options::get_options( true, 'delete_behavior_users' ) );
		$this->assertSame( 'spam', Options::get_options( false, 'spam_behavior_moderators' ) );
	}

	/** Text settings are sanitized before persistence. */
	public function test_text_settings_are_sanitized() {
		Options::update_options( array( 'save_text' => '<b>Save safely</b>' ) );
		$this->assertSame( 'Save safely', Options::get_options( true, 'save_text' ) );
		$this->assertSame( 'Save safely', get_site_option( 'sce_options' )['save_text'] );
	}
}
