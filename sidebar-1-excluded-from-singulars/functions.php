<?php

add_filter( 'nmwsp_registered_widget_sidebars', function () {
	return array(
		'sidebar-1' => true,
	);
} );

add_filter( 'nmwsp_rendered_widget_sidebars', function ( $rendered_sidebars ) {
	$rendered_sidebars['sidebar-1'] = ! is_singular();
	return $rendered_sidebars;
} );
