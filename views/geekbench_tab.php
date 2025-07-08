<div id="lister" style="font-size: large; float: right;">
    <a href="/show/listing/geekbench/geekbench" title="List">
        <i class="btn btn-default tab-btn fa fa-list"></i>
    </a>
</div>
<h2>
    <i class="fa fa-tachometer"></i> <span>Geekbench 6</span>
    <a data-i18n="geekbench.recheck" class="btn btn-default btn-xs" href="<?php echo url('module/geekbench/recheck_geekbench/' . $serial_number); ?>"></a>
</h2>

<div id="geekbench-msg" data-i18n="listing.loading" class="col-lg-12 text-center"></div>
<div id="geekbench-view" class="row hide">
    <div class="col-md-5">
        <table class="table table-striped">
            <tbody>
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
                <tr>
                    <th data-i18n="geekbench.last_run"></th>
                    <td id="geekbench-last_run"></td>
                </tr>
            </tbody>
        </table>
        <div id="geekbench-alt-model-notice" class="small text-muted hide" style="text-align: left;margin-left: 4px;">*Benchmarks not available, using a similar Mac model</div>
    </div>
</div>

<script>
$(document).on('appReady', function(e, lang) {
    // Prevent multiple initializations
    if (window.geekbenchTabInitialized) {
        return;
    }
    window.geekbenchTabInitialized = true;

    // Cache jQuery selectors to avoid repeated DOM queries
    const $geekbenchMsg = $('#geekbench-msg');
    const $geekbenchView = $('#geekbench-view');
    const $modelName = $('#geekbench-model_name');
    const $description = $('#geekbench-description');
    const $samples = $('#geekbench-samples');
    const $gpuName = $('#geekbench-gpu_name');
    const $lastRun = $('#geekbench-last_run');

    // Initialize score data with default divisors (will be updated dynamically)
    // All scores are now derived from the Mac benchmarks JSON
    const scoreData = [
        { id: "geekbench-score", field: "score", divisor: 1000, class: "progress-bar-info1" },
        { id: "geekbench-multiscore", field: "multiscore", divisor: 10000, class: "progress-bar-info2" },
        { id: "geekbench-metal_score", field: "metal_score", divisor: 100000, class: "progress-bar-info3" },
        { id: "geekbench-opencl_score", field: "opencl_score", divisor: 100000, class: "progress-bar-info4" },
        // Uncomment below for CUDA scores
        // { id: "geekbench-cuda_score", field: "cuda_score", divisor: 100000, class: "progress-bar-info5" }
    ];

    // Flag to track if we're currently fetching data
    let isFetchingTabScores = false;

    // Check if geekbenchMaxScores is already defined by geekbench.js
    if (typeof geekbenchMaxScores !== 'undefined') {
        // Use the scores from geekbench.js
        updateDivisorsAndFetchData(geekbenchMaxScores);
    } else {
        // Try to get cached values from localStorage first
        let cachedScores = null;
        let cacheTimestamp = 0;
        
        try {
            cachedScores = JSON.parse(localStorage.getItem('geekbenchMaxScores'));
            cacheTimestamp = parseInt(localStorage.getItem('geekbenchMaxScoresTimestamp') || '0', 10);
        } catch (e) {
            // If there's an error parsing the cached data, ignore it
            cachedScores = null;
        }
        
        // Check if the cache is still valid (less than 24 hours old)
        const currentTime = Math.floor(Date.now() / 1000);
        const cacheAge = currentTime - cacheTimestamp;
        const cacheValid = cachedScores && cacheAge < 86400; // 24 hours in seconds
        
        if (cacheValid) {
            // Use the cached values
            updateDivisorsAndFetchData(cachedScores);
        } else if (!isFetchingTabScores && (typeof isFetchingGeekbenchScores === 'undefined' || !isFetchingGeekbenchScores)) {
            // Set flag to prevent duplicate requests
            isFetchingTabScores = true;
            
            // First, fetch the maximum scores from the cached Mac benchmarks data
            $.getJSON(appUrl + '/module/geekbench/get_max_scores', function(maxScores) {
                // Cache the values in localStorage
                try {
                    localStorage.setItem('geekbenchMaxScores', JSON.stringify(maxScores));
                    localStorage.setItem('geekbenchMaxScoresTimestamp', String(Math.floor(Date.now() / 1000)));
                } catch (e) {
                    // If localStorage is not available or quota is exceeded, just continue
                }
                
                // Reset the flag
                isFetchingTabScores = false;
                
                updateDivisorsAndFetchData(maxScores);
            }).fail(function() {
                // Reset the flag on failure too
                isFetchingTabScores = false;
                
                // If fetching max scores fails, continue with default divisors
                fetchMachineData();
            });
        } else {
            // Another component is already fetching the data, use default divisors
            fetchMachineData();
        }
    }
    
    function updateDivisorsAndFetchData(maxScores) {
        // Update divisors with the maximum scores
        scoreData.forEach(item => {
            if (maxScores[item.field] && maxScores[item.field] > 0) {
                item.divisor = maxScores[item.field];
            }
        });
        
        // Now fetch the data for this specific machine
        fetchMachineData();
    }

    function fetchMachineData() {
        // Fetch Geekbench data for this specific machine
        $.getJSON(appUrl + '/module/geekbench/get_data/' + serialNumber, function(data) {
            if (!data.score) {
                $geekbenchMsg.text(i18n.t('no_data'));
            } else {
                // Hide loading message and show the view
                $geekbenchMsg.text('');
                $geekbenchView.removeClass('hide');

                // Fill basic data
                $modelName.text(data.model_name);
                $description.text(data.description);
                $samples.text(data.samples);
                $gpuName.text(data.gpu_name);

                // Fill date information
                const lastRun = data.last_run;
                if (lastRun) {
                    const formattedDate = lastRun.indexOf('-') === -1
                        ? moment(new Date(lastRun * 1000)).fromNow()
                        : moment(lastRun).fromNow();
                    $lastRun.html(`<span title="${lastRun}">${formattedDate}</span>`);
                }

                // Process scores dynamically
                scoreData.forEach(({ id, field, divisor, class: progressBarClass }) => {
                    const score = data[field];
                    if (score) {
                        // Calculate scale - if score equals or exceeds the max, set to 100%
                        let scale;
                        if (score >= divisor) {
                            scale = 100;
                        } else {
                            scale = (score * 100) / divisor;
                        }
                        
                        $(`#${id}`).html(`
                            <div class="progress">
                                <div class="progress-bar ${progressBarClass}" style="width: ${scale}%;">${score}</div>
                            </div>
                        `);
                    }
                });

                // Show alt model notice if model name contains an asterisk
                if (data.model_name && data.model_name.includes('*')) {
                    $('#geekbench-alt-model-notice').removeClass('hide');
                }
            }
        });
    }
});
</script>
