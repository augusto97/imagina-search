/**
 * Woo Smart Search - Admin JS
 *
 * Handles admin panel interactions: settings save, connection test,
 * sync operations, and log management.
 */
(function ($) {
	'use strict';

	var l10n = wssAdmin.i18n || {};

	$(document).ready(function () {
		initSaveSettings();
		initTestConnection();
		initSyncActions();
		initLogs();
		initIndexStats();
	});

	/**
	 * Settings save handler for all forms.
	 */
	function initSaveSettings() {
		$('.wss-form').on('submit', function (e) {
			e.preventDefault();

			var $form = $(this);
			var $msg = $form.find('.wss-status-message');
			var $btn = $form.find('.wss-save-settings');

			$btn.prop('disabled', true);
			$msg.text(l10n.saving).removeClass('wss-error');

			$.ajax({
				url: wssAdmin.ajaxUrl,
				method: 'POST',
				data: $form.serialize() + '&action=wss_save_settings&nonce=' + wssAdmin.nonce,
				success: function (response) {
					if (response.success) {
						$msg.text(l10n.saved);
					} else {
						$msg.text(response.data.message || l10n.error).addClass('wss-error');
					}
				},
				error: function () {
					$msg.text(l10n.error).addClass('wss-error');
				},
				complete: function () {
					$btn.prop('disabled', false);
					setTimeout(function () { $msg.text(''); }, 3000);
				}
			});
		});
	}

	/**
	 * Test connection button handler.
	 */
	function initTestConnection() {
		$('#wss-test-connection').on('click', function () {
			var $btn = $(this);
			var $result = $('#wss-connection-result');

			$btn.prop('disabled', true);
			$result.show().removeClass('wss-notice-success wss-notice-error').text(l10n.testingConnection);

			$.ajax({
				url: wssAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'wss_test_connection',
					nonce: wssAdmin.nonce,
					engine: $('#wss-engine').val(),
					host: $('#wss-host').val(),
					port: $('#wss-port').val(),
					protocol: $('#wss-protocol').val(),
					api_key: $('#wss-api-key').val()
				},
				success: function (response) {
					if (response.success) {
						$result.addClass('wss-notice-success')
							.text(l10n.connectionSuccess + ' (v' + (response.data.version || '?') + ')');
					} else {
						$result.addClass('wss-notice-error')
							.text(l10n.connectionFailed + ' ' + (response.data.message || ''));
					}
				},
				error: function () {
					$result.addClass('wss-notice-error').text(l10n.connectionFailed + ' ' + l10n.error);
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		});
	}

	/**
	 * Sync action buttons.
	 */
	function initSyncActions() {
		var pollTimer = null;

		$('#wss-full-sync').on('click', function () {
			if (!confirm(l10n.confirmFullSync)) return;

			var $btn = $(this);
			var $msg = $('#wss-sync-message');

			$btn.prop('disabled', true);
			$msg.text('').removeClass('wss-error');

			$.ajax({
				url: wssAdmin.ajaxUrl,
				method: 'POST',
				data: { action: 'wss_full_sync', nonce: wssAdmin.nonce },
				success: function (response) {
					if (response.success) {
						$msg.text(l10n.syncStarted);
						$('#wss-sync-progress').show();
						startPolling();
					} else {
						$msg.text(l10n.syncFailed + ' ' + (response.data.message || '')).addClass('wss-error');
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					$msg.text(l10n.error).addClass('wss-error');
					$btn.prop('disabled', false);
				}
			});
		});

		$('#wss-clear-index').on('click', function () {
			if (!confirm(l10n.confirmClearIndex)) return;
			if (!confirm(l10n.confirmClearIndex)) return; // Double confirm.

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: wssAdmin.ajaxUrl,
				method: 'POST',
				data: { action: 'wss_clear_index', nonce: wssAdmin.nonce },
				success: function (response) {
					$('#wss-sync-message')
						.text(response.success ? response.data.message : (response.data.message || l10n.error))
						.toggleClass('wss-error', !response.success);
					refreshStats();
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		});

		function startPolling() {
			pollTimer = setInterval(function () {
				$.ajax({
					url: wssAdmin.ajaxUrl,
					method: 'POST',
					data: { action: 'wss_sync_progress', nonce: wssAdmin.nonce },
					success: function (response) {
						if (!response.success) return;
						var data = response.data;
						var pct = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;

						$('.wss-progress-bar-fill').css('width', pct + '%');
						$('.wss-progress-text').text(pct + '% (' + data.processed + '/' + data.total + ')');

						if (data.status === 'completed' || data.status === 'idle') {
							clearInterval(pollTimer);
							$('#wss-full-sync').prop('disabled', false);
							$('#wss-sync-message').text(l10n.syncCompleted);
							refreshStats();
						}
					}
				});
			}, 3000);
		}
	}

	/**
	 * Log management.
	 */
	function initLogs() {
		var currentPage = 1;

		function loadLogs(page) {
			currentPage = page || 1;
			$.ajax({
				url: wssAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'wss_get_logs',
					nonce: wssAdmin.nonce,
					log_type: $('#wss-log-type-filter').val(),
					page: currentPage
				},
				success: function (response) {
					if (!response.success) return;
					var data = response.data;
					var $body = $('#wss-logs-body');
					$body.empty();

					if (!data.logs || data.logs.length === 0) {
						$body.append('<tr><td colspan="3">No logs found.</td></tr>');
						return;
					}

					data.logs.forEach(function (log) {
						var typeClass = 'wss-log-type-' + log.type;
						$body.append(
							'<tr>' +
							'<td><span class="wss-log-type ' + typeClass + '">' + log.type + '</span></td>' +
							'<td>' + $('<span>').text(log.message).html() + '</td>' +
							'<td>' + log.created_at + '</td>' +
							'</tr>'
						);
					});

					// Pagination.
					var $pag = $('#wss-logs-pagination .tablenav-pages');
					$pag.empty();
					if (data.pages > 1) {
						for (var i = 1; i <= data.pages; i++) {
							var $link = $('<a href="#" class="button button-small">' + i + '</a>');
							if (i === currentPage) $link.addClass('button-primary');
							$link.data('page', i);
							$pag.append($link).append(' ');
						}
						$pag.on('click', 'a', function (e) {
							e.preventDefault();
							loadLogs($(this).data('page'));
						});
					}
				}
			});
		}

		$('#wss-refresh-logs').on('click', function () { loadLogs(1); });
		$('#wss-log-type-filter').on('change', function () { loadLogs(1); });

		$('#wss-clear-logs').on('click', function () {
			if (!confirm(l10n.confirmClearLogs)) return;
			$.ajax({
				url: wssAdmin.ajaxUrl,
				method: 'POST',
				data: { action: 'wss_clear_logs', nonce: wssAdmin.nonce },
				success: function () { loadLogs(1); }
			});
		});

		$('#wss-export-logs').on('click', function () {
			$.ajax({
				url: wssAdmin.ajaxUrl,
				method: 'POST',
				data: { action: 'wss_export_logs', nonce: wssAdmin.nonce },
				success: function (response) {
					if (!response.success) return;
					var blob = new Blob([response.data.csv], { type: 'text/csv' });
					var a = document.createElement('a');
					a.href = URL.createObjectURL(blob);
					a.download = 'wss-logs-' + new Date().toISOString().slice(0, 10) + '.csv';
					a.click();
				}
			});
		});

		// Load logs if on logs tab.
		if ($('#wss-logs-table').length) {
			loadLogs(1);
		}
	}

	/**
	 * Fetch and display index stats.
	 */
	function initIndexStats() {
		refreshStats();
	}

	function refreshStats() {
		if (!$('#wss-indexed-count').length) return;

		$.ajax({
			url: wssAdmin.ajaxUrl,
			method: 'POST',
			data: { action: 'wss_get_index_stats', nonce: wssAdmin.nonce },
			success: function (response) {
				if (response.success) {
					$('#wss-indexed-count').text(response.data.numberOfDocuments || 0);
				}
			}
		});
	}

})(jQuery);
