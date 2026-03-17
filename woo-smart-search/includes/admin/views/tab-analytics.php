<?php
/**
 * Analytics tab template.
 *
 * Displays search analytics: top queries, zero-result queries,
 * search volume stats, and click-through rate.
 *
 * @package WooSmartSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wss-analytics-wrap">

	<!-- Search Volume Stats -->
	<div id="wss-search-volume-stats" class="wss-analytics-section" style="margin-bottom: 25px;">
		<h2><?php esc_html_e( 'Search Volume', 'woo-smart-search' ); ?></h2>
		<div class="wss-stats-cards" style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
			<div class="wss-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px 25px; min-width: 150px;">
				<div class="wss-stat-label" style="color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">
					<?php esc_html_e( 'Today', 'woo-smart-search' ); ?>
				</div>
				<div class="wss-stat-value" id="wss-total-today" style="font-size: 28px; font-weight: 600; color: #1d2327;">
					&mdash;
				</div>
			</div>
			<div class="wss-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px 25px; min-width: 150px;">
				<div class="wss-stat-label" style="color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">
					<?php esc_html_e( 'This Week', 'woo-smart-search' ); ?>
				</div>
				<div class="wss-stat-value" id="wss-total-week" style="font-size: 28px; font-weight: 600; color: #1d2327;">
					&mdash;
				</div>
			</div>
			<div class="wss-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px 25px; min-width: 150px;">
				<div class="wss-stat-label" style="color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">
					<?php esc_html_e( 'This Month', 'woo-smart-search' ); ?>
				</div>
				<div class="wss-stat-value" id="wss-total-month" style="font-size: 28px; font-weight: 600; color: #1d2327;">
					&mdash;
				</div>
			</div>
			<div class="wss-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px 25px; min-width: 150px;">
				<div class="wss-stat-label" style="color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">
					<?php esc_html_e( 'Click-Through Rate', 'woo-smart-search' ); ?>
				</div>
				<div class="wss-stat-value" id="wss-ctr" style="font-size: 28px; font-weight: 600; color: #1d2327;">
					&mdash;
				</div>
			</div>
		</div>
	</div>

	<!-- Top 20 Search Queries -->
	<div id="wss-top-queries-section" class="wss-analytics-section" style="margin-bottom: 25px;">
		<h2><?php esc_html_e( 'Top 20 Search Queries', 'woo-smart-search' ); ?></h2>
		<table class="widefat striped" id="wss-top-queries-table">
			<thead>
				<tr>
					<th style="width: 5%;">#</th>
					<th style="width: 50%;"><?php esc_html_e( 'Query', 'woo-smart-search' ); ?></th>
					<th style="width: 20%;"><?php esc_html_e( 'Count', 'woo-smart-search' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Last Searched', 'woo-smart-search' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="4" style="text-align: center; padding: 20px;">
						<?php esc_html_e( 'Loading...', 'woo-smart-search' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Searches with No Results -->
	<div id="wss-zero-results-section" class="wss-analytics-section" style="margin-bottom: 25px;">
		<h2><?php esc_html_e( 'Searches with No Results', 'woo-smart-search' ); ?></h2>
		<p class="description" style="margin-bottom: 10px;">
			<?php esc_html_e( 'These queries returned zero results. Consider adding synonyms or new products to improve search coverage.', 'woo-smart-search' ); ?>
		</p>
		<table class="widefat striped" id="wss-zero-results-table">
			<thead>
				<tr>
					<th style="width: 5%;">#</th>
					<th style="width: 50%;"><?php esc_html_e( 'Query', 'woo-smart-search' ); ?></th>
					<th style="width: 20%;"><?php esc_html_e( 'Count', 'woo-smart-search' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Last Searched', 'woo-smart-search' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="4" style="text-align: center; padding: 20px;">
						<?php esc_html_e( 'Loading...', 'woo-smart-search' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

</div>

<script type="text/javascript">
	jQuery( document ).ready( function( $ ) {
		$.post( wssAdmin.ajaxUrl, {
			action: 'wss_get_analytics',
			nonce: wssAdmin.nonce,
			period: 'week'
		}, function( response ) {
			if ( ! response.success || ! response.data ) {
				return;
			}

			var data = response.data;

			// Update volume stats.
			$( '#wss-total-today' ).text( data.totals.today || 0 );
			$( '#wss-total-week' ).text( data.totals.week || 0 );
			$( '#wss-total-month' ).text( data.totals.month || 0 );
			$( '#wss-ctr' ).text( data.click_through_rate + '%' );

			// Populate top queries table.
			var $topBody = $( '#wss-top-queries-table tbody' );
			$topBody.empty();
			if ( data.top_queries && data.top_queries.length > 0 ) {
				$.each( data.top_queries, function( index, row ) {
					$topBody.append(
						'<tr>' +
							'<td>' + ( index + 1 ) + '</td>' +
							'<td><code>' + $( '<span>' ).text( row.query ).html() + '</code></td>' +
							'<td>' + parseInt( row.count, 10 ) + '</td>' +
							'<td>' + $( '<span>' ).text( row.last_searched ).html() + '</td>' +
						'</tr>'
					);
				} );
			} else {
				$topBody.append(
					'<tr><td colspan="4" style="text-align:center; padding:20px;">' +
					'<?php echo esc_js( __( 'No search data yet.', 'woo-smart-search' ) ); ?>' +
					'</td></tr>'
				);
			}

			// Populate zero-results table.
			var $zeroBody = $( '#wss-zero-results-table tbody' );
			$zeroBody.empty();
			if ( data.zero_result_queries && data.zero_result_queries.length > 0 ) {
				$.each( data.zero_result_queries, function( index, row ) {
					$zeroBody.append(
						'<tr>' +
							'<td>' + ( index + 1 ) + '</td>' +
							'<td><code>' + $( '<span>' ).text( row.query ).html() + '</code></td>' +
							'<td>' + parseInt( row.count, 10 ) + '</td>' +
							'<td>' + $( '<span>' ).text( row.last_searched ).html() + '</td>' +
						'</tr>'
					);
				} );
			} else {
				$zeroBody.append(
					'<tr><td colspan="4" style="text-align:center; padding:20px;">' +
					'<?php echo esc_js( __( 'No zero-result queries found.', 'woo-smart-search' ) ); ?>' +
					'</td></tr>'
				);
			}
		} );
	} );
</script>
