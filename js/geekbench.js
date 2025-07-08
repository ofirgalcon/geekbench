// Global variable to store the maximum scores
// These scores are all derived from the Mac benchmarks JSON
var geekbenchMaxScores = {
    score: 1000,
    multiscore: 10000,
    metal_score: 100000,
    opencl_score: 100000,
    cuda_score: 100000
};

// Flag to track if we're currently fetching data
var isFetchingGeekbenchScores = false;

// Fetch maximum scores when the page loads
$(document).ready(function() {
    // Prevent multiple initializations
    if (window.geekbenchInitialized) {
        return;
    }
    window.geekbenchInitialized = true;
    
    // Try to get cached values from localStorage first
    var cachedScores = null;
    var cacheTimestamp = 0;
    
    try {
        cachedScores = JSON.parse(localStorage.getItem('geekbenchMaxScores'));
        cacheTimestamp = parseInt(localStorage.getItem('geekbenchMaxScoresTimestamp') || '0', 10);
    } catch (e) {
        // If there's an error parsing the cached data, ignore it
        cachedScores = null;
    }
    
    // Check if the cache is still valid (less than 24 hours old)
    var currentTime = Math.floor(Date.now() / 1000);
    var cacheAge = currentTime - cacheTimestamp;
    var cacheValid = cachedScores && cacheAge < 86400; // 24 hours in seconds
    
    if (cacheValid) {
        // Use the cached values
        geekbenchMaxScores = cachedScores;
    } else if (!isFetchingGeekbenchScores) {
        // Set flag to prevent duplicate requests
        isFetchingGeekbenchScores = true;
        
        // Fetch fresh values from the server
        $.getJSON(appUrl + '/module/geekbench/get_max_scores', function(data) {
            // Update global variable with fetched max scores
            geekbenchMaxScores = data;
            
            // Cache the values in localStorage
            try {
                localStorage.setItem('geekbenchMaxScores', JSON.stringify(data));
                localStorage.setItem('geekbenchMaxScoresTimestamp', String(Math.floor(Date.now() / 1000)));
            } catch (e) {
                // If localStorage is not available or quota is exceeded, just continue
            }
            
            // Reset the flag
            isFetchingGeekbenchScores = false;
        }).fail(function() {
            // Reset the flag on failure too
            isFetchingGeekbenchScores = false;
        });
    }
});

var formatGeekbenchScore = function(colNumber, row, scoreType, progressBarClass) {
    var col = $('td:eq(' + colNumber + ')', row),
        score = parseInt(col.text(), 10);
    
    if (score) {
        // Use the dynamic divisor from the global variable
        var divisor = geekbenchMaxScores[scoreType] || 1000;
        
        // Calculate scale - if score equals or exceeds the max, set to 100%
        var scale;
        if (score >= divisor) {
            scale = 100;
        } else {
            scale = (score * 100) / divisor;
        }
        
        var progressBar = '<div class="progress"><div class="progress-bar ' + progressBarClass + '" style="width: ' + scale + '%;">' + score + '</div></div>';
        col.html(progressBar);
    }
};

// Usage
var formatGeekbench = function(colNumber, row) {
    formatGeekbenchScore(colNumber, row, 'score', 'progress-bar-info1');
};

var formatGeekbenchMulti = function(colNumber, row) {
    formatGeekbenchScore(colNumber, row, 'multiscore', 'progress-bar-info2');
};

var formatGeekbenchMetal = function(colNumber, row) {
    formatGeekbenchScore(colNumber, row, 'metal_score', 'progress-bar-info3');
};

var formatGeekbenchOpenCL = function(colNumber, row) {
    formatGeekbenchScore(colNumber, row, 'opencl_score', 'progress-bar-info4');
};

var formatGeekbenchCuda = function(colNumber, row) {
    formatGeekbenchScore(colNumber, row, 'cuda_score', 'progress-bar-info5');
};
