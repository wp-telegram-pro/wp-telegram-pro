<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $NinjaFormsWPTP;

class NinjaFormsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'ninjaforms_new_message_notification', false ) ) {
			add_action( 'ninja_forms_after_submission', [ $this, 'ninjaforms_submit' ], 10, 2 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <span class="dashicons-before dashicons-feedback"></span>
                <span>Ninja Forms</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="ninjaforms_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="ninjaforms_new_message_notification"
                              name="ninjaforms_new_message_notification" <?php checked( $this->get_option( 'ninjaforms_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="ninjaforms_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				self::forms_select(
					'ninjaforms_forms_select[]',
					array(
						'blank'    => __( 'All', $this->plugin_key ),
						'field_id' => 'ninjaforms_forms_select',
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'ninjaforms_forms_select', [] ),
						'class'    => 'multi_select_none_wptp',
					)
				);
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new message from Ninja Forms
	 *
	 * @param  array  $form_data  The form data
	 */
	function ninjaforms_submit( $form_data ) {
		$forms_select = $this->get_option( 'ninjaforms_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form_data['form_id'], $forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		foreach ( $form_data['fields'] as $field ) {
			if ( $field['type'] == 'submit' )
				continue;

			$text .= $field['label'] . ': ';
			if ( $field['type'] == 'textarea' )
				$text .= "\n";
			$text .= $field['value'] . "\n";
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_ninjaforms_message_notification_text', $text, $form_data );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '📝',
						'url'  => admin_url( 'post.php?post=' . $form_data['actions']['save']['sub_id'] . '&action=edit' )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'edit.php?post_status=all&post_type=nf_sub&form_id=' . $form_data['form_id'] )
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
		$forms = Ninja_Forms()->form()->get_forms();
		if ( $forms && count( $forms ) )
			foreach ( $forms as $form ) {
				$items[ $form->get_id() ] = $form->get_setting( 'title' );
			}
		HelpersWPTP::forms_select( $field_name, $items, $args );
	}


	/**
	 * Returns an instance of class
	 * @return NinjaFormsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new NinjaFormsWPTP();

		return self::$instance;
	}
}

$NinjaFormsWPTP = NinjaFormsWPTP::getInstance();