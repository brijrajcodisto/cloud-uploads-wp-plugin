<span class="m-0 p-0 text-muted s3up-enabled-status">
	<?php esc_html_e( 'Status', 's3-uploads' ); ?>
	<?php if ( isset( $api_data->site ) && ! $api_data->site->upload_writeable ) { ?>
		<span class="dashicons dashicons-cloud text-warning" data-toggle="tooltip" title="<?php esc_attr_e( 'There is a problem with your S3 Uploads account', 's3-uploads' ); ?>"></span>
	<?php } elseif (  get_site_option( 's3up_enabled' ) ) { ?>
		<span class="dashicons dashicons-cloud-saved" data-toggle="tooltip" title="<?php esc_attr_e( 'Enabled - new uploads are moved to the cloud', 's3-uploads' ); ?>"></span>
	<?php } elseif ( $this->api->has_token() ) { ?>
		<span class="dashicons dashicons-cloud-upload text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Disabled - waiting to sync media to the cloud', 's3-uploads' ); ?>"></span>
	<?php } else { ?>
		<span class="dashicons dashicons-cloud text-muted" data-toggle="tooltip" title="<?php esc_attr_e( 'Disabled - waiting to connect', 's3-uploads' ); ?>"></span>
	<?php } ?>
</span>
