<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $ForminatorWPTP;

class ForminatorWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'forminator_new_message_notification', false ) ) {
			add_action( 'forminator_custom_form_mail_admin_sent', [ $this, 'form_submit' ], 10, 5 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg"
                     xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="20" height="20"
                     viewBox="0 0 20 20" xml:space="preserve">
                        <g fill="#333">
                            <path class="st0" d="M13,3.3l-1-1v0c-0.2-0.2-0.4-0.2-0.6,0L4.6,9C4.5,9.1,4.5,9.2,4.5,9.4c0,0.1,0,0.2,0.1,0.3l1,1
            c0.1,0.1,0.2,0.1,0.3,0.1c0.1,0,0.2,0,0.3-0.1L13,3.9C13.2,3.7,13.2,3.5,13,3.3 M18.6,4.4l-1-1c-0.2-0.2-0.4-0.2-0.6,0l-9,9
            c-0.2,0.2-0.2,0.4,0,0.6l1,1c0.1,0.1,0.2,0.1,0.3,0.1c0.1,0,0.2,0,0.3-0.1l9-9C18.8,4.9,18.8,4.6,18.6,4.4 M3.9,12.8
            c-0.1-0.1-0.2-0.1-0.3-0.1c-0.1,0-0.2,0.1-0.2,0.2l-0.9,2.8c0,0.1,0,0.2,0.1,0.3c0.1,0.1,0.2,0.1,0.3,0.1l2.8-0.9v0
            c0.1,0,0.2-0.1,0.2-0.2c0-0.1,0-0.2-0.1-0.3L3.9,12.8L3.9,12.8z M16.7,8.9L15,10.6c-0.1,0.1-0.1,0.1-0.1,0.2
            c-0.5,2.8-3,4.9-5.8,4.9c-0.3,0-0.6,0-0.9-0.1c-0.1,0-0.3,0-0.4,0.1L7,16.5c-0.1,0.1-0.2,0.3-0.1,0.4c0,0.2,0.2,0.3,0.3,0.3
            c0.6,0.1,1.2,0.2,1.8,0.2c4.3,0,7.7-3.5,7.7-7.7c0-0.2,0-0.3,0-0.5L16.7,8.9L16.7,8.9z M9.6,2.1L7.8,3.9C7.7,4,7.6,4,7.5,4
            C5.2,4.6,3.1,6.8,3.1,9.6c0,0.3,0,0.6,0,0.9c0,0.1,0,0.3-0.1,0.4l-0.8,0.7c-0.1,0.1-0.3,0.2-0.4,0.1s-0.3-0.2-0.3-0.3
            c-0.1-0.6-0.2-1.2-0.2-1.8c0.2-4.2,3.6-7.4,7.5-7.4c0.2,0,0.2,0,0.4,0L9.6,2.1L9.6,2.1z"/>
                        </g>
                </svg>
                <span>Forminator</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="forminator_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="forminator_new_message_notification"
                              name="forminator_new_message_notification" <?php checked( $this->get_option( 'forminator_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="forminator_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				$this->post_type_select( 'forminator_forms_select[]', 'forminator_forms',
					array(
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'forminator_forms_select', [] ),
						'class'    => 'multi_select_none_wptp',
						'blank'    => __( 'All', $this->plugin_key )
					)
				);
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new message from Forminator
	 *
	 * @param  Forminator_CForm_Front_Mail  $email  - the current form
	 * @param  Forminator_Custom_Form_Model  $custom_form  - the current form
	 * @param  array  $data  - current data
	 * @param  Forminator_Form_Entry_Model  $entry  - saved entry @since 1.0.3
	 * @param  array  $recipients  - array or recipients
	 */
	function form_submit( $email, $custom_form, $data, $entry, $recipients ) {
		$forms_select = $this->get_option( 'forminator_forms_select', [] );
		if ( $entry->entry_id == 0 || count( $forms_select ) && $forms_select[0] != '' && ! in_array( $entry->form_id,
				$forms_select ) )
			return;

		$meta   = get_post_meta( $entry->form_id, \Forminator_Base_Form_Model::META_KEY, true );
		$fields = ! empty( $meta['fields'] ) ? $meta['fields'] : array();

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users || count( $fields ) == 0 )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		foreach ( $fields as $field ) {
			if ( isset( $entry->meta_data[ $field['id'] ] ) )
				$text .= $field['field_label'] . ': ' . ( $field['type'] == 'textarea' ? "\n" : '' ) . $entry->meta_data[ $field['id'] ]['value'] . "\n";
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=forminator-entries&form_type=forminator_forms&form_id=' . $entry->form_id )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );

			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
			}
		}
	}

	/**
	 * Returns an instance of class
	 * @return ForminatorWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new ForminatorWPTP();

		return self::$instance;
	}
}

$ForminatorWPTP = ForminatorWPTP::getInstance();