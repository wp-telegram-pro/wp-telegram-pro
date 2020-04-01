<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $WeFormsWPTP;

class WeFormsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'weforms_new_message_notification', false ) ) {
			add_action( 'weforms_entry_submission', [ $this, 'form_submit' ], 10, 4 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                     xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 300 300"
                     style="fill:#333" xml:space="preserve"><g fill="#333">
                        <path d="M285.1,24.1C273.1,9.4,254.8,0,234.3,0H65.7C46.6,0,29.3,8.2,17.3,21.2C6.6,32.9,0,48.5,0,65.7v60.5v108.1 C0,270.6,29.4,300,65.7,300h140h28.6c15.3,0,29.5-5.3,40.7-14.1c15.2-12,25-30.7,25-51.6V65.7C300,49.9,294.4,35.4,285.1,24.1z M212.7,187L104.6,233c-11.1,4.8-24-0.3-28.7-11.4c-4.8-11.1,0.3-24,11.4-28.7l108.1-46.1c11.1-4.8,24,0.3,28.7,11.4 C228.9,169.3,223.8,182.2,212.7,187z M217.4,107.1L99.9,157.8c-11.1,4.8-24-0.3-28.7-11.4c-4.8-11.1,0.3-24,11.4-28.7L200.1,67 c11.1-4.8,24,0.3,28.7,11.4v0C233.6,89.5,228.5,102.3,217.4,107.1z"/>
                    </g></svg>
                <span>weForms</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="weforms_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="weforms_new_message_notification"
                              name="weforms_new_message_notification" <?php checked( $this->get_option( 'weforms_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="weforms_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				$this->post_type_select( 'weforms_forms_select[]', 'wpuf_contact_form',
					array(
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'weforms_forms_select', [] ),
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
	 * Send notification to admin users when new message from weForms
	 *
	 * @param  int  $entry_id  Current entry ID
	 * @param  int  $form_id  Form ID
	 * @param  int  $page_id  Page ID
	 * @param  array  $form_settings  Form Settings
	 */
	function form_submit( $entry_id, $form_id, $page_id, $form_settings ) {
		$forms_select = $this->get_option( 'weforms_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form_id, $forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		$form   = weforms()->form->get( $form_id );
		$entry  = $form->entries()->get( $entry_id );
		$fields = $entry->get_fields();

		if ( false === $fields || sizeof( $fields ) < 1 )
			return;

		$valid_type = array( 'name_field', 'text_field', 'radio_field', 'multiple_select', 'checkbox_field', 'website_url', 'email_address', 'textarea_field', 'date_field' );
		foreach ( $fields as $key => $field ) {
			if ( ! in_array( $field['type'], $valid_type ) )
				continue;

			$text  .= $field['label'] . ': ';
			$value = isset( $field['value'] ) ? $field['value'] : '';

			if ( is_array( $value ) )
				$value = ( count( $value ) > 1 ? "\n" : '' ) . "▫️ " . implode( "\n▫️ ", $value );

			if ( $field['type'] == 'textarea_field' ) {
				$text  .= "\n";
				$value = wp_strip_all_tags( $value );
			}

			$text .= $value . "\n";
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_weforms_message_notification_text', $text, $entry_id, $form_id, $page_id,
			$form_settings );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '👁️',
						'url'  => admin_url( 'admin.php?page=weforms#/form/' . $form_id . '/entries/' . $entry_id )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=weforms#/entries' )
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
	 * @return WeFormsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new WeFormsWPTP();

		return self::$instance;
	}
}

$WeFormsWPTP = WeFormsWPTP::getInstance();