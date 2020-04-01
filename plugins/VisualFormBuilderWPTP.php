<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $VisualFormBuilderWPTP;

class VisualFormBuilderWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'visualformbuilder_new_message_notification', false ) ) {
			add_action( 'vfb_after_email', [ $this, 'form_submit' ] );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <div class="dashicons-before dashicons-feedback"></div>
                <span>Visual Form Builder</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="visualformbuilder_new_message_notification"><?php _e( 'Notification for new message',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="visualformbuilder_new_message_notification"
                              name="visualformbuilder_new_message_notification" <?php checked( $this->get_option( 'visualformbuilder_new_message_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="visualformbuilder_forms_select"><?php _e( 'Notification from this forms',
						$this->plugin_key ) ?></label>
            </td>
            <td>
				<?php
				self::forms_select(
					'visualformbuilder_forms_select[]',
					array(
						'blank'    => __( 'All', $this->plugin_key ),
						'field_id' => 'visualformbuilder_forms_select',
						'multiple' => 'multiple',
						'selected' => $this->get_option( 'visualformbuilder_forms_select', [] ),
						'class'    => 'multi_select_none_wptp',
					)
				);
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new message from Visual Form Builder
	 *
	 * @param  int  $form_id  Form ID
	 */
	function form_submit( $form_id ) {
		global $wpdb;
		$forms_select = $this->get_option( 'visualformbuilder_forms_select', [] );
		if ( count( $forms_select ) && $forms_select[0] != '' && ! in_array( $form_id, $forms_select ) )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( ! $users )
			return;

		$text     = "*" . __( 'New message', $this->plugin_key ) . "*\n\n";
		$mediaURL = '';

		$entry_id = $wpdb->insert_id;
		$query    = "SELECT data FROM " . VFB_WP_ENTRIES_TABLE_NAME . " WHERE entries_id = {$entry_id}";
		$entry    = $wpdb->get_row( $query );

		if ( $entry ) {
			$data = $entry->data;

			if ( is_serialized( $data ) ) {
				$fields = unserialize( $data );

				$valid_type = array(
					'text', 'textarea', 'radio', 'checkbox',
					'select', 'address', 'date', 'time', 'email', 'url', 'currency', 'number',
					'phone', 'file-upload'
				);

				foreach ( $fields as $field ) {
					if ( ! in_array( $field['type'], $valid_type ) )
						continue;

					$value = $field['value'];

					if ( $field['type'] == 'address' && ! empty( $value ) ) {
						$value = html_entity_decode( $value );
						$value = str_replace( [ '<br>', '<br/>' ], ', ', $value );

					} elseif ( $field['type'] == 'file-upload' && ! empty( $value ) ) {
						if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
							$file = explode( '.', $value );
							$ext  = strtolower( end( $file ) );
							if ( in_array( $ext, [ 'png', 'gif', 'jpeg', 'jpg' ] ) )
								$mediaURL = $value;
						} else
							continue;

					} elseif ( $field['type'] == 'checkbox' && ! empty( $value ) ) {
						$value = explode( ',', $value );
						$value = array_map( 'trim', $value );
						$value = ( count( $value ) > 1 ? "\n" : '' ) . "▫️ " . implode( "\n▫️ ", $value );
					}

					$text .= $field['name'] . ': ';

					if ( $field['type'] == 'textarea' && ! empty( $value ) )
						$text .= "\n";

					$text .= $value . "\n";
				}
			} else
				return;
		} else
			return;


		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_visualformbuilder_message_notification_text', $text, $fields, $entry_id,
			$form_id );

		if ( $text ) {
			$keyboard  = array(
				array(
					array(
						'text' => '👁️',
						'url'  => admin_url( 'admin.php?page=vfb-entries&action=view&entry=' . $entry_id )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=vfb-entries&form-filter=' . $form_id )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );

			foreach ( $users as $user ) {
				if ( empty( $mediaURL ) )
					$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
				else
					$this->telegram->sendFile( 'sendPhoto', $mediaURL, $text, $keyboards, $user['user_id'],
						'Markdown' );
			}
		}
	}

	private static function forms_select( $field_name, $args = array() ) {
		global $wpdb;
		$items = [];
		$query = "SELECT form_id, form_title FROM " . VFB_WP_FORMS_TABLE_NAME;
		$forms = $wpdb->get_results( $query );
		if ( $forms )
			foreach ( $forms as $form ) {
				$items[ $form->form_id ] = $form->form_title;
			}
		HelpersWPTP::forms_select( $field_name, $items, $args );
	}


	/**
	 * Returns an instance of class
	 * @return VisualFormBuilderWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new VisualFormBuilderWPTP();

		return self::$instance;
	}
}

$VisualFormBuilderWPTP = VisualFormBuilderWPTP::getInstance();