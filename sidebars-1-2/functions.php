<?php

add_filter( 'nmwsp_registered_widget_sidebars', function () {
	return array(
		'sidebar-1' => true,
		'sidebar-2' => true,
	);
} );
