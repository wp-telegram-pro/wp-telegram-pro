<?php
/**
 * Users class
 *
 * @since 1.0
 */

namespace wptelegrampro;

defined('ABSPATH') || exit;

class Users extends Instance
{
    protected static $_instance;
    protected $WPTelegramPro;

    public function init()
    {
        $this->WPTelegramPro = WPTelegramPro::getInstance();
        if ($this->WPTelegramPro->get_option('telegram_bot_two_factor_auth', false)) {
            add_action('login_message', [$this, 'login_message']);
            add_action('login_enqueue_scripts', [$this, 'login_enqueue_scripts']);
            add_action('login_form', [$this, 'login_form']);
            add_action('woocommerce_login_form', [$this, 'login_enqueue_scripts']);
            add_action('woocommerce_login_form', [$this, 'login_form']);
        }
    }

    /**
     * Enqueue js
     *
     * @since  1.0
     * @access public
     */
    public function login_enqueue_scripts()
    {
        $this->enqueue_style();

        wp_register_script('wptp-login', WPTELEGRAMPRO_URL . '/assets/js/login.js', array('jquery'), WPTELEGRAMPRO_VERSION, false);

        $localize_data = array();
        $localize_data['login_url'] = get_rest_url(null, 'wptelegrampro/v1/telegram_bot_auth');
        wp_localize_script('wptp-login', 'wptelegrampro_login', $localize_data);

        wp_enqueue_script('wptp-login');
    }

    /**
     * Load style
     * @since 1.0
     */
    public function enqueue_style()
    {
        wp_enqueue_style('wptp-login', WPTELEGRAMPRO_URL . '/assets/css/login.css', array(), WPTELEGRAMPRO_VERSION, 'all');
        wp_enqueue_style('wptp-icon', WPTELEGRAMPRO_URL . '/assets/css/icon.css', array('wptp-login'), WPTELEGRAMPRO_VERSION, 'all');
    }

    /**
     * Display login form
     *
     * @since  1.0
     * @access public
     */
    public function login_form()
    {
        echo '	<p id="dologin-process">
						Dologin Security:
						<span id="dologin-process-msg"></span>
					</p>
					<p id="dologin-dynamic_code">
						<label for="dologin-two_factor_code">Dynamic Code</label>
						<br /><input type="text" name="dologin-two_factor_code" id="dologin-two_factor_code" autocomplete="off" />
					</p>
				';
    }

    /**
     * Login default display messages
     *
     * @since  1.0
     * @access public
     */
    public function login_message($msg)
    {
        if (defined('DOLOGIN_ERR'))
            return $msg;

        $msg .= '<div class="message wptp-login-message"><span class="dashicons-wptp-telegram"></span> <strong class="title">' . $this->WPTelegramPro->plugin_name .
            '</strong><br>'.__('Two step Telegram bot authentication is on', WPTELEGRAMPRO_PLUGIN_KEY).'</div>';
        return $msg;
    }
}
