<?php
if (!defined('ABSPATH')) exit;
global $WPFormsWPTP;

class WPFormsWPTP extends WPTelegramPro
{
    public static $instance = null;

    public function __construct()
    {
        parent::__construct(true);
        add_action('wptelegrampro_plugins_settings_content', [$this, 'settings_content']);

        if ($this->get_option('wpforms_new_message_notification', false)) {
            add_action('wpforms_process_complete', [$this, 'wpforms_submit'], 10, 4);
        }
    }

    function settings_content()
    {
        $this->options = get_option($this->plugin_key);
        ?>
        <tr>
            <th colspan="2"><?php _e('WPForms', $this->plugin_key) ?></th>
        </tr>
        <tr>
            <td>
                <label for="wpforms_new_message_notification"><?php _e('Notification for new message', $this->plugin_key) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="wpforms_new_message_notification"
                              name="wpforms_new_message_notification" <?php checked($this->get_option('wpforms_new_message_notification', 0), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="cf7_forms_select"><?php _e('Notification from this forms', $this->plugin_key) ?></label>
            </td>
            <td>
                <?php
                $this->post_type_select('wpforms_forms_select[]', 'wpforms', array('multiple' => true, 'selected' => $this->get_option('wpforms_forms_select', []), 'class' => 'multi_select_none_wptp', 'none_select' => __('All', $this->plugin_key)));
                ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Send notification to admin users when new message from WPForms
     *
     * @param array $fields
     * @param array $entry
     * @param array $form_data
     * @param int $entry_id
     */
    function wpforms_submit($fields, $entry, $form_data, $entry_id)
    {
        $forms_select = $this->get_option('wpforms_forms_select', []);
        if (count($forms_select) && $forms_select[0] != '' && !in_array($form_data['id'], $forms_select))
            return;

        $valid_type = array('name', 'radio', 'select', 'checkbox', 'number', 'email', 'text', 'textarea');

        $text = '';

        foreach ($fields as $field) {
            if (!in_array($field['type'], $valid_type))
                continue;
            $text .= $field['name'] . ': ';
            $value = $field['value'];

            if (!empty($value) && $field['type'] == 'checkbox') {
                $value_ = explode("\n", $value);
                if (count($value_) > 1)
                    $text .= "\n";
                $value_ = array_map(function ($v) {
                    return is_string($v) ? trim($v, "\n") : $v;
                }, $value_);
                $value = "▫️ " . implode("\n▫️ ", $value_);

            } elseif ($field['type'] == 'textarea') {
                $text .= "\n";
            }

            $text .= $value . "\n";
        }

        if ($text == '') return;

        $text = "*" . __('New message', $this->plugin_key) . "*\n\n" . $text;
        $text = apply_filters('wptelegrampro_wpforms_message_notification_text', $text, $fields, $entry, $form_data, $entry_id);

        if (!$text) return;

        $users = $this->get_users();
        if ($users) {
            $keyboards = null;

            if ($entry_id != 0) {
                $keyboard = array(array(
                    array(
                        'text' => '👁️',
                        'url' => admin_url('admin.php?page=wpforms-entries&view=details&entry_id=' . $entry_id)
                    ),
                    array(
                        'text' => '📂',
                        'url' => admin_url('admin.php?page=wpforms-entries')
                    )
                ));
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
            }

            foreach ($users as $user) {
                $this->telegram->sendMessage($text, $keyboards, $user['user_id'], 'Markdown');
            }
        }
    }

    /**
     * Returns an instance of class
     * @return WPFormsWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new WPFormsWPTP();
        return self::$instance;
    }
}

$WPFormsWPTP = WPFormsWPTP::getInstance();