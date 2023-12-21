<h2>Geekbench 6  <a data-i18n="geekbench.recheck" class="btn btn-default btn-xs" href="<?php echo url('module/geekbench/recheck_geekbench/' . $serial_number);?>"></a></h2>

<div id="geekbench-msg" data-i18n="listing.loading" class="col-lg-12 text-center"></div>
	<div id="geekbench-view" class="row hide">
		<div class="col-md-5">
			<table class="table table-striped">
				<tr>
					<th data-i18n="geekbench.geekbench_name"></th>
					<td id="geekbench-model_name"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.geekbench_desc"></th>
					<td id="geekbench-description"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.score"></th>
					<td id="geekbench-score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.multiscore"></th>
					<td id="geekbench-multiscore"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.samples"></th>
					<td id="geekbench-samples"></td>
				</tr>	
				<tr>
					<th data-i18n="geekbench.gpu_name"></th>
					<td id="geekbench-gpu_name"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.metal_score"></th>
					<td id="geekbench-metal_score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.metal_samples"></th>
					<td id="geekbench-metal_samples"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.opencl_score"></th>
					<td id="geekbench-opencl_score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.opencl_samples"></th>
					<td id="geekbench-opencl_samples"></td>
				</tr>
				<!-- <tr>
					<th data-i18n="geekbench.cuda_score"></th>
					<td id="geekbench-cuda_score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.cuda_samples"></th>
					<td id="geekbench-cuda_samples"></td>
				</tr> -->
				<tr>
					<th data-i18n="geekbench.last_run"></th>
					<td id="geekbench-last_run"></td>
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
            $('#geekbench-model_name').text(data.model_name);
            $('#geekbench-score').text(data.score);
            $('#geekbench-multiscore').text(data.multiscore);
            $('#geekbench-samples').text(data.samples);
            $('#geekbench-description').text(data.description);
            $('#geekbench-cuda_score').text(data.cuda_score);
            $('#geekbench-cuda_samples').text(data.cuda_samples);
            $('#geekbench-opencl_score').text(data.opencl_score);
            $('#geekbench-opencl_samples').text(data.opencl_samples);
            $('#geekbench-metal_score').text(data.metal_score);
            $('#geekbench-metal_samples').text(data.metal_samples);
            $('#geekbench-gpu_name').text(data.gpu_name);

            // Format and fill date
            var colvar = data.last_run;
            if(colvar !== "" && colvar.indexOf('-') === -1){
                var date = new Date(colvar * 1000);
                $('#geekbench-last_run').html('<span title="'+moment(date).format('llll')+'">'+moment(date).fromNow()+'</span>');
            } else if (colvar !== ""){
                $('#geekbench-last_run').html('<span title="' + colvar + '">' + moment(colvar).fromNow()+'</span>');   
            }

        	var gscore = data.score;
        	var gscale = gscore*100/3125;
    		if (gscore){
        		$('#geekbench-score').html('<div class="progress"><div class="progress-bar progress-bar-info1" style="width: '+gscale+'%;">'+gscore+'</div></div>');
			}

			var gscore = data.multiscore;
        	var gscale = gscore*100/21312;
    		if (gscore){
        		$('#geekbench-multiscore').html('<div class="progress"><div class="progress-bar progress-bar-info2" style="width: '+gscale+'%;">'+gscore+'</div></div>');
			}

			var gscore = data.metal_score;
        	var gscale = gscore*100/219969;
    		if (gscore){
        		$('#geekbench-metal_score').html('<div class="progress"><div class="progress-bar progress-bar-info3" style="width: '+gscale+'%;">'+gscore+'</div></div>');
			}

			var gscore = data.opencl_score;
        	var gscale = gscore*100/170008;
    		if (gscore){
        		$('#geekbench-opencl_score').html('<div class="progress"><div class="progress-bar progress-bar-info4" style="width: '+gscale+'%;">'+gscore+'</div></div>');
			}

			// var gscore = data.cuda_score;
        	// var gscale = gscore*100/260346;
    		// if (gscore){
        	// 	$('#geekbench-cuda_score').html('<div class="progress"><div class="progress-bar progress-bar-info5" style="width: '+gscale+'%;">'+gscore+'</div></div>');
			// }
        }
    });
});
    
</script>