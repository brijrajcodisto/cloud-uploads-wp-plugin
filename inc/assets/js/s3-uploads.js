jQuery(document).ready(function ($) {
	$('[data-toggle="tooltip"]').tooltip();
	$('.color-field').wpColorPicker();

	var s3upStopLoop = false;
	var s3upProcessingLoop = false;
	var s3upLoopErrors = 0;
	var s3upAjaxCall = false;

	//show a confirmation warning if leaving page during a bulk action
	$(window).on("unload", function () {
		if (s3upProcessingLoop) {
			return s3up_data.strings.leave_confirmation;
		}
	});

	//show an error at top of main settings page
	var showError = function (error_message) {
		if(error_message) {
			$('#s3up-error').text(error_message.substr(0, 200)).show();
			$('html, body').animate({scrollTop: 0}, 1000);
		} else {
			$('#s3up-error').text('Some error occurred').show();
			$('html, body').animate({scrollTop: 0}, 1000);
		}
	};

	var buildFilelist = function (remaining_dirs, nonce = '') {
		if (s3upStopLoop) {
			s3upStopLoop = false;
			s3upProcessingLoop = false;
			return false;
		}
		s3upProcessingLoop = true;

		var data = {remaining_dirs: remaining_dirs};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = s3up_data.nonce.scan;
		}
		$.post(
			ajaxurl + '?action=s3-uploads-filelist',
			data,
			function (json) {
				if (json.success) {
					console.log('response is ', json);
					$('#s3up-scan-storage').text(json.data.local_size);
					$('#s3up-scan-files').text(json.data.local_files);
					$('#s3up-scan-progress').show();
					if (!json.data.is_done) {
						buildFilelist(
							json.data.remaining_dirs,
							json.data.nonce
						);
					} else {
						s3upProcessingLoop = false;
						location.reload();
						return true;
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			showError(s3up_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	};

	var fetchRemoteFilelist = function (next_token, nonce = '') {
		if (s3upStopLoop) {
			s3upStopLoop = false;
			s3upProcessingLoop = false;
			return false;
		}
		s3upProcessingLoop = true;

		var data = {next_token: next_token};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = s3up_data.nonce.scan;
		}
		$.post(
			ajaxurl + '?action=infinite-uploads-remote-filelist',
			data,
			function (json) {
				if (json.success) {
					$('#s3up-scan-remote-storage').text(
						json.data.cloud_size
					);
					$('#s3up-scan-remote-files').text(json.data.cloud_files);
					$('#s3up-scan-remote-progress').show();
					if (!json.data.is_done) {
						fetchRemoteFilelist(
							json.data.next_token,
							json.data.nonce
						);
					} else {
						if ('upload' === window.s3upNextStep) {
							//update values in next modal
							$('#s3up-progress-size').text(
								json.data.remaining_size
							);
							$('#s3up-progress-files').text(
								json.data.remaining_files
							);
							if ('0' == json.data.remaining_files) {
								$('#s3up-upload-progress').hide();
							} else {
								$('#s3up-upload-progress').show();
							}
							$('#s3up-sync-progress-bar')
								.css('width', json.data.pcnt_complete + '%')
								.attr(
									'aria-valuenow',
									json.data.pcnt_complete
								)
								.text(json.data.pcnt_complete + '%');

							$('#s3up-sync-button').attr(
								'data-target',
								'#upload-modal'
							);
							$('.modal').modal('hide');
							$('#upload-modal').modal('show');
						} else if ('download' === window.s3upNextStep) {
							$('.modal').modal('hide');
							$('#download-modal').modal('show');
						} else {
							location.reload();
						}
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			showError(s3up_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	};

	var syncFilelist = function (nonce = '') {
		if (s3upStopLoop) {
			s3upStopLoop = false;
			s3upProcessingLoop = false;
			return false;
		}
		s3upProcessingLoop = true;

		var data = {};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = s3up_data.nonce.sync;
		}
		s3upAjaxCall = $.post(
			ajaxurl + '?action=infinite-uploads-sync',
			data,
			function (json) {
				s3upLoopErrors = 0;
				if (json.success) {
					//$('.s3up-progress-pcnt').text(json.data.pcnt_complete);
					$('#s3up-progress-size').text(json.data.remaining_size);
					$('#s3up-progress-files').text(
						json.data.remaining_files
					);
					$('#s3up-upload-progress').show();
					$('#s3up-sync-progress-bar')
						.css('width', json.data.pcnt_complete + '%')
						.attr('aria-valuenow', json.data.pcnt_complete)
						.text(json.data.pcnt_complete + '%');
					if (!json.data.is_done) {
						data.nonce = json.data.nonce; //save for future errors
						syncFilelist(json.data.nonce);
					} else {
						s3upStopLoop = true;
						$('#s3up-upload-progress').hide();
						//update values in next modal
						$('#s3up-enable-errors span').text(
							json.data.permanent_errors
						);
						if (json.data.permanent_errors) {
							$('.s3up-enable-errors').show();
						}
						$('#s3up-sync-button').attr(
							'data-target',
							'#enable-modal'
						);
						$('.modal').modal('hide');
						$('#enable-modal').modal('show');
					}
					if (
						Array.isArray(json.data.errors) &&
						json.data.errors.length
					) {
						$.each(json.data.errors, function (i, value) {
							$('#s3up-sync-errors ul').append(
								'<li><span class="dashicons dashicons-warning"></span> ' +
								value +
								'</li>'
							);
						});
						$('#s3up-sync-errors').show();
						var scroll = $('#s3up-sync-errors')[0].scrollHeight;
						$('#s3up-sync-errors').animate(
							{scrollTop: scroll},
							5000
						);
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			//if we get an error like 504 try up to 6 times with an exponential backoff to let the server cool down before giving up.
			s3upLoopErrors++;
			if (s3upLoopErrors > 6) {
				showError(s3up_data.strings.ajax_error);
				$('.modal').modal('hide');
				s3upLoopErrors = 0;
				s3upProcessingLoop = false;
			} else {
				var exponentialBackoff = Math.floor(
					Math.pow(s3upLoopErrors, 2.5) * 1000
				); //max 90s
				console.log(
					'Server error. Waiting ' +
					exponentialBackoff +
					'ms before retrying'
				);
				setTimeout(function () {
					syncFilelist(data.nonce);
				}, exponentialBackoff);
			}
		});
	};

	var getSyncStatus = function () {
		if (!s3upProcessingLoop) {
			return false;
		}

		$.get(
			ajaxurl + '?action=infinite-uploads-status',
			function (json) {
				if (json.success) {
					$('#s3up-progress-size').text(json.data.remaining_size);
					$('#s3up-progress-files').text(
						json.data.remaining_files
					);
					$('#s3up-upload-progress').show();
					$('#s3up-sync-progress-bar')
						.css('width', json.data.pcnt_complete + '%')
						.attr('aria-valuenow', json.data.pcnt_complete)
						.text(json.data.pcnt_complete + '%');
				} else {
					showError(json.data);
				}
			},
			'json'
		)
			.fail(function () {
				showError(s3up_data.strings.ajax_error);
			})
			.always(function () {
				setTimeout(function () {
					getSyncStatus();
				}, 15000);
			});
	};

	var deleteFiles = function () {
		if (s3upStopLoop) {
			s3upStopLoop = false;
			return false;
		}

		$.post(
			ajaxurl + '?action=infinite-uploads-delete',
			{nonce: s3up_data.nonce.delete},
			function (json) {
				if (json.success) {
					//$('.s3up-progress-pcnt').text(json.data.pcnt_complete);
					$('#s3up-delete-size').text(json.data.deletable_size);
					$('#s3up-delete-files').text(json.data.deletable_files);
					if (!json.data.is_done) {
						deleteFiles();
					} else {
						location.reload();
						return true;
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			showError(s3up_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	};

	var downloadFiles = function (nonce = '') {
		if (s3upStopLoop) {
			s3upStopLoop = false;
			s3upProcessingLoop = false;
			return false;
		}
		s3upProcessingLoop = true;

		var data = {};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = s3up_data.nonce.download;
		}
		$.post(
			ajaxurl + '?action=infinite-uploads-download',
			data,
			function (json) {
				s3upLoopErrors = 0;
				if (json.success) {
					//$('.s3up-progress-pcnt').text(json.data.pcnt_complete);
					$('#s3up-download-size').text(json.data.deleted_size);
					$('#s3up-download-files').text(json.data.deleted_files);
					$('#s3up-download-progress').show();
					$('#s3up-download-progress-bar')
						.css('width', json.data.pcnt_downloaded + '%')
						.attr('aria-valuenow', json.data.pcnt_downloaded)
						.text(json.data.pcnt_downloaded + '%');
					if (!json.data.is_done) {
						data.nonce = json.data.nonce; //save for future errors
						downloadFiles(json.data.nonce);
					} else {
						s3upProcessingLoop = false;
						location.reload();
						return true;
					}
					if (
						Array.isArray(json.data.errors) &&
						json.data.errors.length
					) {
						$.each(json.data.errors, function (i, value) {
							$('#s3up-download-errors ul').append(
								'<li><span class="dashicons dashicons-warning"></span> ' +
								value +
								'</li>'
							);
						});
						$('#s3up-download-errors').show();
						var scroll = $('#s3up-download-errors')[0]
							.scrollHeight;
						$('#s3up-download-errors').animate(
							{scrollTop: scroll},
							5000
						);
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			//if we get an error like 504 try up to 6 times before giving up.
			s3upLoopErrors++;
			if (s3upLoopErrors > 6) {
				showError(s3up_data.strings.ajax_error);
				$('.modal').modal('hide');
				s3upLoopErrors = 0;
				s3upProcessingLoop = false;
			} else {
				var exponentialBackoff = Math.floor(
					Math.pow(s3upLoopErrors, 2.5) * 1000
				); //max 90s
				console.log(
					'Server error. Waiting ' +
					exponentialBackoff +
					'ms before retrying'
				);
				setTimeout(function () {
					downloadFiles(data.nonce);
				}, exponentialBackoff);
			}
		});
	};

	//Scan
	$('#scan-modal')
		.on('show.bs.modal', function () {
			$('#s3up-error').hide();
			s3upStopLoop = false;
			buildFilelist([]);
		})
		.on('hide.bs.modal', function () {
			s3upStopLoop = true;
			s3upProcessingLoop = false;
		});

	//Compare to live
	$('#scan-remote-modal')
		.on('show.bs.modal', function (e) {
			$('#s3up-error').hide();
			s3upStopLoop = false;
			var button = $(e.relatedTarget); // Button that triggered the modal
			window.s3upNextStep = button.data('next'); // Extract info from data-* attributes
			fetchRemoteFilelist(null);
		})
		.on('hide.bs.modal', function () {
			s3upStopLoop = true;
			s3upProcessingLoop = false;
		});

	//Sync
	$('#upload-modal')
		.on('show.bs.modal', function () {
			$('.s3up-enable-errors').hide(); //hide errors on enable modal
			$('#s3up-collapse-errors').collapse('hide');
			$('#s3up-error').hide();
			$('#s3up-sync-errors').hide();
			$('#s3up-sync-errors ul').empty();
			s3upStopLoop = false;
			syncFilelist();
			setTimeout(function () {
				getSyncStatus();
			}, 15000);
		})
		.on('shown.bs.modal', function () {
			$('#scan-remote-modal').modal('hide');
		})
		.on('hide.bs.modal', function () {
			s3upStopLoop = true;
			s3upProcessingLoop = false;
			s3upAjaxCall.abort();
		});

	//Make sure upload modal closes
	$('#enable-modal')
		.on('shown.bs.modal', function () {
			$('#upload-modal').modal('hide');
		})
		.on('hidden.bs.modal', function () {
			$('#s3up-enable-spinner').addClass('text-hide');
			$('#s3up-enable-button').show();
		});

	$('#s3up-collapse-errors').on('show.bs.collapse', function () {
		// load up list of errors via ajax
		$.get(
			ajaxurl + '?action=infinite-uploads-sync-errors',
			function (json) {
				if (json.success) {
					$('#s3up-collapse-errors .list-group').html(json.data);
				}
			},
			'json'
		);
	});

	$('#s3up-resync-button').on('click', function (e) {
		$('.s3up-enable-errors').hide(); //hide errors on enable modal
		$('#s3up-collapse-errors').collapse('hide');
		$('#s3up-enable-button').hide();
		$('#s3up-enable-spinner').removeClass('text-hide');
		$.post(
			ajaxurl + '?action=infinite-uploads-reset-errors',
			{foo: 'bar'},
			function (json) {
				if (json.success) {
					$('.modal').modal('hide');
					$('#upload-modal').modal('show');
					return true;
				}
			},
			'json'
		).fail(function () {
			showError(s3up_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	});

	//Download
	$('#download-modal')
		.on('show.bs.modal', function () {
			$('#s3up-error').hide();
			$('#s3up-download-errors').hide();
			$('#s3up-download-errors ul').empty();
			s3upStopLoop = false;
			downloadFiles();
		})
		.on('hide.bs.modal', function () {
			s3upStopLoop = true;
			s3upProcessingLoop = false;
		});

	//Delete
	$('#delete-modal')
		.on('show.bs.modal', function () {
			$('#s3up-error').hide();
			s3upStopLoop = false;
			$('#s3up-delete-local-button').show();
			$('#s3up-delete-local-spinner').hide();
		})
		.on('hide.bs.modal', function () {
			s3upStopLoop = true;
		});

	//Delete local files
	$('#s3up-delete-local-button').on('click', function () {
		$(this).hide();
		$('#s3up-delete-local-spinner').show();
		deleteFiles();
	});

	//Enable infinite uploads
	$('#s3up-enable-button').on('click', function () {
		$('.s3up-enable-errors').hide(); //hide errors on enable modal
		$('#s3up-collapse-errors').collapse('hide');
		$('#s3up-enable-button').hide();
		$('#s3up-enable-spinner').removeClass('text-hide');
		$.post(
			ajaxurl + '?action=infinite-uploads-toggle',
			{enabled: true, nonce: s3up_data.nonce.toggle},
			function (json) {
				if (json.success) {
					location.reload();
					return true;
				}
			},
			'json'
		).fail(function () {
			showError(s3up_data.strings.ajax_error);
			$('#s3up-enable-spinner').addClass('text-hide');
			$('#s3up-enable-button').show();
			$('.modal').modal('hide');
		});
	});

	//Enable video cloud
	$('#s3up-enable-video-button').on('click', function () {
		$('#s3up-enable-video-button').hide();
		$('#s3up-enable-video-spinner').removeClass('d-none').addClass('d-block');
		$.post(
			ajaxurl + '?action=infinite-uploads-video-activate',
			{nonce: s3up_data.nonce.video},
			function (json) {
				if (json.success) {
					location.reload();
					return true;
				} else {
					$('#s3up-enable-video-spinner').addClass('d-none').removeClass('d-block');
					$('#s3up-enable-video-button').show();
				}
			},
			'json'
		).fail(function () {
			showError(s3up_data.strings.ajax_error);
			$('#s3up-enable-video-spinner').addClass('d-none').removeClass('d-block');
			$('#s3up-enable-video-button').show();
		});
	});

	//refresh api data
	$('.s3up-refresh-icon .dashicons').on('click', function () {
		$(this).hide();
		$('.s3up-refresh-icon .spinner-grow').removeClass('text-hide');
		window.location = $(this).attr('data-target');
	});

	//Charts
	var bandwidthFormat = function (bytes) {
		if (bytes < 1024) {
			return bytes + ' B';
		} else if (bytes < 1024 * 1024) {
			return Math.round(bytes / 1024) + ' KB';
		} else if (bytes < 1024 * 1024 * 1024) {
			return Math.round((bytes / 1024 / 1024) * 10) / 10 + ' MB';
		} else {
			return (
				Math.round((bytes / 1024 / 1024 / 1024) * 100) / 100 + ' GB'
			);
		}
	};

	var sizelabel = function (tooltipItem, data) {
		var label = ' ' + data.labels[tooltipItem.index] || '';
		return label;
	};

	window.onload = function () {
		var pie1 = document.getElementById('s3up-local-pie');
		if (pie1) {
			var config_local = {
				type: 'pie',
				data: s3up_data.local_types,
				options: {
					responsive: true,
					legend: false,
					tooltips: {
						callbacks: {
							label: sizelabel,
						},
						backgroundColor: '#F1F1F1',
						bodyFontColor: '#2A2A2A',
					},
					title: {
						display: true,
						position: 'bottom',
						fontSize: 18,
						fontStyle: 'normal',
						text: s3up_data.local_types.total,
					},
				},
			};

			var ctx = pie1.getContext('2d');
			window.myPieLocal = new Chart(ctx, config_local);
		}

		var pie2 = document.getElementById('s3up-cloud-pie');
		if (pie2) {
			var config_cloud = {
				type: 'pie',
				data: s3up_data.cloud_types,
				options: {
					responsive: true,
					legend: false,
					tooltips: {
						callbacks: {
							label: sizelabel,
						},
						backgroundColor: '#F1F1F1',
						bodyFontColor: '#2A2A2A',
					},
					title: {
						display: true,
						position: 'bottom',
						fontSize: 18,
						fontStyle: 'normal',
						text: s3up_data.cloud_types.total,
					},
				},
			};

			var ctx = pie2.getContext('2d');
			window.myPieCloud = new Chart(ctx, config_cloud);
		}
	};
});
