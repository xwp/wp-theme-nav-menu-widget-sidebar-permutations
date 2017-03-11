<?php get_header(); ?>

<main id="main" class="site-main" role="main">
	<h2 class="page-title"><?php esc_html_e( 'Main Content', 'nav-menu-widget-sidebar-permutations' ); ?></h2>

	<?php if ( have_posts() ) : ?>

		<?php if ( is_archive() ) : ?>
			<header>
				<?php the_archive_title(); ?>
			</header>
		<?php endif; ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<?php get_template_part( 'template-parts/content', get_post_format() ); ?>
		<?php endwhile; ?>

		<?php the_posts_navigation(); ?>

	<?php else : ?>

		<?php get_template_part( 'template-parts/content', 'none' ); ?>

	<?php endif; ?>

</main><!-- #main -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
