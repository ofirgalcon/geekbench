var formatGeekbench = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score*100/3125;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info1" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchMulti = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score*100/21312;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info2" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchMetal = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score*100/219969;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info3" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchOpenCL = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score*100/170008;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info4" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchCuda = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score*100/260346;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info5" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}
