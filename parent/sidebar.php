<?php global $wp_registered_sidebars; ?>
<?php
$rendered_sidebars = apply_filters( 'nmwsp_rendered_widget_sidebars', nmwsp_get_registered_sidebars() );
?>
<?php foreach ( array_keys( $rendered_sidebars ) as $sidebar_id ) : ?>
	<?php if ( ! is_active_sidebar( $sidebar_id ) ) : ?>
		<!-- <?php printf( 'Widget sidebar %s inactive', esc_html( $sidebar_id ) ); ?> -->
	<?php else : ?>
		<?php
		$hex = substr( md5( $sidebar_id ), 0, 3 );
		$background_color = sprintf( '#F%sF%sF%s', $hex[0], $hex[1], $hex[2] );
		?>
		<aside class="widget-area" role="complementary" style="<?php echo esc_attr( sprintf( 'background-color: %s', $background_color ) ); ?>">
			<h2><?php echo esc_html( $wp_registered_sidebars[ $sidebar_id ]['name'] ); ?></h2>
			<?php dynamic_sidebar( $sidebar_id ); ?>
		</aside><!-- #secondary -->
	<?php endif; ?>

<?php endforeach; ?>
