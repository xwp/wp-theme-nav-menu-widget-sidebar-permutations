<?php

add_filter( 'nmwsp_registered_nav_menus', function () {
	return array(
		'primary' => true,
		'secondary' => true,
		'social' => true,
	);
} );

add_filter( 'nmwsp_registered_widget_sidebars', function () {
	return array(
		'primary' => true,
		'secondary' => true,
	);
} );
