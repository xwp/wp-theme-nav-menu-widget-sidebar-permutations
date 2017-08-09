<?php

namespace NMWSP;

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

class Exception extends \Exception {}

$exit_code = 0;
try {

	/**
	 * Set up initial state.
	 *
	 * @param array $scenario Test scenario.
	 *
	 * @return array Mappings of menu ID to menu location.
	 * @throws Exception
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

		$menu_location_assignments = array();
		foreach ( $scenario['nav_menu_locations_assigned'] as $i => $menu_location ) {
			$menu_slug = "menu-$i";
			$menu_id = exec( sprintf( 'wp menu create %s --porcelain', escapeshellarg( $menu_slug ) ) );
			$menu_location_assignments[ $menu_id ] = $menu_location;

			system( sprintf(
				'wp menu item add-custom %s %s %s',
				escapeshellarg( $menu_id ),
				escapeshellarg( "$menu_slug:first" ),
				escapeshellarg( "http://example.com/$menu_slug/first" )
			) );

			system( sprintf( 'wp menu location assign %s %s', escapeshellarg( $menu_id ), escapeshellarg( $menu_location ) ) );
		}

		// Make sure that the initial assignments are as expected.
		$menus = json_decode( exec( 'wp menu list --json' ), true );
		if ( count( $menus ) !== count( $menu_location_assignments ) ) {
			throw new Exception( 'Not all menus were created.' );
		}
		foreach ( $menus as $menu ) {
			if ( ! isset( $menu_location_assignments[ $menu['term_id'] ] ) ) {
				throw new Exception( 'Missing menu1' );
			}
			$location = $menu_location_assignments[ $menu['term_id'] ];
			if ( array( $location ) !== $menu['locations'] ) {
				throw new Exception( 'Missing menu being assigned to expected location.' );
			}
		}

		return $menu_location_assignments;
	};

	/**
	 * Switch to theme.
	 *
	 * @param string $theme  Theme to switch to.
	 * @param string $method Method to use to switch.
	 *
	 * @throws Exception
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
				throw new Exception( 'Failed to switch theme.' );
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
				throw new Exception( 'Failed to switch theme.' );
			}
		} else {
			throw new Exception( "Unrecognized method: $method" );
		}
		return $result;
	}

	/**
	 * Test switch to theme and back via WP-CLI.
	 *
	 * @param array  $scenario                  Test scenario.
	 * @param array  $location_menu_assignments Mappings of menu ID to menu location.
	 * @param string $method                    Method for remapping. Can be 'wp-cli' or 'customizer'.
	 *
	 * @throws Exception
	 */
	function test_switch_to_theme_and_back( $scenario, $location_menu_assignments, $method = 'wp-cli' ) {
		$original_menus_before_switch = json_decode( exec( 'wp menu list --json' ), true );

		switch_to_theme( $scenario['to'], $method );

		$menus = json_decode( exec( 'wp menu list --json' ), true );
		$switched_menu_locations = array();
		foreach ( $menus as $menu ) {
			foreach ( $menu['locations'] as $location ) {
				$switched_menu_locations[ $location ] = $menu['term_id'];
			}
		}

		$original_menu_locations = array_flip( $location_menu_assignments );

		foreach ( $scenario['expected_location_mapping'] as $from_location => $to_location ) {
			if ( is_null( $to_location ) ) {
				if ( ! empty( $switched_menu_locations[ $to_location ] ) ) {
					throw new Exception( "Expected $from_location to not have been mapped" );
				} else {
					echo "PASS: $from_location did not get remapped\n";
				}
			} else {
				if ( $original_menu_locations[ $from_location ] !== $switched_menu_locations[ $to_location ] ) {
					throw new Exception( "Expected $from_location to have been switched to $to_location" );
				} else {
					echo "PASS: $from_location got remapped to $to_location\n";
				}
			}
		}

		echo "Attempting to switch back to previous theme...\n";
		switch_to_theme( $scenario['from'], $method );

		$original_menus_after_switch_back = json_decode( exec( 'wp menu list --json' ), true );
		if ( $original_menus_before_switch !== $original_menus_after_switch_back ) {
			throw new Exception( 'Expected menu locations to be restored after switching back to the original theme.' );
		} else {
			echo "PASS: Menu locations got restored after switching back.\n";
		}
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
} catch ( Exception $e ) {
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
