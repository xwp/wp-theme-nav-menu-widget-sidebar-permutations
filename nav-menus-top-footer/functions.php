<?php

add_filter( 'nmwsp_registered_nav_menus', function () {
	return array(
		'top' => true,
		'footer' => true,
	);
} );
