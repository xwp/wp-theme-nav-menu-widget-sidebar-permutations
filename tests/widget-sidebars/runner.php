<?php
/**
 * Run tests for nav menu location reassignments when switching themes.
 *
 * Note: The database will be entirely reset while the tests are run, and it will be restored upon finising of the tests.
 *
 * USAGE:
 * $ php runner.php --yes
 * $ php runner.php --yes --methods=wp-cli
 * $ php runner.php --yes --methods=wp-cli,customizer
 * $ php runner.php --yes --methods=admin,customizer
 *
 * @package NMWSP
 */

namespace NMWSP;

if ( 'cli' !== php_sapi_name() ) {
	echo "Error: Must only be run via CLI.\n";
	exit( 1 );
}

if ( ! in_array( '--yes', $argv ) ) {
	echo "Error: You must explicitly supply --yes to indicate that you know that the DB will be reset while the tests are running.\n";
	exit( 1 );
}

$scenarios = json_decode( file_get_contents( __DIR__ . '/scenarios.json' ), true );

$recognized_methods = array( 'wp-cli', 'admin', 'customizer' );

$methods = array();
foreach ( $argv as $arg ) {
	if ( preg_match( '/--methods?=(.+)/', $arg, $matches ) ) {
		$methods = array_merge( $methods, explode( ',', $matches[1] ) );
	}
}
$unrecognized_methods = array_diff( $methods, $recognized_methods );
if ( ! empty( $unrecognized_methods ) ) {
	fwrite( STDERR, sprintf( "Error: Unrecognized method(s): %s\n", implode( ', ', $unrecognized_methods ) ) );
	exit( 1 );
}
if ( empty( $methods ) ) {
	$methods = $recognized_methods;
}

const URL = 'http://src.wordpress-develop.dev';
const ADMIN_USER = 'admin';
const ADMIN_PASSWORD = 'admin';
$db_backup_file = tempnam( sys_get_temp_dir(), 'nav-menu-test-runner-backup.' ) . '.sql';
system( sprintf( 'wp db export %s', escapeshellarg( $db_backup_file ) ) );

$exit_code = 0;
try {

	/**
	 * Set up initial state.
	 *
	 * @param array $scenario Test scenario.
	 *
	 * @return array Sidebars widgets.
	 * @throws \Exception
	 */
	function set_up_initial_state( $scenario ) {
		system( 'wp db reset --yes' );
		system(
			sprintf(
				'wp core install --url=%s --title="WordPress Develop" --admin_user=%s --admin_password=%s --admin_email=admin@example.com --skip-email',
				escapeshellarg( URL ),
				escapeshellarg( ADMIN_USER ),
				escapeshellarg( ADMIN_PASSWORD )
			)
		);
		system( 'wp cache flush' );
		system( 'wp option set fresh_site 0' );
		system( sprintf( 'wp theme activate %s', $scenario['from'] ) );
		$widget_ids = call_user_func_array( 'array_merge', get_sidebars_widgets() );
		if ( ! empty( $widget_ids ) ) {
			system( sprintf(
				'wp widget delete %s',
				join( ' ', array_map(
					function( $widget_id ) {
						return escapeshellarg( $widget_id );
					},
					$widget_ids
				) )
			) );
		}

		$count = 1;
		foreach ( $scenario['widget_sidebars_assigned'] as $sidebar_id ) {
			for ( $i = 1; $i <= $count; $i += 1 ) {
				system( sprintf(
					'wp widget add text %s --title=%s --text=%s',
					escapeshellarg( $sidebar_id ),
					escapeshellarg( "$sidebar_id:text:title" ),
					escapeshellarg( "$sidebar_id:text:text" )
				) );
			}
		}

		$sidebars_widgets = get_sidebars_widgets();
		foreach ( $scenario['widget_sidebars_assigned'] as $sidebar_id ) {
			if ( empty( $sidebars_widgets[ $sidebar_id ] ) ) {
				throw new \Exception( "Expected $sidebar_id to be populated." );
			}
			if ( count( $sidebars_widgets[ $sidebar_id ] ) !== $count ) {
				throw new \Exception( "Expected $sidebar_id to have $count widget(s)." );
			}
		}

		return $sidebars_widgets;
	};

	/**
	 * Get sidebars widgets.
	 *
	 * @return array Sidebars widgets.
	 */
	function get_sidebars_widgets() {
		$sidebars_widgets = array();
		$sidebars = json_decode( exec( 'wp sidebar list --json' ), true );
		foreach ( $sidebars as $sidebar ) {
			$sidebars_widgets[ $sidebar['id'] ] = array_map(
				function( $widget ) {
					return $widget['id'];
				},
				json_decode( exec( sprintf( 'wp widget list %s --json', escapeshellarg( $sidebar['id'] ) ) ), true )
			);
		}
		return $sidebars_widgets;
	}

	/**
	 * Switch to theme.
	 *
	 * @param string $theme  Theme to switch to.
	 * @param string $method Method to use to switch.
	 *
	 * @throws \Exception
	 * @return array Result.
	 */
	function switch_to_theme( $theme, $method ) {
		$result = array();
		if ( 'wp-cli' === $method ) {
			system( sprintf( 'wp theme activate %s', escapeshellarg( $theme ) ) );
		} elseif ( 'admin' === $method ) {
			echo "Opening Admin in headless Chrome...\n";
			system( sprintf(
				'node %s --theme=%s --url=%s',
				escapeshellarg( __DIR__ . '/switch-theme-via-admin.js' ),
				escapeshellarg( $theme ),
				escapeshellarg( URL )
			), $return_var );
			if ( $return_var ) {
				throw new \Exception( 'Failed to switch theme.' );
			}
		} elseif ( 'customizer' === $method ) {
			echo "Opening Customizer in headless Chrome...\n";
			$result['changeset_uuid'] = strtolower( trim( exec( 'uuidgen' ) ) );
			system( sprintf(
				'node %s --theme=%s --changeset_uuid=%s --url=%s',
				escapeshellarg( __DIR__ . '/switch-theme-via-customizer.js' ),
				escapeshellarg( $theme ),
				escapeshellarg( $result['changeset_uuid'] ),
				escapeshellarg( URL )
			), $return_var );
			if ( $return_var ) {
				throw new \Exception( 'Failed to switch theme.' );
			}
		} else {
			throw new \Exception( "Unrecognized method: $method" );
		}
		return $result;
	}

	/**
	 * Test switch to theme and back via WP-CLI.
	 *
	 * @param array  $scenario                  Test scenario.
	 * @param array  $original_sidebars_widgets Mappings of menu ID to menu location.
	 * @param string $method                    Method for remapping. Can be 'wp-cli' or 'customizer'.
	 *
	 * @throws \Exception
	 */
	function test_switch_to_theme_and_back( $scenario, $original_sidebars_widgets, $method = 'wp-cli' ) {
		switch_to_theme( $scenario['to'], $method );

		$switched_sidebars_widgets = get_sidebars_widgets();
		$check_switching = function( $original_sidebars_widgets, $switched_sidebars_widgets ) use ( $scenario ) {
			foreach ( $scenario['expected_sidebar_mapping'] as $old_sidebar_id => $new_sidebar_id ) {
				if ( ! isset( $original_sidebars_widgets[ $old_sidebar_id ] ) ) {
					throw new \Exception( "Expected sidebar $old_sidebar_id to be in original theme." );
				}
				if ( ! $new_sidebar_id ) {
					// Make sure $original_sidebars_widgets[ $old_sidebar_id ] didn't map into any of $switched_sidebars_widgets.
					if ( false !== array_search( $original_sidebars_widgets[ $old_sidebar_id ], $switched_sidebars_widgets ) ) {
						throw new \Exception( "Widgets in $old_sidebar_id were unexpectedly mapped into switched theme's sidebars." );
					}
					echo "PASS: $old_sidebar_id was not mapped to new widget\n";
				} else {
					if ( ! isset( $switched_sidebars_widgets[ $new_sidebar_id ] ) ) {
						throw new \Exception( "Expected sidebar $new_sidebar_id to be in switched-to theme." );
					}
					if ( $original_sidebars_widgets[ $old_sidebar_id ] !== $switched_sidebars_widgets[ $new_sidebar_id ] ) {
						throw new \Exception( "Widgets did not map from $old_sidebar_id to $new_sidebar_id." );
					}
					printf( "PASS: $old_sidebar_id remapped to $new_sidebar_id, widget count: %d\n", count( $original_sidebars_widgets[ $old_sidebar_id ] ) );
				}
			}
		};
		$check_switching( $original_sidebars_widgets, $switched_sidebars_widgets );

		echo "Attempting to switch back to previous theme...\n";
		switch_to_theme( $scenario['from'], $method );

		$switched_back_sidebars_widgets = get_sidebars_widgets();
		foreach ( array_keys( $scenario['expected_sidebar_mapping'] ) as $old_sidebar_id ) {
			if ( $switched_back_sidebars_widgets[ $old_sidebar_id ] !== $original_sidebars_widgets[ $old_sidebar_id ] ) {
				throw new \Exception( "Expected original '$old_sidebar_id' sidebar to be same after switching back." );
			}
			echo "PASS: Sidebars widgets for $old_sidebar_id restored.\n";
		}

		echo "Adding new sidebar to each of the original theme's sidebars...\n";
		foreach ( array_keys( $scenario['expected_sidebar_mapping'] ) as $old_sidebar_id ) {
			system( sprintf(
				'wp widget add text %s --title=%s --text=%s',
				escapeshellarg( $old_sidebar_id ),
				escapeshellarg( "$old_sidebar_id:text:title additional" ),
				escapeshellarg( "$old_sidebar_id:text:text additional" )
			) );
		}

		$original_sidebars_widgets = get_sidebars_widgets();
		switch_to_theme( $scenario['to'], $method );
		$switched_sidebars_widgets = get_sidebars_widgets();

		$check_switching( $original_sidebars_widgets, $switched_sidebars_widgets );
	};

	// Run the tests.
	foreach ( $scenarios as $scenario_name => $scenario ) {
		if ( '#' === substr( $scenario_name, 0, 1 ) ) {
			continue;
		}

		echo "\n\n## $scenario_name\n";

		foreach ( $methods as $method ) {
			echo "\n### Testing switch via $method:\n";
			$location_menu_assignments = set_up_initial_state( $scenario );
			test_switch_to_theme_and_back( $scenario, $location_menu_assignments, $method );
		}
	}
} catch ( \Exception $e ) {
	fwrite( STDERR, sprintf( "Error: %s\n", $e->getMessage() ) );
	$exit_code = 1;
} finally {
	echo "\n";
	system( sprintf( 'wp db import %s', escapeshellarg( $db_backup_file ) ) );
	system( 'wp cache flush' );
}

if ( 0 === $exit_code ) {
	echo "✅  PASS\n";
} else {
	echo "🚫  FAIL\n";
}
exit( $exit_code );
