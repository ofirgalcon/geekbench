var formatGeekbench = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score/17.5;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info1" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchMulti = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score/234;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info2" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchMetal = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score/1669;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info3" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchOpenCL = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score/1700;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info4" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}

var formatGeekbenchCuda = function(colNumber, row){
    var col = $('td:eq('+colNumber+')', row),
        score = col.text();
        scale = score/2603;
    if (score){
        col.html('<div class="progress"><div class="progress-bar progress-bar-info5" style="width: '+scale+'%;">'+score+'</div></div>');
    }
}
