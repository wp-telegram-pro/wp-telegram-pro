<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $CalderaFormsWPTP;

class CalderaFormsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'calderaforms_new_message_notification', false ) ) {
			add_action( 'caldera_forms_submit_complete', [ $this, 'form_submit' ], 10, 4 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <div class="dashicons-before dashicons-cf-logo wp-menu-image"><br></div>
                <span>Caldera Forms</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="calderaforms_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="calderaforms_new_message_notification"
                              name="calderaforms_new_message_notification" <?php checked( $this->get_option( 'calderaforms_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="calderaforms_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				self::forms_select(
					'calderaforms_forms_select[]',
					array(
						'blank'    => __( 'All', $this->plugin_key ),
						'field_id' => 'calderaforms_forms_select',
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'calderaforms_forms_select', [] ),
						'class'    => 'multi_select_none_wptp',
					)
				);
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new message from Caldera Forms
	 *
	 * @param  array  $form  Form config
	 * @param  array  $referrer  URL form was submitted via -- is passed through parse_url() before this point.
	 * @param  string  $process_id  Unique ID for this processing
	 * @param  int|false  $entryid  Current entry ID or false if not set or being saved.
	 */
	function form_submit( $form, $referrer, $process_id, $entryid ) {
		$forms_select = $this->get_option( 'calderaforms_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form['ID'], $forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		$valid_type = array(
			'text', 'email', 'paragraph', 'hidden', 'number', 'phone',
			'phone_better', 'url', 'dropdown', 'checkbox', 'radio', 'filtered_select2',
			'date_picker', 'toggle_switch', 'color_picker', 'states', 'range', 'star_rating'
		);
		foreach ( $form['fields'] as $field_id => $field ) {
			if ( ! in_array( $field['type'], $valid_type ) )
				continue;

			$value = \Caldera_Forms::get_field_data( $field_id, $form );

			if ( $field['type'] == 'checkbox' && is_array( $value ) && ! empty( $field['config']['option'] ) ) {
				$options = [];
				foreach ( $field['config']['option'] as $opt => $option ) {
					if ( in_array( $opt, array_keys( $value ) ) )
						$options[] = $option['label'];
				}

				$value = ( count( $options ) > 1 ? "\n" : '' ) . "▫️ " . implode( "\n▫️ ", $options );

			} elseif ( $field['type'] == 'star_rating' ) {
				$value = intval( $value );
				$star  = '';
				if ( $value > 0 )
					for ( $i = 1; $i <= $value; $i ++ ) {
						$star .= "⭐️";
					} // star ✰
				$value = $star;
			}

			$text .= $field['label'] . ': ' . $value . "\n";
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_calderaforms_message_notification_text', $text, $form );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=caldera-forms' )
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
		$forms = \Caldera_Forms::get_forms();
		if ( $forms && count( $forms ) )
			foreach ( $forms as $form ) {
				$items[ $form['ID'] ] = $form['name'];
			}
		HelpersWPTP::forms_select( $field_name, $items, $args );
	}


	/**
	 * Returns an instance of class
	 * @return CalderaFormsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new CalderaFormsWPTP();

		return self::$instance;
	}
}

$CalderaFormsWPTP = CalderaFormsWPTP::getInstance();