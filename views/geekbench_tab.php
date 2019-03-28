<h2>Geekbench  <a data-i18n="geekbench.recheck" class="btn btn-default btn-xs" href="<?php echo url('module/geekbench/recheck_geekbench/' . $serial_number);?>"></a></h2>

<div id="geekbench-msg" data-i18n="listing.loading" class="col-lg-12 text-center"></div>
	<div id="geekbench-view" class="row hide">
		<div class="col-md-3">
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
				<tr>
					<th data-i18n="geekbench.listing.gpu_name"></th>
					<td id="geekbench-gpu_name"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.listing.opencl_score"></th>
					<td id="geekbench-opencl_score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.listing.opencl_samples"></th>
					<td id="geekbench-opencl_samples"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.listing.cuda_score"></th>
					<td id="geekbench-cuda_score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.listing.cuda_samples"></th>
					<td id="geekbench-cuda_samples"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.listing.last_cache_pull"></th>
					<td id="geekbench-last_cache_pull"></td>
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
			$('#geekbench-cuda_score').text(data.cuda_score);
			$('#geekbench-cuda_samples').text(data.cuda_samples);
			$('#geekbench-opencl_score').text(data.opencl_score);
			$('#geekbench-opencl_samples').text(data.opencl_samples);
			$('#geekbench-gpu_name').text(data.gpu_name);
            
            // Format and fill date
            var colvar = data.last_cache_pull;
            if(colvar !== "" && colvar.indexOf('-') === -1){
                var date = new Date(colvar * 1000);
                $('#geekbench-last_cache_pull').html('<span title="'+moment(date).format('llll')+'">'+moment(date).fromNow()+'</span>');
            } else if (colvar !== ""){
                $('#geekbench-last_cache_pull').html('<span title="' + colvar + '">' + moment(colvar).fromNow()+'</span>');   
            }
            
		}
	});
});
    
</script>