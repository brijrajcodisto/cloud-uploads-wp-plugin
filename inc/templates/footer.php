<div id="s3up-footer" class="container mt-5">
	<div class="row">
		<div class="col-sm text-center text-muted">
			<strong><?php esc_html_e( "The Cloud by S3 Uploads", 's3-uploads' ); ?></strong>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a href="<?php echo esc_url( S3_Uploads_Admin::api_url( '/support/?utm_source=s3up_plugin&utm_medium=plugin&utm_campaign=s3up_plugin&utm_content=footer&utm_term=support' ) ); ?>"
			   class="text-muted text-decoration-none"><?php esc_html_e( "Support", 's3-uploads' ); ?></a> |
			<a href="<?php echo esc_url( S3_Uploads_Admin::get_instance()->api_url( '/terms-of-service/?utm_source=s3up_plugin&utm_medium=plugin&utm_campaign=s3up_plugin&utm_content=footer&utm_term=terms' ) ); ?>"
			   class="text-muted text-decoration-none"><?php esc_html_e( "Terms of Service", 's3-uploads' ); ?></a> |
			<a href="<?php echo esc_url( S3_Uploads_Admin::get_instance()->api_url( '/privacy/?utm_source=s3up_plugin&utm_medium=plugin&utm_campaign=s3up_plugin&utm_content=footer&utm_term=privacy' ) ); ?>"
			   class="text-muted text-decoration-none"><?php esc_html_e( "Privacy Policy", 's3-uploads' ); ?></a>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a href="https://twitter.com/s3uploads" class="text-muted text-decoration-none" data-toggle="tooltip" title="<?php esc_attr_e( 'Twitter', 's3-uploads' ); ?>"><span class="dashicons dashicons-twitter"></span></a>
			<a href="https://www.facebook.com/s3uploads/" class="text-muted text-decoration-none" data-toggle="tooltip" title="<?php esc_attr_e( 'Facebook', 's3-uploads' ); ?>"><span class="dashicons dashicons-facebook-alt"></span></a>
		</div>
	</div>
</div>
