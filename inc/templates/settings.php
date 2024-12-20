<div class="card mt-0">
	<div class="card-header h5">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php esc_html_e( 'Account & Settings', 'cloud-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Includes usage data for all connected sites', 'cloud-uploads' ); ?>"></span></h5>
			<span class="m-0 p-0 text-muted iup-refresh-icon">
				<div class="spinner-grow spinner-grow-sm text-secondary text-hide" role="status">
				  <span class="sr-only">Refreshing...</span>
				</div>
				<span class="dashicons dashicons-update-alt mr-1" role="button" data-target="<?php echo esc_url( $this->settings_url( [ 'refresh' => 1 ] ) ); ?>" data-toggle="tooltip" title="<?php esc_attr_e( 'Refresh account data', 'cloud-uploads' ); ?>"></span>
				<small><?php printf( esc_html__( 'Updated %s ago', 'cloud-uploads' ), human_time_diff( $api_data->refreshed ) ); ?></small>
			</span>
		</div>
	</div>
	<div class="card-body p-md-5">
		<div class="row justify-content-center mb-5">
			<div class="col-md-6 col-sm-12">
				<h5><?php esc_html_e( 'Cloud Uploads Plan', 'cloud-uploads' ); ?></h5>
				<p class="lead"><?php esc_html_e( 'Your current Cloud Uploads plan and storage.', 'cloud-uploads' ); ?></p>
			</div>
			<div class="col-md-6 col-sm-12">
				<div class="row">
					<div class="col"><?php esc_html_e( 'Used / Available', 'cloud-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'cloud-uploads' ); ?>"></span></div>
					<div class="col text-right"><?php esc_html_e( 'Need more?', 'cloud-uploads' ); ?> <a href="<?php echo esc_url( $this->api_url( '/account/billing/' ) ); ?>" class="text-warning"><?php esc_html_e( 'Switch to a new plan.', 'cloud-uploads' ); ?></a></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0 d-none d-md-block"><?php printf( esc_html__( '%s / %s', 'cloud-uploads' ), $this->size_format_zero( $cloud_total_size, 2 ), esc_html( $api_data->plan->label ) ); ?></p>
						<p class="h6 ml-2 mb-0 d-md-none"><?php printf( esc_html__( '%s / %s', 'cloud-uploads' ), $this->size_format_zero( $cloud_total_size, 2 ), esc_html( $api_data->plan->label ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col-md-6 col-sm-12">
				<h5><?php esc_html_e( 'CDN Bandwidth', 'cloud-uploads' ); ?></h5>
				<p class="lead"><?php esc_html_e( 'Cloud Uploads includes allotted bandwidth for CDN delivery of your files.', 'cloud-uploads' ); ?></p>
			</div>
			<div class="col-md-6 col-sm-12">
				<div class="row">
					<div class="col"><?php esc_html_e( 'Used / Available', 'cloud-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Recalculated every 24 hours', 'cloud-uploads' ); ?>"></span></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php printf( esc_html__( '%s / %s', 'cloud-uploads' ), $this->size_format_zero( $api_data->stats->cloud->bandwidth, 2 ), $this->size_format_zero( $api_data->plan->bandwidth_limit ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col-md-6 col-sm-12">
				<h5><?php esc_html_e( 'CDN URL', 'cloud-uploads' ); ?></h5>
				<p class="lead"><?php esc_html_e( 'Your uploads are served from this CDN url via 45+ edge locations around the world.', 'cloud-uploads' ); ?></p>
			</div>
			<div class="col-md-6 col-sm-12">
				<div class="row">
					<div class="col"><?php esc_html_e( 'Current CDN URL', 'cloud-uploads' ); ?></div>
					<?php if ( $api_data->site->cname == $api_data->site->cdn_url ) { ?>
						<div class="col text-right"><a href="<?php echo esc_url( $this->api_url( '/account/sites/?site=' . $this->api->get_site_id() ) ); ?>#custom-cdn-domain" class="text-warning"><?php esc_html_e( 'Use your own custom domain!', 'cloud-uploads' ); ?></a></div>
					<?php } ?>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0 d-none d-md-block"><?php echo esc_html( $api_data->site->cdn_url ); ?></p>
						<p class="h6 ml-2 mb-0 d-md-none"><?php echo esc_html( $api_data->site->cdn_url ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col-md-6 col-sm-12">
				<h5><?php esc_html_e( 'Storage Region', 'cloud-uploads' ); ?></h5>
				<p class="lead"><?php esc_html_e( 'The location of our servers storing your uploads.', 'cloud-uploads' ); ?></p>
			</div>
			<div class="col-md-6 col-sm-12">
				<div class="row">
					<div class="col"><?php esc_html_e( 'Region', 'cloud-uploads' ); ?> <span class="dashicons dashicons-info text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Region can only be selected when first connecting your site.', 'cloud-uploads' ); ?>"></span></div>
				</div>
				<div class="row">
					<div class="col badge badge-pill badge-light text-left p-3">
						<p class="h5 ml-2 mb-0"><?php //echo esc_html( $region_labels[ $api_data->site->region ] ); ?></p></div>
				</div>
			</div>
		</div>
		<?php if ( cloud_uploads_enabled() && (bool) $stats['deletable_files'] ) { ?>
			<div class="row justify-content-center iup-settings-row-delete">
				<div class="col-md-6 col-sm-12">
					<h5><?php esc_html_e( 'Free Up Local Storage', 'cloud-uploads' ); ?></h5>
					<p class="lead"><?php esc_html_e( 'There are unused local copies of files already synced to the cloud. You can optionally delete these to free up local storage space.', 'cloud-uploads' ); ?></p>
				</div>
				<div class="col-md-6 col-sm-12 mt-4">
					<div class="row text-center mb-3">
						<div class="col"><?php esc_html_e( 'This saves space and improves server performance.', 'cloud-uploads' ); ?></div>
					</div>
					<div class="row justify-content-center">
						<div class="col-xl-5 col-lg-6 col-md-7 text-center">
							<button class="btn text-nowrap btn-info btn-lg btn-block" data-toggle="modal" data-target="#delete-modal"><?php esc_html_e( 'Delete', 'cloud-uploads' ); ?></button>
						</div>
					</div>
					<div class="row justify-content-center">
						<div class="col text-center">
							<p><strong><?php printf( esc_html__( '%s / %s deletable files', 'cloud-uploads' ), $stats['deletable_size'], $stats['deletable_files'] ); ?></strong></p>
						</div>
					</div>
				</div>
			</div>
		<?php } ?>
		<div class="row justify-content-center" id="iup-disconnect">
			<div class="col-md-6 col-sm-12">
				<h5><?php esc_html_e( 'Download & Disconnect', 'cloud-uploads' ); ?></h5>
				<p class="lead"><?php printf( __( 'Download your media files and disconnect from our cloud. To cancel or manage your storage plan please visit <a href="%s" class="text-warning">account management</a>.', 'cloud-uploads' ), esc_url( $this->api_url( '/account/billing/' ) ) ); ?></p>
			</div>
			<div class="col-md-6 col-sm-12 mt-4">
				<div class="row text-center mb-3">
					<div class="col"><?php esc_html_e( 'We will download your files back to the uploads directory before disconnecting to prevent broken media on your site.', 'cloud-uploads' ); ?></div>
				</div>
				<div class="row justify-content-center">
					<div class="col-xl-5 col-lg-6 col-md-7 text-center">
						<button class="btn text-nowrap btn-info btn-lg btn-block" data-toggle="modal" data-target="#scan-remote-modal" data-next="download"><?php esc_html_e( 'Disconnect', 'cloud-uploads' ); ?></button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
