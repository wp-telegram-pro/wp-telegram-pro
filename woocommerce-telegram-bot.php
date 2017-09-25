<?php
/*
Plugin Name: WooCommerce Telegram Bot
Plugin URI: http://parsa.ws
Description: Telegram bot for WooCommerce
Author: Parsa Kafi
Version: 1.0
Author URI: http://parsa.ws
*/

define('WCTB_INC_PATH', str_replace('/', DIRECTORY_SEPARATOR, plugin_dir_path(__FILE__)) . 'inc' . DIRECTORY_SEPARATOR);
require_once WCTB_INC_PATH . 'TelegramWCTB.php';

class WooCommerceTelegramBot
{
    protected $plugin_key = 'woocommerce-telegram-bot';
    protected $telegram;
    protected $options;
    public $const_;

    public function __construct()
    {
        $this->const_ = array(
            'next' => __('Next', $this->plugin_key),
            'prev' => __('Previous', $this->plugin_key),
            'next_page' => __('Next Page', $this->plugin_key),
            'prev_page' => __('Previous Page', $this->plugin_key),
            'back' => __('Back', $this->plugin_key)
        );
        $this->options = get_option($this->plugin_key);
        $this->telegram = new TelegramWCTB($this->get_option('api_token'), $this->const_);
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
    }

    function set_const()
    {

    }

    function enqueue_scripts()
    {
        $version = rand(100, 200) . rand(200, 300);
        wp_register_script('wctb-js', plugin_dir_url(__FILE__) . 'assets/js/wctb.js', array('jquery'), $version);
        wp_enqueue_script('wctb-js');
        wp_enqueue_style('wctb-css', plugin_dir_url(__FILE__) . 'assets/css/wctb.css', array(), $version, false);
    }

    function menu()
    {
        add_options_page(__('WooCommerce Telegram Bot', $this->plugin_key), __('WooCommerce Telegram Bot', $this->plugin_key), 'manage_options', $this->plugin_key, array($this, 'settings'));
    }

    function message($message, $type = 'updated')
    {
        return '<div id="setting-error-settings_updated" class="' . $type . ' settings-error notice is-dismissible" ><p><strong>' . $message . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __('Dismiss this notice.') . '</span></button></div> ';
    }

    function settings()
    {
        $update_message = '';
        if (isset($_POST['wsb_nonce_field']) && wp_verify_nonce($_POST['wsb_nonce_field'], 'settings_submit')) {
            unset($_POST['wsb_nonce_field']);
            unset($_POST['_wp_http_referer']);

            update_option($this->plugin_key, $_POST);
            $update_message = $this->message(__('Settings saved.'));

            if (isset($_POST['setWebhook']) && $this->telegram->setWebhook())
                $update_message .= $this->message(__('Set Webhook Successfully.'));
            elseif (isset($_POST['setWebhook']))
                $update_message .= $this->message(__('Set Webhook with Error!'), 'error');
        }

        $this->options = get_option($this->plugin_key);
        ?>
        <div class="wrap wctb-wrap">
            <h1 class="wp-heading-inline"><?php _e('WooCommerce Telegram Bot', $this->plugin_key) ?></h1>
            <?php echo $update_message; ?>
            <div class="nav-tab-wrapper">
                <a id="TabMB1" class="mb-tab nav-tab nav-tab-active"><?php _e('Global', $this->plugin_key) ?></a>
                <a id="TabMB2" class="mb-tab nav-tab">قالب</a>
                <a id="TabMB3" class="mb-tab nav-tab">مدیریت</a>
                <a id="TabMB4" class="mb-tab nav-tab">صفحه دانلود</a>
                <a id="TabMB5" class="mb-tab nav-tab">صفحه دانلود سایت</a>
                <a id="TabMB6" class="mb-tab nav-tab">شبکه‌های اجتماعی</a>
            </div>
            <form action="" method="post">
                <?php wp_nonce_field('settings_submit', 'wsb_nonce_field'); ?>
                <div id="TabMB1Content" class="mb-tab-content">
                    <table>
                        <tr>
                            <td width="200"><?php _e('Telegram Api Token', $this->plugin_key) ?></td>
                            <td><input type="text" name="api_token" value="<?php echo $this->get_option('api_token') ?>"
                                       class="regular-text ltr"></td>
                        </tr>
                        <tr>
                            <td><?php _e('Telegram Set Webhook', $this->plugin_key) ?></td>
                            <td><label><input type="checkbox" value="1"
                                              name="setWebhook"> <?php _e('Set Webhook', $this->plugin_key) ?></label>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"><?php _e('Messages', $this->plugin_key) ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Start Command<br>(Welcome Message)', $this->plugin_key) ?></td>
                            <td>
                            <textarea name="start_command_m" cols="50"
                                      rows="4"><?php echo $this->get_option('start_command_m') ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td><br><input type="submit" class="button float-button button-primary"
                                           value="<?php _e('Save') ?>"></td>
                        </tr>
                    </table>
                </div>
                <div id="TabMB2Content" class="mb-tab-content hidden">
                    3
                </div>

            </form>
        </div>
        <?php
    }

    function get_option($key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : '';
    }

    function init()
    {
        $data = file_get_contents('php://input');

        if ($data) {
            $json = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($json['message']['from']['id']))
                return;
            $user_text = $json['message']['text'];
            $chat_id = $json['message']['from']['id'];
            $text = "";
            if ($user_text == '/start') {
                $this->telegram->run('sendMessage', array('chat_id' => $chat_id, 'text' => $this->get_option('start_command_m')));
            } elseif ($user_text == '765') {
                $this->telegram->run('sendMessage', array('chat_id' => $chat_id, 'text' => "Button pressed"));
            } else {
                $output = json_decode(file_get_contents('php://input'), TRUE);
                $callback_query = $output["callback_query"];
                $data = $callback_query["data"];
                $this->telegram->run('sendMessage', array('chat_id' => $chat_id, 'text' => $data));

                $keyboard = [
                    'inline_keyboard' => [[['text' => 'test', 'callback_data' => '765']],
                        [['text' => 's', 'callback_data' => '545']]]
                ];
                $keyboard = json_encode($keyboard, true);
                $keyboard = $this->telegram->keyboard();
                $this->telegram->run('sendMessage', array('chat_id' => $chat_id, 'reply_markup' => $keyboard, 'text' => $this->get_option('start_command_m')));
            }
            exit;
        }
    }

}

new WooCommerceTelegramBot();