<h2>Geekbench  <a data-i18n="geekbench.recheck" class="btn btn-default btn-xs" href="<?php echo url('module/geekbench/recheck_geekbench/' . $serial_number);?>"></a></h2>

<div id="geekbench-msg" data-i18n="listing.loading" class="col-lg-12 text-center"></div>
	<div id="geekbench-view" class="row hide">
		<div class="col-md-5">
			<table class="table table-striped">
				<tr>
					<th data-i18n="geekbench.listing.score"></th>
					<td id="geekbench-score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.listing.multiscore"></th>
					<td id="geekbench-multiscore"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.listing.samples"></th>
					<td id="geekbench-samples"></td>
				</tr>	
				<tr>
					<th data-i18n="geekbench.geekbench_name"></th>
					<td id="geekbench-model_name"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.geekbench_desc"></th>
					<td id="geekbench-description"></td>
				</tr>
			</table>
		</div>
	</div>

<script>
$(document).on('appReady', function(e, lang) {
	
	// Get geekbench data
	$.getJSON( appUrl + '/module/geekbench/get_data/' + serialNumber, function( data ) {
        // Check if we have valid data
		if( ! data.score){
			$('#geekbench-msg').text(i18n.t('no_data'));
		} else {
			
			// Hide
			$('#geekbench-msg').text('');
			$('#geekbench-view').removeClass('hide');
			            
            // Add data
			$('#geekbench-score').text(data.score);
			$('#geekbench-multiscore').text(data.multiscore);
			$('#geekbench-samples').text(data.samples);
			$('#geekbench-model_name').text(data.model_name);
			$('#geekbench-description').text(data.description);
		}
	});
});
    
</script>