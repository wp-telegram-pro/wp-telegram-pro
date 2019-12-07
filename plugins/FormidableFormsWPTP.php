<?php
if (!defined('ABSPATH')) exit;
global $FormidableFormsWPTP;

class FormidableFormsWPTP extends WPTelegramPro
{
    public static $instance = null;

    public function __construct()
    {
        parent::__construct(true);
        add_action('wptelegrampro_plugins_settings_content', [$this, 'settings_content']);

        if ($this->get_option('formidable_new_message_notification', false)) {
            add_action('frm_after_create_entry', [$this, 'formidable_submit'], 10, 2);
        }
    }

    function settings_content()
    {
        $this->options = get_option($this->plugin_key);
        ?>
        <tr>
            <th colspan="2"><?php _e('Formidable Forms', $this->plugin_key) ?></th>
        </tr>
        <tr>
            <td>
                <label for="formidable_new_message_notification"><?php _e('Notification for new message', $this->plugin_key) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="formidable_new_message_notification"
                              name="formidable_new_message_notification" <?php checked($this->get_option('formidable_new_message_notification', 0), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
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
     * Send notification to admin users when new message from Formidable Forms
     *
     * @param int $entry_id
     * @param int $form_id
     */
    function formidable_submit($entry_id, $form_id)
    {
        $defaults = array(
            'id' => $entry_id,
            'entry' => false,
            'fields' => false,
            'plain_text' => true,
            'user_info' => false,
            'include_blank' => true,
            'default_email' => false,
            'form_id' => false,
            'format' => 'text',
            'array_key' => 'key',
            'direction' => 'ltr',
            'font_size' => '',
            'text_color' => '',
            'border_width' => '',
            'border_color' => '',
            'bg_color' => '',
            'alt_bg_color' => '',
            'class' => '',
            'clickable' => false,
            'exclude_fields' => '',
            'include_fields' => '',
            'include_extras' => '',
            'inline_style' => 1,
            'child_array' => false, // return embedded fields as nested array
        );

        $entry_formatter = FrmEntryFactory::entry_formatter_instance($defaults);
        $text = $entry_formatter->get_formatted_entry_values();
        $text = "*" . __('New message', $this->plugin_key) . "*\n\n" . $text;

        $text = apply_filters('wptelegrampro_formidable_message_notification_text', $text, $entry_id, $form_id);

        if (!$text) return;

        $users = $this->get_users();
        if ($users) {
            $keyboards = null;

            foreach ($users as $user) {
                $this->telegram->sendMessage($text, $keyboards, $user['user_id'], 'Markdown');
            }
        }
    }

    /**
     * Returns an instance of class
     * @return FormidableFormsWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new FormidableFormsWPTP();
        return self::$instance;
    }
}

$FormidableFormsWPTP = FormidableFormsWPTP::getInstance();