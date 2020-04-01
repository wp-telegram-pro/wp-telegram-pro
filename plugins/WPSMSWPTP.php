<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $WPSMSWPTP;

class WPSMSWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'wpsms_add_subscriber_notification', false ) ) {
			add_action( 'wp_sms_add_subscriber', [ $this, 'add_subscriber' ], 10, 4 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <div class="dashicons-before dashicons-email-alt"></div>
                <span>WP SMS</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="wpsms_add_subscriber_notification"><?php _e( 'Notification for new subscriber',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="wpsms_add_subscriber_notification"
                              name="wpsms_add_subscriber_notification" <?php checked( $this->get_option( 'wpsms_add_subscriber_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when add subscriber to WP SMS
	 *
	 * @param  string  $name  name.
	 * @param  string  $mobile  mobile.
	 */
	function add_subscriber( $name, $mobile ) {
		$text = "*" . __( 'SMS new subscriber', $this->plugin_key ) . "*\n\n";

		$text .= __( 'Name', $this->plugin_key ) . ': ' . $name . "\n";
		$text .= __( 'Mobile Number', $this->plugin_key ) . ': ' . $mobile . "\n";

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_wpsms_new_subscriber_notification_text', $text, $name, $mobile );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$keyboard  = array(
				array(
					array(
						'text' => '📂',
						'url'  => admin_url( 'admin.php?page=wp-sms-subscribers' )
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
	 * @return WPSMSWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new WPSMSWPTP();

		return self::$instance;
	}
}

$WPSMSWPTP = WPSMSWPTP::getInstance();