<?php

add_filter( 'nmwsp_registered_nav_menus', function () {
	return array(
		'bar' => true,
	);
} );