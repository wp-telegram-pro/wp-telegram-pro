jQuery(function ($) {
    $(".mb-tab").on('click', function () {
        var ids = $(this).attr('id');
        $('.mb-tab-content').hide();
        $(".mb-tab").removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('#' + ids + 'Content').show();
    });
});