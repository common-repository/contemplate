jQuery(document).ready(function ($) {

	var spinner = $('.pcct-spinner .spinner');

	$(document).on('click', 'input.pcct-shortcode', function (event) {
		this.select();
	});

	$(document).on('click', '#add-ct', function (event) {
		spinner.addClass('is-active');
		$('#pcct-submit, #add-ct').attr("disabled", true);

		data = {
			action             : 'pcct_add_control',
			pcct_ajax_nonce_add: pcct_vars.pcct_nonce
		};

		$.post(ajaxurl, data, function (response) {

			console.log(response);

			$('#last-tr').before(response);
			//$('#pcct-ct-table > tbody:last').append(response);

			$('#pcct-header-tag').after('<div style="display:none;" id="setting-error-settings_updated" class="updated settings-error"><p><strong>New content template added!</strong></p></div>');
			$('#setting-error-settings_updated').slideDown();
			$('#setting-error-settings_updated').delay(2000).slideUp();

			$('#pcct-empty-ct').hide();
			$('#pcct-empty-ct-submit').show();
			spinner.removeClass('is-active');
			$('#pcct-submit, #add-ct').attr("disabled", false);
		});

		return false; // make sure the normal submit behaviour is suppressed
	});

	$(document).on('click', '.pcct-del-icon', function (event) {
		var ct_id = $(this).attr('id');

		var conf = confirm("Delete the [" + ct_id + "] shortcode? This action cannot be undone!");
		if (conf == true) {
			spinner.addClass('is-active');

			$('#pcct-submit').attr("disabled", true);
			$('#add-ct').attr("disabled", true);

			data = {
				action             : 'pcct_delete_control',
				pcct_ajax_nonce_del: pcct_vars.pcct_nonce,
				contemplate_id     : ct_id
			};

			$.post(ajaxurl, data, function (response) {

				response = response.trim(); // trim whitespace from response

				$('#pcct-ct-row-' + response).fadeOut(500, function () {
					$(this).remove();
					var rowCount = $('#pcct-ct-table tr').delay(3000).length;
					if (rowCount == 0) {
						$('#pcct-empty-ct-submit').hide();
						$('#pcct-empty-ct').show();
					}
				});

				$('#pcct-header-tag').after('<div style="display:none;" id="setting-error-settings_deleted" class="error settings-error"><p><strong>Content template deleted!</strong></p></div>');
				$('#setting-error-settings_deleted').slideDown();
				$('#setting-error-settings_deleted').delay(2000).slideUp();

				spinner.removeClass('is-active');
				$('#pcct-submit, #add-ct').attr("disabled", false);
			});
		}
	});
});