<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $HTMLFormsWPTP;

class HTMLFormsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'htmlforms_new_message_notification', false ) ) {
			add_action( 'hf_form_success', [ $this, 'form_submit' ], 10, 2 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                     viewBox="0 0 256.000000 256.000000" preserveAspectRatio="xMidYMid meet">
                    <g transform="translate(0.000000,256.000000) scale(0.100000,-0.100000)"
                       fill="#333" stroke="none">
                        <path d="M0 1280 l0 -1280 1280 0 1280 0 0 1280 0 1280 -1280 0 -1280 0 0 -1280z m2031 593 c8 -8 9 -34 4 -78 -6 -56 -9 -65 -23 -60 -43 16 -98 15 -132 -2 -50 -26 -72 -72 -78 -159 l-5 -74 92 0 91 0 0 -70 0 -70 -90 0 -90 0 0 -345 0 -345 -90 0 -90 0 0 345 0 345 -55 0 -55 0 0 70 0 70 55 0 55 0 0 38 c0 63 20 153 45 202 54 105 141 152 273 147 45 -2 87 -8 93 -14z m-1291 -288 l0 -235 230 0 230 0 0 235 0 235 90 0 90 0 0 -575 0 -575 -90 0 -90 0 0 260 0 260 -230 0 -230 0 0 -260 0 -260 -90 0 -90 0 0 575 0 575 90 0 90 0 0 -235z"/>
                    </g>
                </svg>
                <span>HTML Forms</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="htmlforms_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="htmlforms_new_message_notification"
                              name="htmlforms_new_message_notification" <?php checked( $this->get_option( 'htmlforms_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="htmlforms_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				$this->post_type_select( 'htmlforms_forms_select[]', 'html-form',
					array(
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'htmlforms_forms_select', [] ),
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
	 * Send notification to admin users when new message from HTML Forms
	 *
	 * @param  HTML_Forms\Submission  $submission
	 * @param  HTML_Forms\Form  $form
	 *
	 * @package HTML_Forms
	 */
	function form_submit( $submission, $form ) {
		$forms_select = $this->get_option( 'htmlforms_forms_select', [] );
		if ( count( $submission->data ) === 0 || count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form->ID,
				$forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		foreach ( $submission->data as $key => $value ) {
			$key_ = ucwords( strtolower( str_replace( [ '_', '-' ], '', $key ) ) );
			if ( is_array( $value ) )
				$value = ( count( $value ) > 1 ? "\n" : '' ) . "▫️ " . implode( "\n▫️ ", $value );
			$text .= $key_ . ': ' . $value . "\n";
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_htmlforms_message_notification_text', $text, $submission, $form );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '👁️',
						'url'  => admin_url( 'admin.php?page=html-forms&view=edit&tab=submissions&form_id=' . $form->ID . '&submission_id=' . $submission->id )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=html-forms&view=edit&tab=submissions&form_id=' . $form->ID )
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
	 * @return HTMLFormsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new HTMLFormsWPTP();

		return self::$instance;
	}
}

$HTMLFormsWPTP = HTMLFormsWPTP::getInstance();