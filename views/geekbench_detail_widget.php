<div id="geekbench-summary-tab" class="col-lg-4">
		<h4><i class="fa fa-tachometer"></i> <span data-i18n="geekbench.geekbench"></span><a data-toggle="tab" title="Geekbench" class="btn btn-xs pull-right" href="#geekbench-tab" aria-expanded="false"><i class="fa fa-arrow-right"></i></a></h4>
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
    // Prevent multiple initializations
    if (window.geekbenchWidgetInitialized) {
        return;
    }
    window.geekbenchWidgetInitialized = true;
    
    // These elements might not exist in the detail widget context
    // so we'll check before using them
    const $geekbenchMsg = $('#geekbench-msg').length ? $('#geekbench-msg') : $();
    const $geekbenchView = $('#geekbench-view').length ? $('#geekbench-view') : $();
    
    const progressBarColors = {
        score: '1',
        multiscore: '2',
        metal_score: '3'
    };

    function updateProgressBar(elementId, score, maxScore) {
        if (!score) return;
        
        const scale = Math.min((score * 100) / maxScore, 100); // Prevent overflow
        const colorSuffix = progressBarColors[elementId];
        
        // Use template literal only once
        const progressBar = `
            <div class="progress" style="height: 16px;">
                <div class="progress-bar progress-bar-info${colorSuffix}" 
                     style="width: ${scale}%; line-height: 17px;">
                    ${score}
                </div>
            </div>`;
        
        const $element = $(`#geekbench-widget-${elementId}`);
        if ($element.length) {
            $element.html(progressBar);
        }
    }

    // Function to get max scores from cache or fetch them
    function getMaxScores() {
        return new Promise((resolve, reject) => {
            // Try to get from localStorage first
            try {
                const cachedScores = localStorage.getItem('geekbenchMaxScores');
                const cachedTimestamp = localStorage.getItem('geekbenchMaxScoresTimestamp');
                
                if (cachedScores && cachedTimestamp) {
                    const now = Math.floor(Date.now() / 1000);
                    const age = now - parseInt(cachedTimestamp);
                    
                    // Use cached scores if they're less than 1 hour old
                    if (age < 3600) {
                        resolve(JSON.parse(cachedScores));
                        return;
                    }
                }
            } catch (e) {
                // If localStorage fails, continue to fetch
            }
            
            // If no valid cache, fetch from server
            fetch(`${appUrl}/module/geekbench/get_max_scores`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Cache the new values
                    try {
                        localStorage.setItem('geekbenchMaxScores', JSON.stringify(data));
                        localStorage.setItem('geekbenchMaxScoresTimestamp', String(Math.floor(Date.now() / 1000)));
                    } catch (e) {
                        // If localStorage fails, just continue
                    }
                    resolve(data);
                })
                .catch(reject);
        });
    }

    // Use fetch instead of $.getJSON for better performance
    Promise.all([
        fetch(`${appUrl}/module/geekbench/get_data/${serialNumber}`),
        getMaxScores()
    ])
    .then(([dataResponse, maxScores]) => {
        if (!dataResponse.ok) {
            throw new Error('Network response was not ok');
        }
        return dataResponse.json().then(data => ({ data, maxScores }));
    })
    .then(({ data, maxScores }) => {
        if (!data || !data.score) {
            if ($geekbenchMsg.length) {
                $geekbenchMsg.text(i18n.t('no_data'));
            }
            return;
        }

        if ($geekbenchMsg.length) {
            $geekbenchMsg.text('');
        }
        
        if ($geekbenchView.length) {
            $geekbenchView.removeClass('hide');
        }

        // Process all updates in a single batch
        requestAnimationFrame(() => {
            Object.keys(progressBarColors).forEach(key => {
                if (data[key] && maxScores[key]) {
                    updateProgressBar(key, data[key], maxScores[key]);
                }
            });
        });
    })
    .catch(error => {
        console.error('Error fetching Geekbench data:', error);
        if ($geekbenchMsg.length) {
            $geekbenchMsg.text(i18n.t('error'));
        }
    });
});
</script>