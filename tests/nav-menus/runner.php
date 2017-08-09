<?php

namespace NMWSP;

$scenarios = json_decode( file_get_contents( __DIR__ . '/scenarios.json' ), true );

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
		$i                         = 0;
		foreach ( $scenario['nav_menu_locations_assigned'] as $menu_location ) {
			$i += 1;
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

		$initial_switch_uuid = null;
		if ( 'wp-cli' === $method ) {
			system( sprintf( 'wp theme activate %s', escapeshellarg( $scenario['to'] ) ) );
		} elseif ( 'customizer' === $method ) {
			echo "Opening Customizer in headless Chrome...\n";
			$initial_switch_uuid = strtolower( trim( exec( 'uuidgen' ) ) );
			system( sprintf(
				'node %s --theme=%s --changeset_uuid=%s --url=%s',
				escapeshellarg( __DIR__ . '/switch-theme-via-customizer.js' ),
				escapeshellarg( $scenario['to'] ),
				escapeshellarg( $initial_switch_uuid ),
				escapeshellarg( URL )
			), $return_var );
			if ( $return_var ) {
				throw new Exception( 'Failed to switch theme.' );
			}
		}

		$menus = json_decode( exec( 'wp menu list --json' ), true );
		$switched_menu_locations = array();
		foreach ( $menus as $menu ) {
			foreach ( $menu['locations'] as $location ) {
				$switched_menu_locations[ $location ] = $menu['term_id'];
			}
		}

		$original_menu_locations = array_flip( $location_menu_assignments );

		foreach ( $scenario['expected_switch_theme_mapping'] as $from_location => $to_location ) {
			if ( is_null( $to_location ) ) {
				if ( ! empty( $switched_menu_locations[ $to_location ] ) ) {
					throw new Exception( "Expected $from_location to not have been mappped" );
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

		$switch_back_uuid = null;
		if ( 'wp-cli' === $method ) {
			system( sprintf( 'wp theme activate %s', escapeshellarg( $scenario['from'] ) ) );
		} elseif ( 'customizer' === $method ) {
			echo "Opening Customizer in headless Chrome...\n";
			$switch_back_uuid = strtolower( trim( exec( 'uuidgen' ) ) );
			system( sprintf(
				'node %s --theme=%s --changeset_uuid=%s --url=%s',
				escapeshellarg( __DIR__ . '/switch-theme-via-customizer.js' ),
				escapeshellarg( $scenario['from'] ),
				escapeshellarg( $switch_back_uuid ),
				escapeshellarg( URL )
			), $return_var );
			if ( $return_var ) {
				throw new Exception( 'Failed to switch theme back.' );
			}
		}

		$original_menus_after_switch_back = json_decode( exec( 'wp menu list --json' ), true );
		if ( $original_menus_before_switch !== $original_menus_after_switch_back ) {
			throw new Exception( 'Expected menu locations to be restored after switching back to the original theme.' );
		} else {
			echo "PASS: Menu locations got restored after switching back.\n";
		}
	};

	// Run the tests.
	foreach ( $scenarios as $scenario_name => $scenario ) {
		echo "\n\n## $scenario_name\n";

		echo "## Testing straight switch via WP-CLI:\n";
		$location_menu_assignments = set_up_initial_state( $scenario );
		test_switch_to_theme_and_back( $scenario, $location_menu_assignments, 'wp-cli' );

		echo "## Testing switch via Customizer:\n";
		$location_menu_assignments = set_up_initial_state( $scenario );
		test_switch_to_theme_and_back( $scenario, $location_menu_assignments, 'customizer' );
	}
} catch ( Exception $e ) {
	fwrite( STDERR, sprintf( "Error: %s\n", $e->getMessage() ) );
	$exit_code = 1;
} finally {
	echo "\n";
	system( sprintf( 'wp db import %s', escapeshellarg( $db_backup_file ) ) );
	system( 'wp cache flush' );
}
exit( $exit_code );
