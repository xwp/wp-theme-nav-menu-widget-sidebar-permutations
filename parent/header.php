<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div id="page" class="site">
	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>

			<?php $description = get_bloginfo( 'description', 'display' ); ?>
			<?php if ( $description || is_customize_preview() ) : ?>
				<p class="site-description"><?php echo $description; /* WPCS: xss ok. */ ?></p>
			<?php endif; ?>
		</div><!-- .site-branding -->

		<nav id="nav-menus" role="navigation">
			<?php
			foreach ( apply_filters( 'nmwsp_rendered_nav_menus', nmwsp_get_registered_nav_menu_locations() ) as $location => $name ) {
				printf( '<h2>Menu: %s</h2>', esc_html( $name ) );
				if ( has_nav_menu( $location ) ) {
					wp_nav_menu( array(
						'theme_location' => $location,
						'menu_id' => $location . '-menu',
						'fallback_cb' => false,
					) );
				} else {
					echo "Nav menu $location unassigned.";
				}
			}
			?>
		</nav><!-- #nav-menus -->
	</header><!-- #masthead -->

	<div id="content" class="site-content">
