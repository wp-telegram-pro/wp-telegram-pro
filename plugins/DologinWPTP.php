<?php

namespace wptelegrampro;

use dologin\Data;
use dologin\IP;
use dologin\REST;
use dologin\s;
use WP_User;

if (!defined('ABSPATH')) exit;
global $DoLoginWPTP;

class DoLoginWPTP extends WPTelegramPro
{
    public static $instance = null;
    private $_dry_run = false;

    public function __construct()
    {
        parent::__construct();
        add_action('wptelegrampro_plugins_settings_content', [$this, 'settings_content']);

        if ($this->get_option('dologin_plugin_two_factor_auth', false)) {
            add_action('init', [$this, 'init_addon']);
            add_action('init', function () {
                $GLOBALS['wp_scripts'] = new FilterableScriptsWPTP;
            });
            add_filter('wptelegrampro_localize_script', [$this, 'localize_script'], 30, 3);
            add_filter('authenticate', [$this, 'authenticate'], 30, 3);
        }

        $this->words = apply_filters('wptelegrampro_words', $this->words);
    }

    function settings_content()
    {
        $this->options = get_option($this->plugin_key);
        ?>
        <tr>
            <th colspan="2">
                <span>DoLogin Security</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="dologin_plugin_two_factor_auth"><?php _e('Two factor auth with Telegram bot', $this->plugin_key) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="dologin_plugin_two_factor_auth"
                              name="dologin_plugin_two_factor_auth" <?php checked($this->get_option('dologin_plugin_two_factor_auth', 0), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                </label>
                <p class="description">
                    <?php _e('This feature replaces with DoLogin Two Step SMS Auth.', $this->plugin_key); ?>
                    <?php _e('(Requires Telegram Connectivity in WordPress tab)', $this->plugin_key); ?>
                </p>
            </td>
        </tr>
        <tr>
            <td>
                <label for="dologin_plugin_force_two_factor_auth"><?php _e('Force Telegram bot auth validation', $this->plugin_key) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="dologin_plugin_force_two_factor_auth"
                              name="dologin_plugin_force_two_factor_auth" <?php checked($this->get_option('dologin_plugin_force_two_factor_auth', 0), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                </label>
                <p class="description">
                    <?php _e('If enabled this, any user without linked Telegram account in profile will not be able to login.', $this->plugin_key); ?>
                    <?php
                    $bot_user = $this->set_user(array('wp_id' => get_current_user_id()));
                    if (!$bot_user)
                        echo '<div class="wptp-warning-h3">' . __('You need to setup your Telegram Connectivity before enabling this setting to avoid yourself being blocked from next time login.', $this->plugin_key) .
                            '<br><a href="profile.php#wptp">' . __('Click here to connect the WordPress profile to the Telegram account', $this->plugin_key) . '</a>' .
                            '</div>';
                    ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Init
     *
     * @since  1.0
     * @access public
     */
    public function init_addon()
    {
        add_action('rest_api_init', [$this, 'rest_api_init']);
    }

    /**
     * Register REST hooks
     *
     * @since  1.0
     * @access public
     */
    public function rest_api_init()
    {
        register_rest_route('dologin/v1', '/telegram_bot', array(
            'methods' => 'POST',
            'callback' => [$this, 'send'],
        ));
    }

    function localize_script($l10n, $handle, $object_name)
    {
        if ($object_name == 'dologin') {
            $l10n = array('login_url' => get_rest_url(null, 'dologin/v1/telegram_bot'));
        }
        return $l10n;
    }

    /**
     * Verify Telegram bot after u+p authenticated
     *
     * @since  1.3
     */
    public function authenticate($user, $username, $password)
    {
        global $wpdb;

        if ($this->_dry_run) {
            defined('debug') && debug('bypassed due to dryrun');
            return $user;
        }

        if (empty($username) || empty($password)) {
            defined('debug') && debug('bypassed due to lack of u/p');
            return $user;
        }

        if (is_wp_error($user)) {
            defined('debug') && debug('bypassed due to is_wp_error already');
            return $user;
        }

        // If telegram is optional and the user doesn't have linked account, bypass
        $bot_user = $this->set_user(array('wp_id' => $user->ID));
        if (!$bot_user) {
            defined('debug') && debug('no phone number set');
            if (!$this->get_option('dologin_plugin_force_two_factor_auth', false)) {
                defined('debug') && debug('bypassed due to no force_sms check');
                return $user;
            }
        }

        $error = new \WP_Error();

        // Validate dynamic code
        if (empty($_POST['dologin-two_factor_code'])) {
            $error->add('dynamic_code_missing', $this->words['dynamic_code_missing']);
            defined('DOLOGIN_ERR') || define('DOLOGIN_ERR', true);
            defined('debug') && debug('❌ Dynamic code is missing');
            return $error;
        }

        $tb_sms = Data::get_instance()->tb('sms');

        $q = "SELECT id, code FROM $tb_sms WHERE user_id = %d AND used = 0";
        $row = $wpdb->get_row($wpdb->prepare($q, array($user->ID)));

        if ($row->id) {
            $wpdb->query($wpdb->prepare("UPDATE $tb_sms SET used = 1 WHERE id = %d", array($row->id)));
        }

        if (!$row->code || $row->code != $_POST['dologin-two_factor_code']) {
            $error->add('dynamic_code_not_correct', $this->words['dynamic_code_not_correct']);
            defined('DOLOGIN_ERR') || define('DOLOGIN_ERR', true);
            defined('debug') && debug('❌ Dynamic code is wrong');
            return $error;
        }

        return $user;
    }

    /**
     * Send SMS
     *
     * @since  1.3
     */
    public function send()
    {
        global $wpdb;

        if (!$this->get_option('dologin_plugin_two_factor_auth', false)) {
            return REST::ok(array('bypassed' => 1));
        }

        $field_u = 'log';
        $field_p = 'pwd';
        if (isset($_POST['woocommerce-login-nonce'])) {
            $field_u = 'username';
            $field_p = 'password';
        }

        if (empty($_POST[$field_u]) || empty($_POST[$field_p])) {
            return REST::err($this->words['empty_u_p']);
        }

        // Verify u & p first
        $this->_dry_run = true;
        $user = wp_authenticate($_POST[$field_u], $_POST[$field_p]);
        $this->_dry_run = false;
        if (is_wp_error($user)) {
            return REST::err($user->get_error_message());
        }

        // Search if the user has linked Telegram account
        $bot_user = $this->set_user(array('wp_id' => $user->ID));
        if (!$bot_user) {
            if (!$this->get_option('dologin_plugin_force_two_factor_auth', false)) {
                defined('debug') && debug('bypassed due to no linked Telegram account');
                return REST::ok(array('bypassed' => 1));
            }
            return REST::err($this->words['no_linked_telegram_account']);
        }

        // Generate dynamic code
        $code = s::rrand(4, 1);
        $ip_info = ip::geo();
        $location = !empty($ip_info['country']) ? $ip_info['country'] : '';
        $location .= !empty($ip_info['city']) ? (!empty($location) ? '-' : '') . $ip_info['city'] : '';
        /* translators: 1: Code 2: Location */
        $message = sprintf(__('Dynamic Code: %1$s ,Location: %2$s.', $this->plugin_key), $code, $location);

        $tb_sms = Data::get_instance()->tb('sms');

        // Expire old ones
        $wpdb->query($wpdb->prepare("UPDATE $tb_sms SET used = -1 WHERE user_id = %d AND used = 0", array($user->ID)));

        // Save to db
        $s = array(
            'user_id' => $user->ID,
            'sms' => $message,
            'code' => $code,
            'used' => 0,
            'dateline' => time(),
        );
        $q = 'INSERT INTO ' . $tb_sms . ' ( ' . implode(',', array_keys($s)) . ' ) VALUES ( ' . implode(',', array_fill(0, count($s), '%s')) . ' )';
        $wpdb->query($wpdb->prepare($q, $s));
        $id = $wpdb->insert_id;

        // Send
        try {
            $result = $this->send_notification($bot_user, $message, $user);
        } catch (\Exception $ex) {
            return REST::err($ex->getMessage());
        }

        // Update log
        $wpdb->query($wpdb->prepare("UPDATE $tb_sms SET res = %s WHERE id = %d", array(json_encode($result), $id)));

        // Expected response
        if ($result && isset($result['ok']) && $result['ok']) {
            $usernameStar = HelpersWPTP::string2Stars($bot_user['username']);
            $message = sprintf(__('Sent dynamic code to this Telegram account @%s.', $this->plugin_key), $usernameStar);
            return REST::ok(array('info' => $message));
        }

        if ($result && isset($result['ok']) && !$result['ok']) {
            return REST::err($this->words['error_sending_message']);
        }

        return REST::err('Unknown error');
    }

    /**
     * Send dynamic code notification
     *
     * @param array $bot_user name.
     * @param string $message Message.
     * @param WP_User $user WordPress User.
     * @return bool|array
     */
    function send_notification($bot_user, $message, $user)
    {
        if ($bot_user) {
            $text = "*" . sprintf(__('Dear %s', $this->plugin_key), $user->display_name) . "*\n";
            $text .= $message;
            $text = apply_filters('wptelegrampro_dologin_plugin_two_factor_auth_notification_text', $text, $bot_user, $message, $user);

            if ($text) {
                $keyboard = array(array(
                    array(
                        'text' => __('Display website', $this->plugin_key),
                        'url' => get_bloginfo('url')
                    )
                ));
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                $this->telegram->sendMessage($text, $keyboards, $bot_user['user_id'], 'Markdown');
                return $this->telegram->get_last_result();
            }
        }
        return false;
    }

    /**
     * Returns an instance of class
     * @return DoLoginWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new DoLoginWPTP();
        return self::$instance;
    }
}

$DoLoginWPTP = DoLoginWPTP::getInstance();