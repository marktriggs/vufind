$(document).ready(function(){
    // create the slider for the publish date facet
    makePublishDateSlider();    
});

function makePublishDateSlider() {
    // create the slider widget
    $('#publishDateSlider').slider({
        range: true,
        slide: function(event, ui) {
            $('#publishDatefrom').val(ui.values[0]);
            $('#publishDateto').val(ui.values[1]);
        }
    });
    // initialize the slider with the original values
    // in the text boxes
    updatePublishDateSlider();

    // when user enters values into the boxes
    // the slider needs to be updated too
    $('#publishDatefrom, #publishDateto').change(function(){
        updatePublishDateSlider();
    });    
}

function updatePublishDateSlider() {
    var from = parseInt($('#publishDatefrom').val());
    var to = parseInt($('#publishDateto').val());
    // assuming our oldest item is published in the 15th century
    var min = 1500;
    if (!from || from < min) {
        from = min;
    }
    // move the min 20 years away from the "from" value
    if (from > min + 20) {
        min = from - 20;
    }
    // and keep the max at 1 years from now
    var max = (new Date()).getFullYear() + 1;
    if (!to || to > max) {
        to = max;
    }
    // update the slider with the new min/max/values
    $('#publishDateSlider').slider('option', {
        min: min, max: max, values: [from, to]
    });
}