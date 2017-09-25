jQuery(function ($) {
    $(".mb-tab").on('click', function (e) {
        e.preventDefault();
        var ids = $(this).attr('id');
        $('.mb-tab-content').hide();
        $(".mb-tab").removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('#' + ids + 'Content').show();
    });
});