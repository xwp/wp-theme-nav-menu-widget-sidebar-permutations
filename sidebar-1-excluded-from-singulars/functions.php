<?php

add_filter( 'nav_menu_widget_sidebar_permutations_registered_sidebars', function () {
	return array(
		'sidebar-1',
	);
} );

add_filter( 'nav_menu_widget_sidebar_permutations_rendered_sidebars', function ( $rendered_sidebars ) {
	$rendered_sidebars['sidebar-1'] = ! is_singular();
	return $rendered_sidebars;
} );
