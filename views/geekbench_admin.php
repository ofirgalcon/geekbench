<?php $this->view('partials/head'); ?>

<div class="container">
    <div class="row"><span id="geekbench_pull_all"></span></div>
    <div class="col-lg-5">
        <div id="model-count-loading" class="text-center"><i class="fa fa-spinner fa-spin fa-lg"></i></div>
        <div id="model-count-display" class="hide">
            <table class="table table-striped table-condensed">
                <tbody>
                    <tr>
                        <th data-i18n="geekbench.mac_models"></th>
                        <td id="mac-model-count">-</td>
                    </tr>
                    <tr>
                        <th data-i18n="geekbench.opencl_models"></th>
                        <td id="opencl-model-count">-</td>
                    </tr>
                    <tr>
                        <th data-i18n="geekbench.metal_models"></th>
                        <td id="metal-model-count">-</td>
                    </tr>
                    <tr>
                        <th data-i18n="geekbench.total_models"></th>
                        <td id="total-model-count">-</td>
                    </tr>
                    <tr>
                        <th data-i18n="geekbench.last_updated"></th>
                        <td id="last-updated-time">-</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div id="max-scores-loading" class="text-center"><i class="fa fa-spinner fa-spin fa-lg"></i></div>
        <div id="max-scores-display" class="hide">
            <h4><i class="fa fa-trophy"></i> <span data-i18n="geekbench.max_scores"></span></h4>
            <table class="table table-striped table-condensed">
                <tbody>
                    <tr>
                        <th data-i18n="geekbench.score"></th>
                        <td id="max-score">-</td>
                    </tr>
                    <tr>
                        <th data-i18n="geekbench.multiscore"></th>
                        <td id="max-multiscore">-</td>
                    </tr>
                    <tr>
                        <th data-i18n="geekbench.metal_score"></th>
                        <td id="max-metal-score">-</td>
                    </tr>
                    <tr>
                        <th data-i18n="geekbench.opencl_score"></th>
                        <td id="max-opencl-score">-</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div id="GetAllGeekbench-Progress" class="progress hide">
            <div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="min-width: 2em; width: 0%;">
                <span id="Progress-Bar-Percent"></span>
            </div>
        </div>
        <br id="Progress-Space" class="hide">
        <div id="Geekbench-System-Status"></div>
        <div id="Geekbench-Match-Errors"></div>
    </div>
    <div class="col-lg-6">
        <!-- Right column is now empty -->
    </div>
</div>  <!-- /container -->

<script>
var geekbench_pull_all_running = 0;
    
$(document).on('appReady', function(e, lang) {
    
    // Generate pull all button and header    
    $('#geekbench_pull_all').html('<h3 class="col-lg-6" >&nbsp;&nbsp;<i class="fa fa-tachometer"></i> '+i18n.t('geekbench.geekbench_admin')+'&nbsp;&nbsp;<button id="GetAllGeekbench" class="btn btn-default btn-sm">'+i18n.t("geekbench.pull_in_all")+'</button>&nbsp;&nbsp;<button id="GetJSONs" class="btn btn-default btn-sm">'+i18n.t("geekbench.get_jsons")+'</button>&nbsp;<i id="JSONProgess" class="hide fa fa-cog fa-spin" aria-hidden="true"></i></h3>');
        
    geekbench_pull_all_running = 0;
    
    // Load model count data
    loadModelCount();
    
    $('#GetJSONs').click(function (e) {
        // Disable button
        $('#GetJSONs').addClass('disabled');
        $('#JSONProgess').removeClass('hide');
        
        // Get new JSONs
        $.getJSON(appUrl + '/module/geekbench/update_cached_jsons', function (get_result) {
            // Show update result
            if(get_result["status"] == 1){
            
                $('#Geekbench-System-Status').html("<div class='row' id='JSONResult'><div class='alert alert-success col-md-4 col-md-offset-4' align='center' style='margin-top: 20px;'>"+i18n.t("geekbench.get_jsons_success")+"</div></div>");
                $('#GetJSONs').removeClass('disabled');
                $('#JSONProgess').addClass('hide');
                
                // Reload model count after updating JSONs
                loadModelCount();
                
                // Trigger max_scores recalculation
                $.getJSON(appUrl + '/module/geekbench/get_max_scores', function(data) {
                    // Cache the values in localStorage
                    try {
                        localStorage.setItem('geekbenchMaxScores', JSON.stringify(data));
                        localStorage.setItem('geekbenchMaxScoresTimestamp', String(Math.floor(Date.now() / 1000)));
                    } catch (e) {
                        // If localStorage is not available or quota is exceeded, just continue
                    }
                });

            } else {
                $('#Geekbench-System-Status').html("<div class='row' id='JSONResult'><div class='alert alert-danger col-md-4 col-md-offset-4' align='center' style='margin-top: 20px;'>"+i18n.t("geekbench.get_jsons_failed")+"</div></div>");
                $('#GetJSONs').removeClass('disabled');
                $('#JSONProgess').addClass('hide');
            }
        })
    })
    
    $('#GetAllGeekbench').click(function (e) {
        // Disable button and unhide progress bar
        $('#GetAllGeekbench').html(i18n.t('geekbench.processing')+'...');
        $('#Progress-Bar-Percent').text('0%');
        $('#GetAllGeekbench-Progress').removeClass('hide');
        $('#Progress-Space').removeClass('hide');
        $('#GetAllGeekbench').addClass('disabled');
        $('#GetJSONs').addClass('disabled');
        $('#JSONResult').addClass('hide');
        geekbench_pull_all_running = 1;

        // Get JSON of all serial numbers
        $.getJSON(appUrl + '/module/geekbench/pull_all_geekbench_data', function (processdata) {

            // Set count of serial numbers to be processed
            var progressmax = (processdata.length);
            var progessvalue = 0;
            $('.progress-bar').attr('aria-valuemax', progressmax);

            var serial_index = 0;
            var serial = processdata[0];

            // Process the serial numbers
            process_serial(serial,progessvalue,progressmax,processdata,serial_index);
        });
    });
});

// Function to load model count data
function loadModelCount() {
    // Show loading indicator
    $('#model-count-loading').removeClass('hide');
    $('#model-count-display').addClass('hide');
    $('#max-scores-loading').removeClass('hide');
    $('#max-scores-display').addClass('hide');
    
    // Get model count data
    $.getJSON(appUrl + '/module/geekbench/get_model_count', function (data) {
        // Update the model count display
        $('#mac-model-count').text(data.mac_count);
        $('#opencl-model-count').text(data.opencl_count);
        $('#metal-model-count').text(data.metal_count);
        $('#total-model-count').text(data.total_count);
        
        // Format and display the last updated timestamp
        if (data.last_updated) {
            var lastUpdatedDate = new Date(data.last_updated * 1000);
            $('#last-updated-time').html('<span title="' + lastUpdatedDate.toLocaleString() + '">' + moment(lastUpdatedDate).fromNow() + '</span>');
        } else {
            $('#last-updated-time').text('-');
        }
        
        // Hide loading indicator and show data
        $('#model-count-loading').addClass('hide');
        $('#model-count-display').removeClass('hide');
        
        // Load max scores
        loadMaxScores();
    }).fail(function() {
        // If there's an error, show error message
        $('#model-count-loading').addClass('hide');
        $('#model-count-display').removeClass('hide');
        $('#mac-model-count').text('-');
        $('#opencl-model-count').text('-');
        $('#metal-model-count').text('-');
        $('#total-model-count').text('-');
        $('#last-updated-time').text('-');
        
        // Load max scores even if model count fails
        loadMaxScores();
    });
}

// Function to load max scores
function loadMaxScores() {
    $.getJSON(appUrl + '/module/geekbench/get_max_scores', function(data) {
        // Update the max scores display
        $('#max-score').text(data.score);
        $('#max-multiscore').text(data.multiscore);
        $('#max-metal-score').text(data.metal_score);
        $('#max-opencl-score').text(data.opencl_score);
        
        // Hide loading indicator and show data
        $('#max-scores-loading').addClass('hide');
        $('#max-scores-display').removeClass('hide');
        
        // Cache the values in localStorage
        try {
            localStorage.setItem('geekbenchMaxScores', JSON.stringify(data));
            localStorage.setItem('geekbenchMaxScoresTimestamp', String(Math.floor(Date.now() / 1000)));
        } catch (e) {
            // If localStorage is not available or quota is exceeded, just continue
        }
    }).fail(function() {
        // If there's an error, show error message
        $('#max-scores-loading').addClass('hide');
        $('#max-scores-display').removeClass('hide');
        $('#max-score').text('-');
        $('#max-multiscore').text('-');
        $('#max-metal-score').text('-');
        $('#max-opencl-score').text('-');
    });
}
    
    
// Process each serial number one at a time
function process_serial(serial,progessvalue,progressmax,processdata,serial_index){

    // Get JSON for each serial number
    request = $.ajax({
        url: appUrl + '/module/geekbench/pull_all_geekbench_data/'+processdata[serial_index],
        type: "get",
        success: function (obj, resultdata) {

            // Check if machine was properly processed
            if (!obj['process_status'].includes("Machine processed")){
                $('#Geekbench-Match-Errors').append("<br>"+obj['process_status']+" -- "+obj['serial'])
            }

            // Calculate progress bar's percent
            var processpercent = Math.round((((progessvalue+1)/progressmax)*100));
            progessvalue++
            $('.progress-bar').css('width', (processpercent+'%')).attr('aria-valuenow', processpercent);
            $('#Progress-Bar-Percent').text(progessvalue+"/"+progressmax);

            // Cleanup and reset when done processing serials
            if ((progessvalue) == progressmax) {
                // Make button clickable again and hide process bar elements
                $('#GetAllGeekbench').html(i18n.t('geekbench.pull_in_all'));
                $('#GetAllGeekbench').removeClass('disabled');
                $('#UpdateGeekbench').removeClass('disabled');
                geekbench_pull_all_running = 0;
                $("#Progress-Space").fadeOut(1200, function() {
                    $('#Progress-Space').addClass('hide')
                    var progresselement = document.getElementById('Progress-Space');
                    progresselement.style.display = null;
                    progresselement.style.opacity = null;
                });
                $("#GetAllGeekbench-Progress").fadeOut( 1200, function() {
                    $('#GetAllGeekbench-Progress').addClass('hide')
                    var progresselement = document.getElementById('GetAllGeekbench-Progress');
                    progresselement.style.display = null;
                    progresselement.style.opacity = null;
                    $('.progress-bar').css('width', 0+'%').attr('aria-valuenow', 0);
                });

                return true;
            }

            // Go to the next serial
            serial_index++

            // Get next serial
            serial = processdata[serial_index];

            // Run function again with new serial
            process_serial(serial,progessvalue,progressmax,processdata,serial_index)
        },
        statusCode: {
            500: function() {
                geekbench_pull_all_running = 0;
                alert("An internal server occurred. Please refresh the page and try again.");
            }
        }
    });
}


// Warning about leaving page if Geekbench pull all is running
window.onbeforeunload = function() {
    if (geekbench_pull_all_running == 1) {
        return i18n.t('geekbench.leave_page_warning');
    } else {
        return;
    }
};
    
</script>

<?php $this->view('partials/foot'); ?>