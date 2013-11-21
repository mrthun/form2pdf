$(function(){

    var questions = $('#questions');

    function refreshSelects(){
        var selects = questions.find('select');

        // Improve the selects with the Chose plugin
        selects.chosen();

        // Listen for changes
        selects.unbind('change').bind('change',function(){

            // The selected option
            var selected = $(this).find('option').eq(this.selectedIndex);
            // Look up the data-connection attribute
            var connection = selected.data('connection');

            // Removing the li containers that follow (if any)
            selected.closest('#questions li').nextAll().remove();

            if(connection){
                fetchSelect(connection);
            }

        });
    }

    var working = false;

    function fetchSelect(val){

        if(working){
            return false;
        }
        working = true;

        $.getJSON('ajax.php',{key:val},function(r){

            var connection, options = '';

            $.each(r.items,function(k,v){
                connection = '';
                if(v){
                    connection = 'data-connection="'+v+'"';
                }

                options+= '<option value="'+k+'" '+connection+'>'+k+'</option>';
            });

            if(r.defaultText){

                // The chose plugin requires that we add an empty option
                // element if we want to display a "Please choose" text

                options = '<option></option>'+options;
            }

            // Building the markup for the select section

            $('<li>\
                <p>'+r.title+'</p>\
                <select data-placeholder="'+r.defaultText+'">\
                    '+ options +'\
                </select>\
                <span class="divider"></span>\
            </li>').appendTo(questions);

            refreshSelects();

            working = false;
        });

    }

    $('#preloader').ajaxStart(function(){
        $(this).show();
    }).ajaxStop(function(){
        $(this).hide();
    });

    // Initially load the product select
    fetchSelect('productSelect');
});