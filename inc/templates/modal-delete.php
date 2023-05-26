<div class="modal fade" id="delete-modal" tabindex="-1" role="dialog" aria-labelledby="delete-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="delete-modal-label"><?php esc_html_e( 'Free Up Local Storage', 'cloud-uploads' ); ?></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="container-fluid">
					<div class="row justify-content-center mb-4 mt-3">
						<div class="col text-center">
							<h4><?php esc_html_e( 'Delete Local Files', 'cloud-uploads' ); ?></h4>
							<p class="lead"><?php esc_html_e( "This will delete the duplicate copies of your files stored in your local media library. This saves space and improves server performance but will require downloading these files back to the uploads directory before disconnecting to prevent broken media on your site.", 'cloud-uploads' ); ?></p>
							<p><?php esc_html_e( 'If your host provides access to WP CLI, you can also execute the command:', 'cloud-uploads' ); ?> <code>wp cloud-uploads delete</code></p>
						</div>
					</div>
					<div class="row justify-content-center mb-5">
						<div class="col text-center text-muted">
							<div id="cup-delete-local-spinner" class="spinner-border spinner-border-sm" role="status" style="display: none;">
								<span class="sr-only">Deleting...</span>
							</div>
							<span class="h5"><?php printf( __( '<span id="cup-delete-size">%s</span> / <span id="cup-delete-files">%s</span> Deletable Files', 'cloud-uploads' ), $stats['deletable_size'], $stats['deletable_files'] ); ?></span>
						</div>
					</div>
					<div class="row justify-content-center mb-4">
						<div class="col text-center">
							<button class="btn text-nowrap btn-info btn-lg" id="cup-delete-local-button"><?php esc_html_e( 'Start Delete', 'cloud-uploads' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
