/**
 * Drag-and-drop ordering for the post list table.
 *
 * Rows are made sortable; on drop, the visible row order is posted to
 * admin-ajax.php and saved as menu_order values. The Order column is
 * refreshed in place so the numbers match the new positions.
 */
(function ($) {
	'use strict';

	var $tbody = $('#the-list');

	if (!$tbody.length || typeof wpoOrder === 'undefined') {
		return;
	}

	// Only enable sorting when the list is actually showing the manual
	// order (default list view, no search/filter active).
	var params = new URLSearchParams(window.location.search);
	if (params.get('s') || params.get('orderby') || params.get('post_status') || params.get('m') || params.get('cat')) {
		return;
	}

	$tbody.sortable({
		items: 'tr',
		axis: 'y',
		handle: '.column-title, .column-wpo_order',
		cursor: 'move',
		placeholder: 'wpo-sort-placeholder',
		helper: function (e, row) {
			// Keep column widths while dragging.
			row.children().each(function () {
				$(this).width($(this).width());
			});
			return row;
		},
		update: function () {
			var order = [];
			var postType = $('input[name="post_type"]').val() || $('#post_type').val() || '';

			$tbody.find('tr').each(function () {
				var id = $(this).attr('id') || '';
				var match = id.match(/^post-(\d+)$/);
				if (match) {
					order.push(parseInt(match[1], 10));
				}
			});

			if (!order.length) {
				return;
			}

			$.post(wpoOrder.ajaxUrl, {
				action: 'wpo_save_order',
				nonce: wpoOrder.nonce,
				post_type: postType,
				order: order
			}).done(function (response) {
				if (response && response.success) {
					// Refresh the Order column values in place.
					$tbody.find('tr').each(function (index) {
						$(this).find('.column-wpo_order').text(index);
					});
				}
			});
		}
	});
})(jQuery);
