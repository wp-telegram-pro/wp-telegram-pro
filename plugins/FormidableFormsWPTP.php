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
            <th colspan="2" class="title-with-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 599.68 601.37" width="20" height="20">
                    <path fill="#333" d="M289.6 384h140v76h-140z"/>
                    <path fill="#333"
                          d="M400.2 147h-200c-17 0-30.6 12.2-30.6 29.3V218h260v-71zM397.9 264H169.6v196h75V340H398a32.2 32.2 0 0 0 30.1-21.4 24.3 24.3 0 0 0 1.7-8.7V264zM299.8 601.4A300.3 300.3 0 0 1 0 300.7a299.8 299.8 0 1 1 511.9 212.6 297.4 297.4 0 0 1-212 88zm0-563A262 262 0 0 0 38.3 300.7a261.6 261.6 0 1 0 446.5-185.5 259.5 259.5 0 0 0-185-76.8z"/>
                </svg>
                <?php _e('Formidable Forms', $this->plugin_key) ?>
            </th>
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
                <label for="formidable_forms_select"><?php _e('Notification from this forms', $this->plugin_key) ?></label>
            </td>
            <td>
                <?php
                self::forms_dropdown(
                    'formidable_forms_select[]',
                    array(
                        'blank' => __('All', $this->plugin_key),
                        'field_id' => 'formidable_forms_select',
                        'multiple' => 'multiple',
                        'selected' => $this->get_option('formidable_forms_select', []),
                        'class' => 'multi_select_none_wptp',
                    )
                );
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
        $forms_select = $this->get_option('formidable_forms_select', []);
        if (count($forms_select) && $forms_select[0] != '' && !in_array($form_id, $forms_select))
            return;

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
            $keyboard = array(array(
                array(
                    'text' => '👁️',
                    'url' => admin_url('admin.php?page=formidable-entries&frm_action=show&id=' . $entry_id)
                ),
                array(
                    'text' => '📂',
                    'url' => admin_url('admin.php?page=formidable-entries')
                )
            ));
            $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');

            foreach ($users as $user) {
                $this->telegram->sendMessage($text, $keyboards, $user['user_id'], 'Markdown');
            }
        }
    }

    public static function forms_dropdown($field_name, $args = array())
    {
        $defaults = array(
            'blank' => true,
            'field_id' => false,
            'onchange' => false,
            'exclude' => false,
            'multiple' => false,
            'selected' => 0,
            'class' => '',
            'inc_children' => 'exclude',
        );
        $args = wp_parse_args($args, $defaults);

        if (!$args['field_id']) {
            $args['field_id'] = $field_name;
        }

        $query = array();
        if ($args['exclude']) {
            $query['id !'] = $args['exclude'];
        }

        $where = apply_filters('frm_forms_dropdown', $query, $field_name);
        $forms = FrmForm::get_published_forms($where, 999, $args['inc_children']);
        $add_html = array();
        FrmFormsHelper::add_html_attr($args['onchange'], 'onchange', $add_html);
        FrmFormsHelper::add_html_attr($args['class'], 'class', $add_html);
        FrmFormsHelper::add_html_attr($args['multiple'], 'multiple', $add_html);

        ?>
        <select name="<?php echo esc_attr($field_name); ?>"
                id="<?php echo esc_attr($args['field_id']); ?>"
            <?php echo wp_strip_all_tags(implode(' ', $add_html)); // WPCS: XSS ok.
            ?>>
            <?php if ($args['blank']) { ?>
                <option value="" <?php echo($args['selected'] == '' || is_array($args['selected']) && $args['selected'][0] == '' ? 'selected' : '') ?>><?php echo ($args['blank'] == 1) ? ' ' : '- ' . esc_attr($args['blank']) . ' -'; ?></option>
            <?php } ?>
            <?php foreach ($forms as $form) {
                $selected = false;
                if ($args['selected']) {
                    if (is_array($args['selected']))
                        $selected = in_array($form->id, $args['selected']);
                    else
                        $selected = $form->id == $args['selected'];
                }
                ?>
                <option value="<?php echo esc_attr($form->id); ?>" <?php selected($selected, true); ?>>
                    <?php echo esc_html('' === $form->name ? __('(no title)', 'formidable') : FrmAppHelper::truncate($form->name, 50) . ($form->parent_form_id ? ' ' . __('(child)', 'wp-telegram-pro') : '')); ?>
                </option>
            <?php } ?>
        </select>
        <?php
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