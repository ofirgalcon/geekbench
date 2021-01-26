<?php $this->view('partials/head'); ?>

<div class="container">
    <div class="row"><span id="geekbench_pull_all"></span></div>
    <div class="col-lg-6">
        <div id="GetAllGeekbench-Progress" class="progress hide">
            <div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="min-width: 2em; width: 0%;">
                <span id="Progress-Bar-Percent"></span>
            </div>
        </div>
        <br id="Progress-Space" class="hide">
        <div id="Geekbench-System-Status"></div>
        <div id="Geekbench-Match-Errors"></div>
    </div>
</div>  <!-- /container -->

<script>
var geekbench_pull_all_running = 0;
    
$(document).on('appReady', function(e, lang) {
    
    // Generate pull all button and header    
    $('#geekbench_pull_all').html('<h3 class="col-lg-6" >&nbsp;&nbsp;'+i18n.t('geekbench.geekbench_admin')+'&nbsp;&nbsp;<button id="GetAllGeekbench" class="btn btn-default btn-xs">'+i18n.t("geekbench.pull_in_all")+'</button>&nbsp;&nbsp;<button id="GetJSONs" class="btn btn-default btn-xs">'+i18n.t("geekbench.get_jsons")+'</button>&nbsp;<i id="JSONProgess" class="hide fa fa-cog fa-spin" aria-hidden="true"></i></h3>');
        
    geekbench_pull_all_running = 0;
    
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