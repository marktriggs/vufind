$(document).ready(function() {
    checkSaveStatuses();
});

function checkSaveStatuses() {
    var id = $.map($('.recordId'), function(i) {
        return $(i).attr('id').substr('record'.length);
    });
    if (id.length) {    
        $.ajax({
            dataType: 'json',
            url: path + '/AJAX/JSON?method=getSaveStatuses',
            data: {id:id},
            success: function(response) {
                if(response.status == 'OK') {
                    $('.savedLists > ul').empty();
                    $.each(response.data, function(i, result) {
                        var $container = $('#savedLists' + result.record_id);
                        var $ul = $container.children('ul:first');
                        if ($ul.length == 0) {
                            $container.append('<ul></ul>');
                            $ul = $container.children('ul:first');
                        }
                        var html = '<li><a href="' + path + '/MyResearch/MyList/' + result.list_id + '">' 
                                 + result.list_title + '</a></li>';
                        $ul.append(html);
                        $container.show();
                    });
                }
            }
        });
    }
}
