<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $EverestFormsWPTP;

class EverestFormsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'everestforms_new_message_notification', false ) ) {
			add_action( 'everest_forms_process_complete', [ $this, 'form_submit' ], 10, 4 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
                    <g>
                        <path fill="#333"
                              d="M18.1 4h-3.8l1.2 2h3.9zM20.6 8h-3.9l1.2 2h3.9zM20.6 18H5.8L12 7.9l2.5 4.1H12l-1.2 2h7.3L12 4.1 2.2 20h19.6z"/>
                    </g>
                </svg>
                <span>Everest Forms</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="everestforms_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="everestforms_new_message_notification"
                              name="everestforms_new_message_notification" <?php checked( $this->get_option( 'everestforms_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="everestforms_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				self::forms_select(
					'everestforms_forms_select[]',
					array(
						'blank'    => __( 'All', $this->plugin_key ),
						'field_id' => 'everestforms_forms_select',
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'everestforms_forms_select', [] ),
						'class'    => 'multi_select_none_wptp',
					)
				);
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new message from Everest Forms
	 *
	 * @param  array  $form_fields  Form Fields
	 * @param  array  $entry  Entry object
	 * @param  array  $form_data  Form Data
	 * @param  int  $entry_id  Current entry ID
	 */
	function form_submit( $form_fields, $entry, $form_data, $entry_id ) {
		$forms_select = $this->get_option( 'everestforms_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form_data['id'], $forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		foreach ( $form_fields as $slug => $field ) {
			$value = $field['value'];
			if ( ! is_array( $value ) && ! is_string( $value ) )
				continue;

			$text .= $field['name'] . ': ';
			if ( $field['type'] == 'textarea' )
				$text .= "\n";

			if ( is_array( $value ) )
				$value = ( count( $value ) > 1 ? "\n" : '' ) . "▫️ " . implode( "\n▫️ ", $value );

			$text .= $value . "\n";
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_everestforms_message_notification_text', $text, $form_fields, $entry,
			$form_data, $entry_id );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '👁️',
						'url'  => admin_url( 'admin.php?page=evf-entries&form_id=' . $form_data['id'] . '&view-entry=' . $entry_id )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=evf-entries&form_id=' . $form_data['id'] )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );
			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
			}
		}
	}

	private static function forms_select( $field_name, $args = array() ) {
		$items = [];
		$forms = evf_get_all_forms( true );
		if ( $forms && count( $forms ) )
			foreach ( $forms as $form_id => $form_title ) {
				$items[ $form_id ] = $form_title;
			}
		HelpersWPTP::forms_select( $field_name, $items, $args );
	}


	/**
	 * Returns an instance of class
	 * @return EverestFormsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new EverestFormsWPTP();

		return self::$instance;
	}
}

$EverestFormsWPTP = EverestFormsWPTP::getInstance();