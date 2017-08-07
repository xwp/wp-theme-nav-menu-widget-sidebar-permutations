<?php

/**
 * Get the registered sidebars.
 *
 * @return array Registered sidebars.
 */
function nmwsp_get_registered_sidebars() {
	$raw_sidebars = array_filter( apply_filters( 'nmwsp_registered_sidebars', array(
		'sidebar-1' => true,
	) ) );

	$sidebars = array();
	foreach ( $raw_sidebars as $sidebar_id => $sidebar_args ) {
		if ( true === $sidebar_args ) {
			$sidebar_args = array();
		}
		$sidebar_args = array_merge(
			array(
				'name' => ucwords( preg_replace( '/-|_/', ' ', $sidebar_id ) ),
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget' => '</section>',
				'before_title' => '<h3 class="widget-title">',
				'after_title' => '</h3>',
			),
			$sidebar_args,
			array(
				'id' => $sidebar_id,
			)
		);
		$sidebars[ $sidebar_id ] = $sidebar_args;
	}

	return $sidebars;
}

/**
 * Get the registered nav menu locations.
 *
 * @return array Nav menu locations.
 */
function nmwsp_get_registered_nav_menu_locations() {
	$default_locations = array(
		'primary' => true,
	);
	$raw_locations = array_filter( apply_filters( 'nmwsp_registered_nav_menus', $default_locations ) );

	$locations = array();
	foreach ( $raw_locations as $location => $name ) {
		if ( true === $name ) {
			$name = ucfirst( $location );
		}
		$locations[ $location ] = $name;
	}
	return $locations;
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function nmwsp_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );

	register_nav_menus( nmwsp_get_registered_nav_menu_locations() );

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
	) );

	add_theme_support( 'customize-selective-refresh-widgets' );
}
add_action( 'after_setup_theme', 'nmwsp_setup', 20 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function nmwsp_widgets_init() {
	foreach ( nmwsp_get_registered_sidebars() as $id => $sidebar_args ) {
		register_sidebar( array_merge( $sidebar_args, compact( 'id' ) ) );
	}
}
add_action( 'widgets_init', 'nmwsp_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function nmwsp_scripts() {
	wp_enqueue_style( 'nmwsp-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'nmwsp_scripts' );
