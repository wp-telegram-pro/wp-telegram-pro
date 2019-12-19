<?php
if (!defined('ABSPATH')) exit;
global $WPUserAvatarWPTP;

class WPUserAvatarWPTP extends WPTelegramPro
{
    public static $instance = null;

    public function __construct()
    {
        parent::__construct(true);
        add_action('wptelegrampro_plugins_settings_content', [$this, 'settings_content']);

        if ($this->get_option('wpuseravatar_avatar_change_notification', false)) {
            add_action('update_user_meta', [$this, 'avatar_change_notification'], 10, 4);
        }
    }

    function settings_content()
    {
        $this->options = get_option($this->plugin_key);
        ?>
        <tr>
            <th colspan="2">
                <span><?php _e('WP User Avatar', $this->plugin_key) ?></span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="wpuseravatar_avatar_change_notification"><?php _e('Notification for user avatar change', $this->plugin_key) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="wpuseravatar_avatar_change_notification"
                              name="wpuseravatar_avatar_change_notification" <?php checked($this->get_option('wpuseravatar_avatar_change_notification', 0), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * Send notification to admin users when user avatar change
     *
     * @param int $meta_id ID of the metadata entry to update.
     * @param int $user_id User ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     */
    function avatar_change_notification($meta_id, $user_id, $meta_key, $meta_value)
    {
        global $blog_id, $wpdb;

        if (empty($meta_value) || !is_numeric($meta_value)) return;

        $key = $wpdb->get_blog_prefix($blog_id) . 'user_avatar';
        if ($key != $meta_key) return;

        $user = get_userdata($user_id);
        $user_role = $this->get_user_role($user);
        if (!$user_role || $user_role == 'administrator') return;

        $image_size = $this->get_option('image_size', 'medium');
        $image = wp_get_attachment_image_src($meta_value, $image_size);
        if (!$image) return;
        $mediaURL = $image[0];

        $text = "*" . __('User avatar change notification', $this->plugin_key) . "*\n\n";
        $text .= __('User Name', $this->plugin_key) . ': ' . $user->user_login . "\n";
        $text .= __('Date', $this->plugin_key) . ': ' . HelpersWPTP::localeDate() . "\n";
        $text = apply_filters('wptelegrampro_wpuseravatar_avatar_change_notification_text', $text, $meta_id, $user_id, $meta_key, $meta_value);

        if (!$text) return;

        $users = $this->get_users();
        if ($users) {
            $keyboard = array(array(
                array(
                    'text' => __('User Profile', $this->plugin_key),
                    'url' => admin_url('user-edit.php?user_id=' . $user_id)
                )
            ));
            $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');

            foreach ($users as $user)
                $this->telegram->sendFile('sendPhoto', $mediaURL, $text, $keyboards, $user['user_id'], 'Markdown');
        }
    }

    /**
     * Returns an instance of class
     * @return WPUserAvatarWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new WPUserAvatarWPTP();
        return self::$instance;
    }
}

$WPUserAvatarWPTP = WPUserAvatarWPTP::getInstance();