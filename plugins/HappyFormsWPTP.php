<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $HappyFormsWPTP;

class HappyFormsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'happyforms_new_message_notification', false ) ) {
			add_action( 'happyforms_submission_success', [ $this, 'form_submit' ], 10, 2 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <div class="dashicons-before dashicons-format-status"></div>
                <span>Happy Forms</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="happyforms_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="happyforms_new_message_notification"
                              name="happyforms_new_message_notification" <?php checked( $this->get_option( 'happyforms_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="happyforms_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				$this->post_type_select( 'happyforms_forms_select[]', 'happyform',
					array(
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'happyforms_forms_select', [] ),
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
	 * Send notification to admin users when new message from Happy Forms
	 *
	 * @param  array  $submission  Entry Data
	 * @param  array  $form  Form Data
	 */
	function form_submit( $submission, $form ) {
		$forms_select = $this->get_option( 'happyforms_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form['ID'], $forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		if ( is_array( $form['parts'] ) && count( $form['parts'] ) ) {
			foreach ( $form['parts'] as $field ) {
				if ( isset( $submission[ $field['id'] ] ) ) {
					$text  .= $field['label'] . ': ';
					$value = $submission[ $field['id'] ];

					if ( $field['type'] == 'multi_line_text' )
						$text .= "\n";

                    elseif ( $field['type'] == 'checkbox' && ! empty( $value ) ) {
						$value = explode( ',', $value );
						$value = array_map( 'trim', $value );
						$value = ( count( $value ) > 1 ? "\n" : '' ) . "▫️ " . implode( "\n▫️ ", $value );
					}

					$text .= $value . "\n";
				}
			}
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_happyforms_message_notification_text', $text, $submission, $form );

		if ( $text ) {
			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, null, $user['user_id'], 'Markdown' );
			}
		}
	}

	/**
	 * Returns an instance of class
	 * @return HappyFormsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new HappyFormsWPTP();

		return self::$instance;
	}
}

$HappyFormsWPTP = HappyFormsWPTP::getInstance();