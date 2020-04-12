jQuery(function ($) {
    const max_channel_wptp = 20;
    var wpDoAjax = false;

    $.fn.extend({
        change_item_index: function (new_index) {
            var in_name = $(this).attr('name');
            if (new_index === undefined) {
                new_index = 0;
                $(".item").each(function (index) {
                    var in_index = $(this).attr('data-index');
                    if (parseInt(in_index) > new_index)
                        new_index = parseInt(in_index);
                });
                new_index++;
            }
            in_name = in_name.replace(/\[.*\]/g, '[' + new_index + ']');
            $(this).attr('name', in_name);
            return new_index;
        }
    });

    $.fn.extend({
        insertAtCaret: function (myValue) {
            this.each(function () {
                if (document.selection) {
                    this.focus();
                    var sel = document.selection.createRange();
                    sel.text = myValue;
                    this.focus();
                } else if (this.selectionStart || this.selectionStart == '0') {
                    var startPos = this.selectionStart;
                    var endPos = this.selectionEnd;
                    var scrollTop = this.scrollTop;
                    this.value = this.value.substring(0, startPos) +
                        myValue + this.value.substring(endPos, this.value.length);
                    this.focus();
                    this.selectionStart = startPos + myValue.length;
                    this.selectionEnd = startPos + myValue.length;
                    this.scrollTop = scrollTop;
                } else {
                    this.value += myValue;
                    this.focus();
                }
            });
            return this;
        }
    });

    var reload_check = false;
    var publish_button_click = false;
    jQuery(document).ready(function ($) {
        if ($('.wptp-metabox').length > 0)
            add_publish_button_click = setInterval(function () {
                $publish_button = jQuery('.edit-post-header__settings .editor-post-publish-button');
                if ($publish_button && !publish_button_click) {
                    publish_button_click = true;
                    $publish_button.on('click', function () {
                        var reloader = setInterval(function () {
                            postsaving = wp.data.select('core/editor').isSavingPost();
                            autosaving = wp.data.select('core/editor').isAutosavingPost();
                            success = wp.data.select('core/editor').didPostSaveRequestSucceed();
                            console.log('Saving: ' + postsaving + ' - Autosaving: ' + autosaving + ' - Success: ' + success);
                            if (!postsaving) return;
                            clearInterval(reloader);

                            $('.wptp-metabox .item').each(function () {
                                var value = $(this).find(".send-to-channel:checked").val();
                                if (value == '1')
                                    $(this).find(".send-to-channel-no").prop("checked", true);
                            })
                        }, 1000);
                    });
                }
            }, 1000);

        $('#proxy-wptp-tab-content input[type=radio][name=proxy_status]').unbind('change').change(function () {
            $('.proxy-status-wptp').hide();
            $('#proxy_' + this.value).show();
        });

        function wptp_init() {
            $('.accordion-wptp .toggle').unbind('click').on('click', function () {
                var $this = $(this);
                $(".accordion-wptp .toggle").each(function (index) {
                    if ($(this).parent().index() != $this.parent().index())
                        $(this).removeClass('active').parent().find('.panel').slideUp();
                });
                $(this).toggleClass('active').parent().find('.panel').slideToggle(500, "swing", function () {
                    $('html, body').animate({
                        scrollTop: $(this).closest('.item').offset().top - 50
                    }, 500);
                });
            });

            $('input.channel-username-wptp').unbind('input').on('input', function (e) {
                if ($(this).val().length == 0) {
                    title = wptp.new_channel;
                    $(this).parent().find('.channel-info-wptp').hide();
                } else {
                    title = '@' + $(this).val();
                    $(this).parent().find('.channel-info-wptp').show();
                }
                $(this).closest('.item').find('.toggle').html(title);
            });

            $('.remove-channel-wptp').unbind('click').on('click', function () {
                var message = wptp.confirm_remove_channel;
                var channel_username = $(this).parent().find('.channel-username-wptp').val();
                if ($.trim(channel_username).length > 0)
                    message = message.replace('%', '@' + channel_username);
                else
                    message = message.replace('%', '');
                var r = confirm(message);
                if (r == true) {
                    if ($('.channel-list-wptp .item').length === 1)
                        add_channel_item(false);
                    $(this).closest('.item').remove();
                    if ($('.channel-list-wptp .item').length < max_channel_wptp)
                        $('.channel-list-wptp .add-channel').prop('disabled', false);
                }
            });

            $('.patterns-select-wptp').unbind('change').change(function () {
                var $tcp = $(this).parent().find('div.message-pattern-wptp > .emojionearea-editor');
                if ($tcp.length == 0) {
                    var $tcp = $(this).parent().find('textarea.message-pattern-wptp');
                    $tcp.insertAtCaret($(this).val());
                } else
                    $tcp.html($tcp.html() + $(this).val()).caret('pos', $tcp.text().length).focus();
            });

            $(".wptp-wrap textarea.emoji").emojioneArea({
                autoHideFilters: true,
                pickerPosition: 'bottom',
                filtersPosition: 'bottom',
            });

            $('.channel-info-wptp').unbind('click').on('click', function () {
                var $this = $(this);
                $this.removeClass('dashicons-info channel-info-wptp').addClass('dashicons-update wptp-loader');
                $this.parent().find('.description').remove();
                var channel_username = $(this).parent().find('.channel-username-wptp').val();
                if ($.trim(channel_username).length > 0 && !wpDoAjax) {
                    wpDoAjax = true;
                    var data = {
                        'action': 'channel_members_count_wptp',
                        'channel_username': channel_username
                    };
                    $.post(ajaxurl, data, function (response) {
                        $this.parent().append('<div class="description">' + response + '</div>');
                        $this.removeClass('dashicons-update wptp-loader').addClass('dashicons-info channel-info-wptp');
                        wpDoAjax = false;
                    });
                }
            });

            $('.quick-send-channel-wptp').unbind('click').on('click', function () {
                if (wpDoAjax) return;
                var $this = $(this);
                $this.removeClass('dashicons-wptp-telegram green-wptp red-wptp').addClass('dashicons dashicons-update wptp-loader');
                wpDoAjax = true;
                var data = {
                    'action': 'quick_send_channel_wptp',
                    'channel_username': $this.data('channel'),
                    'channel_index': $this.data('index'),
                    'post_id': $this.data('id')
                };
                $.post(ajaxurl, data, function (response) {
                    if (response && response.success)
                        $this.addClass('green-wptp');
                    else
                        $this.addClass('red-wptp');
                    $this.removeClass('dashicons dashicons-update wptp-loader').addClass('dashicons-wptp-telegram');
                    wpDoAjax = false;
                });
            });

            $('.multi_select_none_wptp').change(function () {
                if ($('option:first', this).is(':selected')) {
                    $('option:not(:first)', this).prop('selected', false);
                }
            });
        }

        function add_channel_item(accordion) {
            if (accordion === undefined)
                accordion = true;

            var item = $('.channel-list-wptp .item:last').clone();
            new_index = item.find('.channel-username-wptp').change_item_index();
            item.find('.channel_post_type').change_item_index(new_index);
            item.find('.send_to_channel').change_item_index(new_index);
            item.find('.message-pattern-wptp').change_item_index(new_index);
            item.find('.message-pattern-wptp').val("{title}\n{excerpt}\n\n{short-link}");
            item.find('.with_featured_image').change_item_index(new_index);
            item.find('.formatting_messages').change_item_index(new_index);
            item.find('.excerpt_length').change_item_index(new_index);
            item.find('.inline_button_title').change_item_index(new_index);
            item.find('.disable_web_page_preview').change_item_index(new_index);
            //item.find('.image_position').change_item_index(new_index);
            item.find('input[type="text"],input[type="number"],textarea').val('');
            //item.find('input:radio').eq(0).prop('checked', true);
            item.find('input[type="checkbox"]').prop('checked', true);
            item.find('.channel_post_type,.disable_web_page_preview').prop('checked', false);
            item.find('select option:eq(0)').prop('selected', true);
            item.find('.emojionearea-editor').html('')
            item.find('.accordion_wptp').removeClass('active').html(wptp.new_channel);
            item.find('.panel').hide();
            item.find('textarea').css('display');
            item.find('.emojionearea').remove();
            item.find('.channel-info-wptp').hide();
            item.attr('data-index', new_index).insertAfter('.channel-list-wptp .item:last')

            item.find(".channel-username-wptp").trigger("input");

            if ($('.channel-list-wptp .item').length >= max_channel_wptp)
                $(this).prop('disabled', true);
            wptp_init();

            item.find('.emojionearea-editor').html('{title}<div></div>{excerpt}<div><br></div>{short-link}');

            if (accordion) {
                item.find(".toggle").html(wptp.new_channel).trigger("click");
            }
        }

        // Tab
        $(".wptp-tab").on('click', function (e) {
            e.preventDefault();
            var ids = $(this).attr('id');
            $('.wptp-tab-content').hide();
            $(".wptp-tab").removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('#' + ids + '-content').show();
        });

        $('.bot-info-wptp').on('click', function () {
            var $this = $(this);
            var api_token = $(this).parent().find('.api-token').val();
            if ($.trim(api_token).length > 0 && !wpDoAjax) {
                wpDoAjax = true;
                $this.removeClass('dashicons-info bot-info-wptp').addClass('dashicons-update wptp-loader');
                $this.parent().find('.description').html(' ');
                var data = {'action': 'bot_info_wptp'};
                $.post(ajaxurl, data, function (response) {
                    $this.parent().append('<div class="description">' + response + '</div>');
                    $this.removeClass('dashicons-update wptp-loader').addClass('dashicons-info bot-info-wptp');
                    wpDoAjax = false;
                });
            }
        });

        $('.channel-list-wptp .add-channel').on('click', function () {
            add_channel_item(true);
        });

        setTimeout(function () {
            wptp_init();
        }, 1000);
    });
});