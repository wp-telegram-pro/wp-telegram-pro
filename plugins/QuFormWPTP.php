<?php

namespace wptelegrampro;

use Quform;
use Quform_Repository;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $QuFormWPTP;

class QuFormWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'quform_new_message_notification', false ) ) {
			add_filter( 'quform_post_process', [ $this, 'form_submit' ], 10, 2 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg version="1.0" xmlns="http://www.w3.org/2000/svg"
                     width="20" height="20" viewBox="0 0 397.000000 354.000000"
                     preserveAspectRatio="xMidYMid meet">
                    <g transform="translate(0.000000,354.000000) scale(0.100000,-0.100000)"
                       fill="#333" stroke="none">
                        <path d="M1660 3530 c-548 -67 -1036 -347 -1337 -768 -146 -204 -244 -433
-295 -687 -32 -160 -32 -451 0 -614 157 -784 810 -1360 1644 -1450 136 -15
2208 -15 2241 0 53 24 57 47 57 304 0 257 -4 280 -57 304 -16 7 -128 11 -319
11 l-295 0 67 83 c226 277 344 569 376 929 19 224 -6 432 -82 659 -206 622
-766 1089 -1450 1210 -131 24 -428 33 -550 19z m400 -635 c135 -21 230 -49
346 -104 139 -67 244 -140 344 -240 451 -454 449 -1114 -5 -1566 -467 -465
-1243 -473 -1726 -18 -148 140 -275 352 -326 548 -22 87 -26 120 -26 255 0
136 4 168 27 255 46 174 144 355 268 490 272 297 692 443 1098 380z"/>
                        <path d="M1255 2341 c-11 -5 -31 -21 -45 -36 -22 -23 -25 -36 -25 -96 0 -64 2
-71 33 -101 l32 -33 660 0 660 0 32 33 c31 30 33 37 33 102 0 65 -2 72 -33
102 l-32 33 -648 2 c-356 1 -656 -2 -667 -6z"/>
                        <path d="M1255 1901 c-11 -5 -31 -21 -45 -36 -22 -23 -25 -36 -25 -96 0 -64 2
-71 33 -101 l32 -33 405 0 405 0 32 33 c31 30 33 37 33 102 0 65 -2 72 -33
102 l-32 33 -393 2 c-215 1 -401 -2 -412 -6z"/>
                        <path d="M1255 1461 c-11 -5 -31 -21 -45 -36 -22 -23 -25 -36 -25 -96 0 -64 2
-71 33 -101 l32 -33 165 0 165 0 32 33 c31 30 33 37 33 102 0 65 -2 72 -33
102 l-32 33 -153 2 c-83 1 -161 -1 -172 -6z"/>
                    </g>
                </svg>
                <span>Quform</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="quform_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="quform_new_message_notification"
                              name="quform_new_message_notification" <?php checked( $this->get_option( 'quform_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="quform_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				self::forms_select(
					'quform_forms_select[]',
					array(
						'blank'    => __( 'All', $this->plugin_key ),
						'field_id' => 'quform_forms_select',
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'quform_forms_select', [] ),
						'class'    => 'multi_select_none_wptp',
					)
				);
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new message from Quform Forms
	 *
	 * @param  array  $result  Quform notification
	 * @param  Quform_Form  $form  Quform form
	 *
	 * @return array
	 */
	function form_submit( $result, $form ) {
		$forms_select = $this->get_option( 'quform_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form->getId(), $forms_select ) )
			return $result;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return $result;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";

		$fieldsText = $mediaURL = '';
		foreach ( $form->getRecursiveIterator() as $element ) {
			if ( $element instanceof Quform_Element_Field && $element->config( 'saveToDatabase' ) ) {
				$type  = $element->config( 'type' );
				$value = $element->getValue();

				if ( $type == 'file' ) {
					continue;
					/*if (is_array($value) && count($value)) {
						$file = current($value);
						$value = $file['url'];
						if (filter_var($value, FILTER_VALIDATE_URL)) {
							$file = explode('.', $value);
							$ext = strtolower(end($file));
							if (in_array($ext, ['png', 'gif', 'jpeg', 'jpg']))
								$mediaURL = $value;
						} else
							continue;
					} else
						continue;*/
				}

				if ( $type == 'name' )
					$value = $element->getValueText();

				if ( $type == 'multiselect' && $element->isMultiple() && is_array( $value ) )
					if ( count( $value ) )
						$value = ( count( $value ) > 1 ? "\n" : '' ) . "▫️ " . implode( "\n▫️ ", $value );
					else
						$value = '';

				$fieldsText .= Quform::escape( $element->getLabel() ) . ': ';

				if ( $type == 'textarea' && ! empty( $value ) )
					$fieldsText .= "\n";

				$fieldsText .= $value . "\n";
			}
		}
		if ( empty( $fieldsText ) )
			return $result;

		$text .= $fieldsText;

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_quform_message_notification_text', $text, $form );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '👁️',
						'url'  => admin_url( 'admin.php?page=quform.entries&sp=view&eid=' . $form->getEntryId() )
					),
					array(
						'text' => '📝',
						'url'  => admin_url( 'admin.php?page=quform.entries&sp=edit&eid=' . $form->getEntryId() )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=quform.entries&id=' . $form->getId() )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );

			foreach ( $users as $user ) {
				//if (empty($mediaURL))
				$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
				//else
				//    $this->telegram->sendFile('sendPhoto', $mediaURL, $text, $keyboards, $user['user_id'], 'Markdown');
			}
		}

		return $result;
	}

	private static function forms_select( $field_name, $args = array() ) {
		global $wpdb;
		$items = [];

		$Quform_Repository = new Quform_Repository();
		$query             = "SELECT id, name FROM " . $Quform_Repository->getFormsTableName() . " WHERE active = 1 AND trashed = 0";
		$forms             = $wpdb->get_results( $query );

		if ( $forms )
			foreach ( $forms as $form ) {
				$items[ $form->id ] = $form->name;
			}
		HelpersWPTP::forms_select( $field_name, $items, $args );
	}

	/**
	 * Returns an instance of class
	 * @return QuFormWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new QuFormWPTP();

		return self::$instance;
	}
}

$QuFormWPTP = QuFormWPTP::getInstance();