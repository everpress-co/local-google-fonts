<?php

$buffer = get_option( 'local_google_fonts_buffer', array() );

$upload_dir = wp_get_upload_dir();
$folder     = $upload_dir['error'] ? WP_CONTENT_DIR . '/uploads/fonts' : $upload_dir['basedir'] . '/fonts';
$folder_url = $upload_dir['error'] ? WP_CONTENT_URL . '/uploads/fonts' : $upload_dir['baseurl'] . '/fonts';
$count      = count( $buffer );

if ( ! $count ) :
	add_settings_error( 'local_google_fonts_messages', 'local_google_fonts_message', __( 'You have currently no Google fonts in use on your site.', 'local-google-fonts' ) );
		endif;
	settings_errors( 'local_google_fonts_messages' );
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wrap-inner">
	<section class="main">
		<form action="options.php" method="post">
		<?php
		settings_fields( 'local_google_fonts_settings_page' );
		do_settings_sections( 'local_google_fonts_settings_page' );

		if ( $count ) :
			submit_button();
		endif;
		?>
					
	<hr>
	<h2><?php printf( esc_html__( _n( '%d Google font source found on your site.', '%d Google font sources found on your site.', $count, 'local-google-fonts' ) ), $count ); ?></h2>

	<p><?php esc_html_e( 'This page shows all discovered Google Fonts over time. If you miss a font start browsing your front end so they end up showing here.', 'local-google-fonts' ); ?></p>
	
		<?php foreach ( $buffer as $id => $data ) : ?>

	<h3><?php esc_html_e( 'Handle', 'local-google-fonts' ); ?>: <code><?php esc_html_e( $data['handle'] ); ?></code></h3>
	<p><?php esc_html_e( 'Original URL', 'local-google-fonts' ); ?>: <a href="<?php echo esc_url( $data['src'] ); ?>" class="dashicons dashicons-external" target="_blank" title="<?php esc_attr_e( 'show original URL', 'local-google-fonts' ); ?>"></a><code class="original-url"><?php echo rawurldecode( $data['src'] ); ?></code></p>

			<?php $fontinfo = $this->get_parser( $data['src'] )->get_info(); ?>

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
				<tr>
					<td><strong><?php echo esc_html( $font['name'] ); ?></strong>
					</td>
					<td>
						<p class="code">
						<?php foreach ( $font['variants'] as $variant ) : ?>
								<span class="variant"><?php echo esc_html( $variant ); ?></span>
						<?php endforeach ?>
						</p>
						<details>
							<summary><strong><?php printf( esc_html__( '%1$d of %2$d files loaded.', 'local-google-fonts' ), $font['loaded'], $font['total'] ); ?></strong></summary>
							<div style="max-height: 280px; overflow: scroll;font-size: small;white-space: nowrap; overflow: hidden; overflow-y: auto;" class="code">
							<?php foreach ( $font['faces'] as $face ) : ?>
								<div>
								<strong><?php printf( '%s %s', $face['style'], $face['weight'] ); ?></strong>
									<ul>
									<li>
									<?php if ( $face['loaded'] ) : ?>
										<code><?php esc_html_e( 'Local', 'local-google-fonts' ); ?>: <a href="<?php echo esc_url( $face['local_url'] ); ?>" download><?php echo esc_html( basename( $face['file'] ) ); ?></a></code>
										<strong title="<?php esc_attr_e( 'loaded, served from your server', 'local-google-fonts' ); ?>">✔</strong>
									<?php else : ?>
										<code><?php esc_html_e( 'Local', 'local-google-fonts' ); ?>: <?php echo esc_html( basename( $face['file'] ) ); ?></code>
										<strong class="wp-ui-text-notification" title="<?php esc_attr_e( 'not loaded, served from Google servers', 'local-google-fonts' ); ?>">✕</strong>
									<?php endif; ?>
									</li>
									<li><code><?php esc_html_e( 'Remote', 'local-google-fonts' ); ?>: <?php echo esc_url( $face['remote_url'] ); ?></code></li>
									</ul>
								</div>
							<?php endforeach ?>
							</div>
						</details>
					</td>
					<td>
						<?php if ( $font['loaded'] == $font['total'] ) : ?>
							<?php printf( '%s %s', '<strong>✔</strong>', sprintf( esc_html__( 'loaded from %s', 'local-google-fonts' ), '<code>' . wp_parse_url( $font['stylesheet'], PHP_URL_HOST ) . '</code>' ) ); ?>
						<?php elseif ( $font['loaded'] > 0 ) : ?>
							<?php printf( '%s %s', '<strong class="wp-ui-text-notification">✕</strong>', sprintf( esc_html__( 'partially loaded, some files are loaded from %s', 'local-google-fonts' ), '<code>' . wp_parse_url( $data['src'], PHP_URL_HOST ) . '</code>' ) ); ?>
						<?php else : ?>
							<?php printf( '%s %s', '<strong class="wp-ui-text-notification">✕</strong>', sprintf( esc_html__( 'loaded from %s', 'local-google-fonts' ), '<code>' . wp_parse_url( $data['src'], PHP_URL_HOST ) . '</code>' ) ); ?>
						<?php endif; ?>
						<?php if ( $font['filesize'] ) : ?>
							<p><?php esc_html_e( size_format( $font['filesize'] ) ); ?></p>
						<?php endif; ?>
					</td>

				</tr>
			<?php endforeach ?>
			</tbody>
		</table>
		<p>
			<?php if ( is_dir( $folder . '/' . $data['id'] ) ) : ?>
			<button class="host-locally button button-primary" name="hostlocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Reload Fonts', 'local-google-fonts' ); ?></button>
			<button class="host-locally button button-link-delete" name="removelocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Remove hosted files', 'local-google-fonts' ); ?></button>
			<?php else : ?>
			 <button class="host-locally button button-primary" name="hostlocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Host locally', 'local-google-fonts' ); ?></button>
			<?php endif; ?>
		</p>
	<?php endif; ?>


	<?php endforeach ?>
	<hr>
		<p class="textright">
			<button class="check button button-secondary" name="check"><?php esc_html_e( 'Manually check Homepage', 'local-google-fonts' ); ?></button>
			<button class="host-locally button button-link-delete" name="flush" value="1"><?php esc_html_e( 'Remove all stored data', 'local-google-fonts' ); ?></button>
		</p>
	<?php submit_button(); ?>

		</form>
	</section>
	<aside class="lgf-side">

		<h3><?php printf( esc_attr__( 'Optimize %s', 'local-google-fonts' ), wp_parse_url( get_option( 'home' ), PHP_URL_HOST ) ); ?></h3>
		<p><?php printf( esc_attr__( 'We partner with %s to provide a trusted resource for hiring top quality premium support to help you optimize your site.', 'local-google-fonts' ), '<a href="https://codeable.io/?ref=ebTBq" ref="noopener noreferrer" target="_blank">Codeable</a>' ); ?></p>

		<a href="https://codeable.io/?ref=ebTBq" class="codable-link" ref="noopener noreferrer" target="_blank" title="<?php esc_attr_e( 'visit Codeable', 'local-google-fonts' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 498 127"><defs><path d="M0 126.968h499.874V0H0z"/></defs><g fill="none" fill-rule="evenodd"><path d="m193.5 52.009-8.951 4.146c-2.278-2.276-4.476-3.983-9.114-3.983-5.288 0-10.903 3.983-10.903 11.624 0 7.56 5.615 11.463 10.903 11.463 4.638 0 6.836-1.626 9.114-3.902l9.032 4.145c-4.07 6.829-10.416 9.999-18.308 9.999-9.439 0-22.05-6.828-22.05-21.705 0-14.714 12.611-21.949 22.05-21.949 7.73 0 14.239 3.333 18.226 10.162M205.273 63.878c0 7.478 5.613 11.462 10.903 11.462 5.207 0 10.903-3.984 10.903-11.462 0-7.723-5.696-11.707-10.903-11.707-5.29 0-10.903 3.984-10.903 11.707m33.035 0c0 14.795-12.531 21.786-22.132 21.786s-22.214-6.991-22.214-21.786c0-14.796 12.613-22.03 22.214-22.03 9.6 0 22.132 7.234 22.132 22.03M273.28 63.634c0-7.56-5.371-11.462-11.148-11.462-5.777 0-10.741 3.902-10.741 11.462 0 7.804 4.964 11.706 10.74 11.706 5.778 0 11.148-3.902 11.148-11.706Zm11.065-36.257v57.474H272.71v-6.666c0 3.008-5.451 7.479-12.611 7.479-8.87 0-20.017-6.991-20.017-22.03 0-14.552 11.148-21.787 20.017-21.787 7.16 0 12.611 4.715 12.611 6.666V27.377h11.636ZM299.95 59.57h20.18c-1.303-5.204-5.94-7.805-10.172-7.805-4.15 0-8.462 2.601-10.008 7.804m30.432 8.21h-30.514c1.384 5.284 5.696 8.049 11.31 8.049 5.29 0 7.324-1.22 10.335-2.845l6.427 6.259c-3.743 3.82-8.868 6.422-17.168 6.422-10.496 0-22.458-7.235-22.458-21.786 0-14.795 12.125-22.03 21.644-22.03 9.601 0 22.946 7.235 20.424 25.932M343.523 63.634c0 7.804 4.962 11.706 10.74 11.706 5.777 0 11.147-3.902 11.147-11.706 0-7.56-5.37-11.462-11.147-11.462-5.778 0-10.74 3.902-10.74 11.462m21.318-15.121v-5.934h11.635V84.85H364.84v-6.666c0 3.008-5.452 7.479-12.613 7.479-8.869 0-20.017-6.991-20.017-22.03 0-14.552 11.148-21.787 20.017-21.787 7.16 0 12.613 4.715 12.613 6.666M415.613 63.634c0-7.56-4.963-11.462-10.74-11.462-5.778 0-11.148 3.902-11.148 11.462 0 7.804 5.37 11.706 11.147 11.706 5.778 0 10.74-3.902 10.74-11.706m11.31 0c0 15.039-11.146 22.03-20.015 22.03-7.161 0-12.613-4.471-12.613-7.479v6.666h-11.635V27.377h11.635v21.136c0-1.95 5.452-6.666 12.613-6.666 8.869 0 20.016 7.235 20.016 21.787" fill="#151D23"/><path fill="#151D23" mask="url(#b)" d="M430.997 84.851h11.636V27.377h-11.636zM458.206 59.57h20.18c-1.302-5.204-5.94-7.805-10.171-7.805-4.15 0-8.462 2.601-10.009 7.804m30.432 8.21h-30.513c1.383 5.284 5.696 8.049 11.31 8.049 5.29 0 7.324-1.22 10.334-2.845l6.428 6.259c-3.742 3.82-8.87 6.422-17.168 6.422-10.497 0-22.458-7.235-22.458-21.786 0-14.795 12.124-22.03 21.644-22.03 9.601 0 22.946 7.235 20.423 25.932"/><path d="M42.362 84.645c0 23.375 18.966 42.323 42.362 42.323 23.397 0 42.362-18.948 42.362-42.323 0-23.374-18.965-42.322-42.362-42.322v42.322H42.362Z" fill="#FFB199" mask="url(#b)"/><path fill="#165260" mask="url(#b)" d="M84.724 42.323V0H0v84.645h42.362V42.323z"/></g></svg>
		</a>

		<p><?php esc_attr_e( 'Get a quote within 10 minutes without obligations.', 'local-google-fonts' ); ?></p>

		<button class="button button-hero button-primary get-support"><?php esc_attr_e( 'Request Quote', 'local-google-fonts' ); ?>*</button>

		<p class="howto">* <?php esc_attr_e( 'Links to Codeable are affiliate links.', 'local-google-fonts' ); ?></p>
	</aside>
	</div>
</div>
