<?php

add_filter( 'nmwsp_registered_widget_sidebars', function () {
	return array(
		'primary' => true,
		'footer' => true,
	);
} );
