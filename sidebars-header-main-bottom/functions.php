<?php

add_filter( 'nmwsp_registered_widget_sidebars', function () {
	return array(
		'header' => true,
		'main' => true,
		'bottom' => true,
	);
} );
