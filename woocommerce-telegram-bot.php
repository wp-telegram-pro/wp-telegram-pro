<?php
/*
Plugin Name: WooCommerce Telegram Bot
Plugin URI: http://parsa.ws
Description: Telegram bot for WooCommerce
Author: Parsa Kafi
Version: 1.0
Author URI: http://parsa.ws
*/

class WooCommerceTelegramBot
{
    protected $plugin_key = 'woocommerce-telegram-bot';
    protected $options;
    public $const_;

    public function __construct()
    {
        $this->const_ = array(
            'next' => "برگه بعدی \xE2\x97\x80",
            'prev' => "\xE2\x96\xB6 برگه قبلی",
            'back' => __('Back', $this->plugin_key)
        );
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
        $this->options = get_option($this->plugin_key);
    }

    function enqueue_scripts()
    {
        if (is_admin()) {
            $version = rand(100, 200) . rand(200, 300);
            wp_register_script('wctb', plugin_dir_url(__FILE__) . 'assets/js/wctb.js', array('jquery'), $version);
            wp_enqueue_script('wctb');
            wp_enqueue_style('wctb', plugin_dir_url(__FILE__) . 'assets/css/wctb.css', array(), $version, false);
        }
    }

    function menu()
    {
        add_options_page(__('WooCommerce Telegram Bot', $this->plugin_key), __('WooCommerce Telegram Bot', $this->plugin_key), 'manage_options', $this->plugin_key, array($this, 'settings'));
    }

    function settings()
    {
        $update_message = '';
        if (isset($_POST['wsb_nonce_field']) && wp_verify_nonce($_POST['wsb_nonce_field'], 'settings_submit')) {
            unset($_POST['wsb_nonce_field']);
            unset($_POST['_wp_http_referer']);

            update_option($this->plugin_key, $_POST);
            $update_message = '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible" ><p><strong>' . __('Settings saved.') . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __('Dismiss this notice.') . '</span></button></div> ';

            if (isset($_POST['setWebhook']) && $this->setWebhook())
                $update_message .= '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible" ><p><strong>' . __('Set Webhook Successfully.') . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __('Dismiss this notice.') . '</span></button></div> ';
            elseif (isset($_POST['setWebhook']))
                $update_message .= '<div id="setting-error-settings_updated" class="error settings-error notice is-dismissible" ><p><strong>' . __('Set Webhook with Error!') . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __('Dismiss this notice.') . '</span></button></div> ';
        }

        $this->options = get_option($this->plugin_key);
        ?>
        <div class="wrap wctb-wrap">
            <h1 class="wp-heading-inline"><?php _e('WooCommerce Telegram Bot', $this->plugin_key) ?></h1>
            <?php echo $update_message; ?>
            <div class="nav-tab-wrapper">
                <a href="javascript: void(0)" id="TabMB1" class="mb-tab nav-tab nav-tab-active">عمومی</a>
                <a href="javascript: void(0)" id="TabMB2" class="mb-tab nav-tab">قالب</a>
                <a href="javascript: void(0)" id="TabMB3" class="mb-tab nav-tab">مدیریت</a>
                <a href="javascript: void(0)" id="TabMB4" class="mb-tab nav-tab">صفحه دانلود</a>
                <a href="javascript: void(0)" id="TabMB5" class="mb-tab nav-tab">صفحه دانلود سایت</a>
                <a href="javascript: void(0)" id="TabMB6" class="mb-tab nav-tab">شبکه‌های اجتماعی</a>
            </div>
            <form action="" method="post">
                <div id="TabMB1Content" class="mb-tab-content">
                    2
                </div>
                <div id="TabMB2Content" class="mb-tab-content hidden">
                    3
                </div>
                <?php wp_nonce_field('settings_submit', 'wsb_nonce_field'); ?>
                <table>
                    <tr>
                        <td width="200"><?php _e('Telegram Api Token', $this->plugin_key) ?></td>
                        <td><input type="text" name="api_token" value="<?php echo $this->get_option('api_token') ?>"
                                   class="regular-text ltr"></td>
                    </tr>
                    <tr>
                        <td><?php _e('Telegram Set Webhook', $this->plugin_key) ?></td>
                        <td><label><input type="checkbox" value="1"
                                          name="setWebhook"> <?php _e('Set Webhook', $this->plugin_key) ?></label></td>
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
            </form>
        </div>
        <?php
    }

    function get_option($key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : '';
    }

    function run($method, $parameter = array())
    {
        $token = $this->get_option('api_token');
        if (empty($token))
            return false;

        $url = 'https://api.telegram.org/bot' . $token . '/' . $method . '?' . http_build_query($parameter);
        return file_get_contents($url);
    }

    function setWebhook()
    {
        $result = $this->run('setWebhook', array('url' => get_bloginfo('url') . '/'));
        if (!$result)
            return false;
        $json = json_decode($result, true);
        return $json['result'];
    }

    function sendMessage($chat_id, $message, $replyMarkup)
    {
        $access_token = $this->get_option('api_token');
        $api = 'https://api.telegram.org/bot' . $access_token;
        file_get_contents($api . '/sendMessage?chat_id=' . $chat_id . '&text=' . urlencode($message) . '&reply_markup=' . $replyMarkup);
    }

    function init()
    {
        /*$access_token = $this->get_option('api_token');
        $api = 'https://api.telegram.org/bot' . $access_token;
        $output = json_decode(file_get_contents('php://input'), TRUE);
        $chat_id = $output['message']['chat']['id'];
        $first_name = $output['message']['chat']['first_name'];
        $message = $output['message']['text'];
        $callback_query = $output['callback_query'];
        $data = $callback_query['data'];
        $message_id = ['callback_query']['message']['message_id'];
        switch ($message) {
            case '/test':
                $inline_button1 = array("text" => "Google url", "url" => "http://google.com");
                $inline_button2 = array("text" => "work plz", "callback_data" => '/plz');
                $inline_keyboard = [[$inline_button1, $inline_button2]];
                $keyboard = array("inline_keyboard" => $inline_keyboard);
                $replyMarkup = json_encode($keyboard);
                $this->sendMessage($chat_id, "ok", $replyMarkup);
                break;
        }
        switch ($data) {
            case '/plz':
                $this->sendMessage($chat_id, "plz", "");
                break;
            default:
                $this->sendMessage($chat_id, json_encode($output), "");
        }
        exit;
        return;*/

        $data = file_get_contents('php://input');

        if ($data) {
            $json = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($json['message']['from']['id']))
                return;
            $user_text = $json['message']['text'];
            $chat_id = $json['message']['from']['id'];
            $text = "";
            if ($user_text == '/start')
                $this->run('sendMessage', array('chat_id' => $chat_id, 'text' => $this->get_option('start_command_m')));
            elseif ($user_text == '765') {
                $this->run('sendMessage', array('chat_id' => $chat_id, 'text' => "Button pressed"));
            } else {
                $output = json_decode(file_get_contents('php://input'), TRUE);
                $callback_query = $output["callback_query"];
                $data = $callback_query["data"];
                $this->run('sendMessage', array('chat_id' => $chat_id, 'text' => $data));

                $keyboard = [
                    'inline_keyboard' => [[['text' => 'test', 'callback_data' => '765']],
                        [['text' => 's', 'callback_data' => '545']]]
                ];
                $keyboard = json_encode($keyboard, true);
                $keyboard = $this->keyboard();
                $this->run('sendMessage', array('chat_id' => $chat_id, 'reply_markup' => $keyboard, 'text' => $this->get_option('start_command_m')));
            }
            exit;
        }
    }

    function keyboard($type = 'keyboard')
    {
        /*$replyMarkup = array(
            'keyboard' => array(
                array("A", "B")
            )
        );
        return json_encode($replyMarkup);*/
        $reply = array();
        $keyboard = array(array($this->const_['next'], $this->const_['prev'], $this->const_['back']));
        $reply['keyboard'] = $keyboard;
        $reply['resize_keyboard'] = true;
        return json_encode($reply, true);
    }
}

new WooCommerceTelegramBot();