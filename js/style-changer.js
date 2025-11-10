/*
******************************
CSS Style changer with JQuery
Version: 1.0
Author Name: RoyHridoy
Author URI: https://github.com/RoyHridoy
Project URI: https://github.com/RoyHridoy/CSS-Style-Changer-with-JQuery

******************************
*/

$(document).ready(function(){
    $('.style-box-control button').on('click', function(){
        $('.style-changer').toggleClass('open');
    });
    $('.style-changer-box button').on('click', function(){
        var stylesheet = $(this).data('file');
        $('link[data-style="styles"]').attr('href','css/color/' + stylesheet +'.css');
        $(this).attr('disabled', 'disabled').addClass('disabled');
        $(this).siblings('button').removeAttr('disabled').removeClass('disabled');
    });
});
