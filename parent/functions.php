<?php

/**
 * Get the registered sidebar IDs.
 *
 * @return array Sidebar IDs.
 */
function nav_menu_widget_sidebar_permutations_get_registered_sidebar_ids() {
	return apply_filters( 'nav_menu_widget_sidebar_permutations_registered_sidebars', array(
		'sidebar-1',
		'sidebar-2',
		'sidebar-3',
	) );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function nav_menu_widget_sidebar_permutations_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'menu-1' => esc_html__( 'Primary', 'nav-menu-widget-sidebar-permutations' ),
	) );

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
add_action( 'after_setup_theme', 'nav_menu_widget_sidebar_permutations_setup' );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function nav_menu_widget_sidebar_permutations_widgets_init() {
	foreach ( nav_menu_widget_sidebar_permutations_get_registered_sidebar_ids() as $sidebar_id ) {
		register_sidebar( array(
			'id' => $sidebar_id,
			'name' => ucwords( preg_replace( '/-|_/', ' ', $sidebar_id ) ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		) );
	}
}
add_action( 'widgets_init', 'nav_menu_widget_sidebar_permutations_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function nav_menu_widget_sidebar_permutations_scripts() {
	wp_enqueue_style( 'nav_menu_widget_sidebar_permutations-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'nav_menu_widget_sidebar_permutations_scripts' );
