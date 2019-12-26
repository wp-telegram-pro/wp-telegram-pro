<?php

namespace wptelegrampro;

use HM\BackUpWordPress\Service;
use HM\BackUpWordPress\Services;
use HM\BackUpWordPress\Backup;

/**
 * Telegram notifications for backups
 *
 * @extends Service
 */
class BackUpWordPressServiceWPTP extends Service
{
    /**
     * Human readable name for this service
     * @var string
     */
    public $name = 'WP Telegram Pro Integration';

    /**
     * Output the checkbox form field
     *
     * @access  public
     */
    public function field()
    {
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($this->get_field_name('notification')); ?>"><?php _e('Telegram', WPTELEGRAMPRO_PLUGIN_KEY); ?></label>
            </th>
            <td>
                <label><input type="checkbox" value="1"
                              id="<?php echo esc_attr($this->get_field_name('notification')); ?>"
                              name="<?php echo esc_attr($this->get_field_name('notification')); ?>" <?php checked($this->get_field_value('notification'), 1) ?>> <?php _e('Notification', WPTELEGRAMPRO_PLUGIN_KEY) ?>
                </label><br>
                <label><input type="checkbox" value="1"
                              id="<?php echo esc_attr($this->get_field_name('attache')); ?>"
                              name="<?php echo esc_attr($this->get_field_name('attache')); ?>" <?php checked($this->get_field_value('attache'), 1) ?>> <?php _e('Attache backup file', WPTELEGRAMPRO_PLUGIN_KEY) ?>
                </label>
            </td>
        </tr>
    <?php }

    /**
     * Not used as we only need a field
     *
     * @return string Empty string
     * @see  field
     */
    public function form()
    {
        return '';
    }

    public static function constant()
    {
    }

    /**
     * The sentence fragment that is output as part of the schedule sentence
     *
     * @return string
     */
    public function display()
    {
        if ($this->is_service_active())
            return __('Telegram', WPTELEGRAMPRO_PLUGIN_KEY);
        return '';
    }

    /**
     * Used to determine if the attache file
     */
    public function is_attache_active()
    {
        return (bool)$this->get_field_value('attache');
    }

    /**
     * Used to determine if the service is in use or not
     */
    public function is_service_active()
    {
        return (bool)$this->get_field_value('notification');
    }

    /**
     * Validate the form and return an error if validation fails
     *
     * @param array &$new_data Array of new data, passed by reference.
     * @param array $old_data The data we are replacing.
     *
     * @return array|null      Null on success, array of errors if validation failed.
     */
    public function update(&$new_data, $old_data)
    {
        return null;
    }

    /**
     * Fire the telegram notification on the hmbkp_backup_complete
     *
     * @param string $action The action received from the backup
     * @param $backup Backup
     * @see  Backup::do_action
     */
    public function action($action, Backup $backup)
    {
        if ('hmbkp_backup_complete' === $action && $this->is_service_active())
            do_action('wptelegrampro_backupwordpress_plugin_new_backup', $backup, $this->is_attache_active());
    }

    public static function intercom_data()
    {
        return array();
    }

    public static function intercom_data_html()
    {
    }
}

// Register the service
Services::register(__FILE__, 'wptelegrampro\BackUpWordPressServiceWPTP');
