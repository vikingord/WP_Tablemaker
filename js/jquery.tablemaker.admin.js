

jQuery(function($) {
	$('tbody').on('click', 'input.removerow', function() {
		if ( ! confirm("Are you sure you want to delete this row and data? \nThis cannot be undone.")) {
			return false;
		}
		$(this).closest('tr').remove();
	});

	$(".tablemaker a.delete").click(function(){
		if ( ! confirm("Are you sure you want to delete this table and data? \nThis cannot be undone.")) {
			return false;
		}
	});


	$('table.data tbody').sortable({
		stop: function() {
			reindexRows();
		}
	});

	function reindexRows() {
		var count = 0;
		$('tbody tr').each(
			function() {
				$(this).find('input[type="text"]').each(
					function() {
						var name = $(this).attr('name');
						name = name.split('][');
						name = 'data[' + count + '][' + name[1];
						$(this).attr('name', name);
					}
				);
				count++;
			}
		);
	}
});