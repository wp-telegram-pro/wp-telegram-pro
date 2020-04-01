<?php

namespace wptelegrampro;

use GF_Field;
use GFFormsModel;
use RGFormsModel;
use GFCommon;
use GFAPI;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $GravityFormsWPTP;

class GravityFormsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'gravityforms_new_message_notification', false ) ) {
			add_action( 'gform_after_submission', [ $this, 'gravityforms_submit' ], 10, 2 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg"
                     xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="-15 77 581 640" width="20"
                     height="20"
                     enable-background="new -15 77 581 640" xml:space="preserve"><g id="Layer_2">
                        <path fill="#333"
                              d="M489.5,227L489.5,227L315.9,126.8c-22.1-12.8-58.4-12.8-80.5,0L61.8,227c-22.1,12.8-40.3,44.2-40.3,69.7v200.5c0,25.6,18.1,56.9,40.3,69.7l173.6,100.2c22.1,12.8,58.4,12.8,80.5,0L489.5,567c22.2-12.8,40.3-44.2,40.3-69.7V296.8C529.8,271.2,511.7,239.8,489.5,227z M401,300.4v59.3H241v-59.3H401z M163.3,490.9c-16.4,0-29.6-13.3-29.6-29.6c0-16.4,13.3-29.6,29.6-29.6s29.6,13.3,29.6,29.6C192.9,477.6,179.6,490.9,163.3,490.9z M163.3,359.7c-16.4,0-29.6-13.3-29.6-29.6s13.3-29.6,29.6-29.6s29.6,13.3,29.6,29.6S179.6,359.7,163.3,359.7z M241,490.9v-59.3h160v59.3H241z"/>
                    </g></svg>
                <span>Gravity Forms</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="gravityforms_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="gravityforms_new_message_notification"
                              name="gravityforms_new_message_notification" <?php checked( $this->get_option( 'gravityforms_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="gravityforms_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				self::forms_select(
					'gravityforms_forms_select[]',
					array(
						'blank'    => __( 'All', $this->plugin_key ),
						'field_id' => 'gravityforms_forms_select',
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'gravityforms_forms_select', [] ),
						'class'    => 'multi_select_none_wptp',
					)
				);
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new message from Gravity Forms
	 * Source from GFEntryDetail::lead_detail_grid()
	 *
	 * @param  array  $entry  The Entry object
	 * @param  GFFormsModel  $form  The Form object
	 */
	function gravityforms_submit( $entry, $form ) {
		$forms_select = $this->get_option( 'gravityforms_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $entry['form_id'], $forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";
		foreach ( $form['fields'] as $field ) {
			$content = $value = '';
			switch ( $field->get_input_type() ) {
				case 'section':
				case 'captcha':
				case 'html':
				case 'password':
				case 'page':
				case 'fileupload':
					break;
				default :
					if ( GFCommon::is_product_field( $field->type ) )
						break;

					$value = RGFormsModel::get_lead_field_value( $entry, $field );

					if ( is_array( $field->fields ) ) {
						// Ensure the top level repeater has the right nesting level so the label is not duplicated.
						$field->nestingLevel = 0;
					}
					//$content .= $value . "\n";
					$display_value = GFCommon::get_lead_field_display( $field, $value, $entry['currency'] );

					/**
					 * Filters a field value displayed within an entry.
					 *
					 * @param  string  $display_value  The value to be displayed.
					 * @param  GF_Field  $field  The Field Object.
					 * @param  array  $lead  The Entry Object.
					 * @param  array  $form  The Form Object.
					 *
					 * @since 1.5
					 */
					$display_value = apply_filters( 'gform_entry_field_value', $display_value, $field, $entry, $form );

					if ( ! empty( $display_value ) || $display_value === '0' ) {
						$display_value = empty( $display_value ) && $display_value !== '0' ? '&nbsp;' : $display_value;
						//$content .= $field->type . ' - ' ;
						$content .= esc_html( GFCommon::get_label( $field ) ) . ': ';
						if ( $field->type == 'textarea' ) {
							$content .= "\n";
						} elseif ( ( $field->type == 'list' || $field->type == 'checkbox' ) && ! empty( trim( $display_value ) ) ) {
							$content .= "\n";
							if ( is_string( $value ) && is_serialized( $value ) )
								$value = unserialize( $value );
							$display_value = "▫️ " . implode( "\n▫️ ", $value );
						} elseif ( $field->type == 'address' ) {
							$content       .= "\n";
							$display_value = implode( ", ", $value );
						}

						$content .= $display_value;
					}
					break;
			}

			/**
			 * Filters the field content.
			 *
			 * @param  string  $content  The field content.
			 * @param  array  $field  The Field Object.
			 * @param  string  $value  The field value.
			 * @param  int  $lead  ['id'] The entry ID.
			 * @param  int  $form  ['id'] The form ID.
			 *
			 * @since 2.1.2.14 Added form and field ID modifiers.
			 *
			 */
			$content = gf_apply_filters( array( 'gform_field_content', $form['id'], $field->id ), $content, $field,
				$value, $entry['id'], $form['id'] );
			$content = wp_strip_all_tags( $content ) . "\n";

			$text .= $content;
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate( $entry['date_created'] ) . "\n";

		$text = apply_filters( 'wptelegrampro_gravityforms_message_notification_text', $text, $entry, $form );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '👁️',
						'url'  => admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $entry['form_id'] . '&lid=' . $entry['id'] )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=gf_entries&id=' . $entry['form_id'] )
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
		$forms = GFAPI::get_forms();
		$items = [];
		if ( count( $forms ) )
			foreach ( $forms as $form ) {
				$items[ $form['id'] ] = $form['title'];
			}
		HelpersWPTP::forms_select( $field_name, $items, $args );
	}

	/**
	 * Returns an instance of class
	 * @return GravityFormsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new GravityFormsWPTP();

		return self::$instance;
	}
}

$GravityFormsWPTP = GravityFormsWPTP::getInstance();