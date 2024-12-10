<div class="modal fade" id="enable-modal" tabindex="-1" role="dialog" aria-labelledby="enable-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="enable-modal-label"><?php esc_html_e( 'Enable Cloud Uploads', 'cloud-uploads' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-4 mt-3">
						<div class="col text-center">
							<h4><?php esc_html_e( 'Enable the Cloud Uploads Cloud', 'cloud-uploads' ); ?></h4>
							<p class="lead"><?php esc_html_e( 'Your media library has finished syncing to the Cloud Uploads cloud. Enable now to serve all media from the cloud and global CDN. All new media uploaded will skip the local filesystem and be synced directly to the Cloud Uploads cloud.', 'cloud-uploads' ); ?></p>
							<?php $error_count = $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 0 AND errors >= 3" ); ?>
							<div id="cup-enable-errors" class="alert alert-warning text-left cup-enable-errors" role="alert" <?php echo ( $error_count ) ? '' : 'style="display:none;"'; ?>>
								<?php printf( __( '<span>%s</span> file(s) errored while syncing to the cloud.', 'cloud-uploads' ), number_format_i18n( $error_count ) ); ?>
								<a class="alert-link" data-toggle="collapse" href="#cup-collapse-errors" role="button" aria-expanded="false" aria-controls="cup-collapse-errors" title="<?php esc_attr_e( 'Show errors', 'cloud-uploads' ); ?>">
									<span class="dashicons dashicons-arrow-down-alt2"></span>
								</a>
							</div>
							<div class="collapse" id="cup-collapse-errors">
								<div class="card card-body">
									<ul class="list-group list-group-flush text-left">
										<?php
										$error_list = $wpdb->get_results( "SELECT file, size FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 0 AND errors >= 3" );
										if ( count( $error_list ) ) {
											foreach ( $error_list as $error ) { ?>
												<li class="list-group-item list-group-item-warning"><?php echo esc_html( $error->file ); ?> - <?php echo size_format( $error->size, 2 ); ?></li>
												<?php
											}
										} else { ?>
											<li class="list-group-item list-group-item-warning">
												<div class="spinner-grow spinner-grow-sm" role="status">
													<span class="sr-only">Loading...</span>
												</div>
											</li>
										<?php } ?>
									</ul>
								</div>
							</div>
							<p class="text-warning cup-enable-errors" <?php echo ( $error_count ) ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Note: If any of these files are referenced in posts or pages they might show as missing after enabling. You can retry syncing them, or ignore and enable anyway.', 'cloud-uploads' ); ?>
								<a href="<?php echo esc_url( $this->api_url( '/support/' ) ); ?>"><?php esc_html_e( 'Need help?', 'cloud-uploads' ); ?></a></p>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center">
							<button class="btn text-nowrap btn-primary btn-lg mr-2 cup-enable-errors" id="cup-resync-button" data-toggle="modal" <?php echo ( $error_count ) ? '' : 'style="display:none;"'; ?>><span
									class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Retry Sync', 'cloud-uploads' ); ?></button>
							<button class="btn text-nowrap btn-info btn-lg" id="cup-enable-button"><span class="dashicons dashicons-cloud-saved"></span><?php esc_html_e( 'Enable', 'cloud-uploads' ); ?></button>
							<div class="spinner-grow text-muted text-hide d-block mx-auto" id="cup-enable-spinner" role="status"><span class="sr-only"><?php esc_html_e( 'Enabling...', 'cup' ); ?></span></div>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center">
							<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-5.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
