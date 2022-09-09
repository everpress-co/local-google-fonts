<?php


$buffer = get_option( 'local_google_fonts_buffer', array() );

$folder     = WP_CONTENT_DIR . '/uploads/fonts';
$folder_url = WP_CONTENT_URL . '/uploads/fonts';
$count      = count( $buffer );

if ( ! $count ) :
	add_settings_error( 'local_google_fonts_messages', 'local_google_fonts_message', __( 'You have currently no Google fonts in use on your site.', 'local-google-fonts' ) );
		endif;
	settings_errors( 'local_google_fonts_messages' );
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form action="options.php" method="post">
	<?php
	settings_fields( 'local_google_fonts_settings_page' );
	do_settings_sections( 'local_google_fonts_settings_page' );

	?>
	<?php submit_button(); ?>
	
<hr>
<h2><?php printf( esc_html__( _n( '%d Google font source found on your site.', '%d Google font sources found on your site.', $count, 'local-google-fonts' ) ), $count ); ?></h2>

<p><?php esc_html_e( 'This page shows all discovered Google Fonts over time. If you miss a font start browsing your front end so they end up showing here.', 'local-google-fonts' ); ?></p>

	<?php foreach ( $buffer as $id => $data ) : ?>

<h3><?php esc_html_e( 'Handle', 'local-google-fonts' ); ?>: <code><?php esc_html_e( $data['handle'] ); ?></code></h3>
<p><?php esc_html_e( 'Original URL', 'local-google-fonts' ); ?>: <code><?php echo rawurldecode( $data['src'] ); ?></code> <a href="<?php echo esc_url( $data['src'] ); ?>" class="dashicons dashicons-external" target="_blank" title="<?php esc_attr_e( 'show original URL', 'local-google-fonts' ); ?>"></a></p>

		<?php $fontinfo = $this->get_font_info( $data['src'], $data['handle'] ); ?>

		<?php if ( is_wp_error( $fontinfo ) ) : ?>
	<div class="notice inline error">
		<p><strong><?php echo esc_html( $fontinfo->get_error_message() ); ?></strong></p>
	</div>
	<?php else : ?>

	<table class="wp-list-table widefat fixed striped table-view-list ">
		<thead>
			<tr>
				<th scope="col" id="name" class="manage-column column-name column-primary" style="width: 150px"><?php esc_html_e( 'Name', 'local-google-fonts' ); ?></th>
				<th scope="col" id="description" class="manage-column column-description"><?php esc_html_e( 'Variants', 'local-google-fonts' ); ?></th>
				<th scope="col" id="auto-updates" class="manage-column column-auto-updates" style="width: 250px"><?php esc_html_e( 'Status', 'local-google-fonts' ); ?></th>
			</tr>
		</thead>
		<tbody>
				<?php foreach ( $fontinfo as $i => $font ) : ?>

					<?php $filename = $font->id . '-' . $font->version . '-' . $font->defSubset; ?>
			<tr>
				<td><strong><?php echo esc_html( $font->family ); ?></strong><br>
					<?php if ( $font->id != $font->original ) : ?>
						<span class="font-alternative" title="<?php esc_attr_e( 'This is the best alternative for a font no longer supported.', 'local-google-fonts' ); ?>"><?php esc_html_e( 'alternative', 'local-google-fonts' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<p class="code">
					<?php foreach ( $font->variants as $variant ) : ?>
							<span class="variant"><?php printf( '%s %s', $variant->fontStyle, $variant->fontWeight ); ?></span> 
					<?php endforeach ?>
					</p>
					<?php $active_subsets = isset( $data['subsets'] ) ? $data['subsets'][ $font->id ] : array_keys( array_filter( (array) $font->subsetMap ) ); ?>
					<p><strong><?php esc_html_e( 'Subsets', 'local-google-fonts' ); ?></strong><br>
					<?php foreach ( $font->subsetMap as $subset => $is_active ) : ?>
						<label title="<?php printf( esc_attr__( 'Load %s subset with this font', 'local-google-fonts' ), $subset ); ?>" class="subset"><input type="checkbox" name="subsets[<?php echo esc_attr( $data['handle'] ); ?>][<?php echo esc_attr( $font->id ); ?>][]" value="<?php echo esc_attr( $subset ); ?>" <?php checked( in_array( $subset, $active_subsets ) ); ?>> <?php echo esc_html( $subset ); ?> </label> 
					<?php endforeach ?>
					</p>
					<details>
						<summary><strong><?php printf( esc_html__( '%1$d of %2$d files loaded.', 'local-google-fonts' ), $font->loaded, $font->total ); ?></strong></summary>
						<div style="max-height: 280px; overflow: scroll;font-size: small;white-space: nowrap; overflow: hidden; overflow-y: auto;" class="code">
						<?php foreach ( $font->variants as $variant ) : ?>
							<div>
							<h4><?php printf( '%s %s', $variant->fontStyle, $variant->fontWeight ); ?></h4>
							<?php foreach ( array( 'woff', 'svg', 'woff2', 'ttf', 'eot' ) as $ext ) : ?>
								<ul>
								<li>
								<?php $file = $data['id'] . '/' . $filename . '-' . $variant->id . '.' . $ext; ?>
								<?php if ( file_exists( $folder . '/' . $file ) ) : ?>
									<code><?php esc_html_e( 'Local', 'local-google-fonts' ); ?>: <a href="<?php echo esc_url( $folder_url . '/' . $file ); ?>" download><?php echo esc_html( basename( $file ) ); ?></a></code>
									<strong title="<?php esc_attr_e( 'loaded, served from your server', 'local-google-fonts' ); ?>">✔</strong>
								<?php else : ?>
									<code><?php esc_html_e( 'Local', 'local-google-fonts' ); ?>: <?php echo esc_html( basename( $file ) ); ?></code>
									<strong class="wp-ui-text-notification" title="<?php esc_attr_e( 'not loaded, served from Google servers', 'local-google-fonts' ); ?>">✕</strong>
								<?php endif; ?>	
								</li>								
								<li><code><?php esc_html_e( 'Remote', 'local-google-fonts' ); ?>: <?php echo esc_url( $variant->{$ext} ); ?></code></li>
								</ul>
							<?php endforeach; ?>
							</div>
						<?php endforeach ?>
						</div>
					</details>
				</td>
				<td>
					<?php if ( is_dir( $folder . '/' . $data['id'] ) ) : ?>
						<?php printf( '%s %s', '<strong>✔</strong>', esc_html__( 'loaded, served from your server', 'local-google-fonts' ) ); ?>
					<?php else : ?>
						<?php printf( '%s %s', '<strong class="wp-ui-text-notification">✕</strong>', esc_html__( 'not loaded, served from Google servers', 'local-google-fonts' ) ); ?>
					<?php endif; ?>
				
				</td>

			</tr>
		<?php endforeach ?>
		</tbody>
	</table>		
	<p>
			<?php if ( is_dir( $folder . '/' . $data['id'] ) ) : ?>
		<button class="host-locally button button-primary" name="hostlocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Reload Fonts', 'local-google-fonts' ); ?></button>
		<button class="host-locally button button-secondary" name="preload" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Preload', 'local-google-fonts' ); ?></button>
		<button class="host-locally button button-link-delete" name="removelocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Remove hosted files', 'local-google-fonts' ); ?></button>
		<?php else : ?>
		 <button class="host-locally button button-primary" name="hostlocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Host locally', 'local-google-fonts' ); ?></button>
		<?php endif; ?>
	</p>
<?php endif; ?>

<?php endforeach ?>
<hr>
	<p class="textright">
		<button class="host-locally button button-link-delete" name="flush" value="1"><?php esc_html_e( 'Remove all stored data', 'local-google-fonts' ); ?></button>
	</p>			
<?php submit_button(); ?>							

	</form>
</div>
