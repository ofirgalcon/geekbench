
	<div id="geekbench-summary-tab" class="col-lg-4">
		<h4><i class="fa fa-tachometer"></i> <span data-i18n="geekbench.geekbench"></span></h4>
			<table class="table table-striped">
				<tr>
					<th data-i18n="geekbench.score"></th>
					<td id="geekbench-widget-score"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.multiscore"></th>
					<td id="geekbench-widget-multiscore"></td>
				</tr>
				<tr>
					<th data-i18n="geekbench.metal_score"></th>
					<td id="geekbench-widget-metal_score"></td>
				</tr>
			</table>
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
            $('#geekbench-widget-score').text(data.score);
            $('#geekbench-widget-multiscore').text(data.multiscore);
            $('#geekbench-widget-metal_score').text(data.metal_score);
            
        	var gscore = data.score;
        	var gscale = gscore*100/2803;
    		if (gscore){
        		$('#geekbench-widget-score').html('<div class="progress" style="height: 16px;"><div class="progress-bar progress-bar-info1" style="width: '+gscale+'%; line-height: 17px;">'+gscore+'</div></div>');
			}

			var gscore = data.multiscore;
        	var gscale = gscore*100/21299;
    		if (gscore){
        		$('#geekbench-widget-multiscore').html('<div class="progress" style="height: 16px;"><div class="progress-bar progress-bar-info2" style="width: '+gscale+'%; line-height: 17px;">'+gscore+'</div></div>');
			}

			var gscore = data.metal_score;
        	var gscale = gscore*100/219606;
    		if (gscore){
        		$('#geekbench-widget-metal_score').html('<div class="progress" style="height: 16px;"><div class="progress-bar progress-bar-info3" style="width: '+gscale+'%; line-height: 17px;">'+gscore+'</div></div>');
			}

        }
    });
});
    
</script>