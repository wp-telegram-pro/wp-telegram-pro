/**
 * @copyright This code from "DoLogin Security" WordPress plugin
 * https://wordpress.org/plugins/dologin
 * */

document.addEventListener('DOMContentLoaded', function () {
    jQuery(document).ready(function ($) {
        var wptp_can_submit_user = '';
        var wptp_can_submit_bypass = false;

        function wptp_login_tfa(e) {
            var wptp_user_handler = '#user_login';
            if ($(this).find('#username').length) {
                wptp_user_handler = '#username';
            }

            if (wptp_can_submit_user && wptp_can_submit_user == $(wptp_user_handler).val()) {
                return true;
            }

            if (wptp_can_submit_bypass) {
                return true;
            }

            e.preventDefault();

            $('#wptp-login-process').show();
            $('#wptp-process-msg').attr('class', 'wptp-spinner').html('');

            // Append the submit button for 2nd time submission
            var submit_btn = $(this).find('[type=submit]').first();
            if (!$(this).find('[type="hidden"][name="' + submit_btn.attr('name') + '"]').length) {
                $(this).append('<input type="hidden" name="' + submit_btn.attr('name') + '" value="' + submit_btn.val() + '" />');
            }

            var that = this;

            $.ajax({
                url: wptelegrampro_login.login_url,
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (!res._res) {
                        $('#wptp-process-msg').attr('class', 'wptp-err').html(res._msg);
                        $('#wptp-two_factor_code').attr('required', false);
                        $('#wptp-dynamic_code').hide();
                    } else {
                        // If no phone set in profile
                        if ('bypassed' in res) {
                            wptp_can_submit_bypass = true;
                            $(that).submit();
                            return;
                        }
                        $('#wptp-process-msg').attr('class', 'wptp-success').html(res.info);
                        $('#wptp-dynamic_code').show();
                        $('#wptp-two_factor_code').attr('required', true);
                        wptp_can_submit_user = $(wptp_user_handler).val();
                    }
                }
            });
        }

        if ($('#loginform').length > 0)
            $('#loginform').submit(wptp_login_tfa);

        if ($('.woocommerce-form-login').length > 0)
            $('.woocommerce-form-login').submit(wptp_login_tfa);

        // $('.tml-login form[name="loginform"], .tml-login form[name="login"], #wpmem_login form, form#ihc_login_form').submit( wptp_login_tfa );
    });
});