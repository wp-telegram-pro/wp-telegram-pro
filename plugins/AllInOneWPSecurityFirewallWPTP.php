<?php
if (!defined('ABSPATH')) exit;
global $AllInOneWPSecurityFirewallWPTP;

class AllInOneWPSecurityFirewallWPTP extends WPTelegramPro
{
    public static $instance = null;

    public function __construct()
    {
        parent::__construct(true);

        add_action('wptelegrampro_plugins_settings_content', [$this, 'settings_content']);

        if ($this->get_option('allinonewpsecurityfirewall_lock_user_notification', false)) {
            add_action('aiowps_lockdown_event', [$this, 'lock_user'], 10, 2);
        }
    }

    function settings_content()
    {
        $this->options = get_option($this->plugin_key);
        ?>
        <tr>
            <th colspan="2">
                <?php _e('All In One WP Security & Firewall', $this->plugin_key) ?>
            </th>
        </tr>
        <tr>
            <td>
                <?php _e('Notification for lock the user', $this->plugin_key); ?>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="allinonewpsecurityfirewall_lock_user_notification"
                              name="allinonewpsecurityfirewall_lock_user_notification" <?php checked($this->get_option('allinonewpsecurityfirewall_lock_user_notification', 0), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * Send notification to admin users when add log
     *
     * @param string $ip_range IP range
     * @param string $username User Name
     */
    function new_blacklisted_ip($ip_range, $username)
    {
        $text = "*" . __('All In One WP Security & Firewall lock the user', $this->plugin_key) . "*\n\n";

        if (is_email($username))
            $text .= __('Email', $this->plugin_key) . ': ' . $username . "\n";
        else
            $text .= __('User Name', $this->plugin_key) . ': ' . $username . "\n";

        $text .= __('IP range', $this->plugin_key) . ': ' . $ip_range . "\n";
        $text .= __('Date', $this->plugin_key) . ': ' . HelpersWPTP::localeDate() . "\n";
        $text = apply_filters('wptelegrampro_allinonewpsecurityfirewall_lock_user_notification_text', $text, $ip_range, $username);

        if (!$text) return;

        $users = $this->get_users();
        if ($users) {
            $keyboard = array(array(
                array(
                    'text' => __('All In One WP Security & Firewall Dashboard', $this->plugin_key),
                    'url' => admin_url('admin.php?page=aiowpsec')
                )
            ));
            $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');

            foreach ($users as $user) {
                $this->telegram->sendMessage($text, $keyboards, $user['user_id'], 'Markdown');
            }
        }
    }

    /**
     * Returns an instance of class
     * @return AllInOneWPSecurityFirewallWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new AllInOneWPSecurityFirewallWPTP();
        return self::$instance;
    }
}

$AllInOneWPSecurityFirewallWPTP = AllInOneWPSecurityFirewallWPTP::getInstance();