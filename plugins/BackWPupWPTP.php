<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $BackWPupWPTP;

class BackWPupWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'backwpup_send_log_notification', false ) ) {
			add_filter( 'wp_mail', [ $this, 'send_log' ] );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <style>
                    .backwpup-icon::before {
                        font: normal 20px/1 'backwpup' !important;
                        content: "\e600";
                    }
                </style>
                <div class="backwpup-icon pg-icon"></div>
                <span>BackWPup</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="backwpup_send_log_notification"><?php _e( 'Notification for send log',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="backwpup_send_log_notification"
                              name="backwpup_send_log_notification" <?php checked( $this->get_option( 'backwpup_send_log_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new log from BackWPup
	 *
	 * @param  array  $args  A compacted array of wp_mail() arguments, including the "to" email,
	 *                    subject, message, headers, and attachments values.
	 *
	 * @return array
	 */
	function send_log( $args ) {
		if ( stripos( $args['subject'], 'BackWPup' ) === false )
			return $args;

		$text = __( 'BackWPup new log', $this->plugin_key ) . "\n\n";

		$message = $args['message'];
		$message = HelpersWPTP::br2nl( $message );
		$message = wp_strip_all_tags( $message );
		//$message = utf8_decode($message);
		//$message = str_replace(["&#160;", "&hellip;", "&quot;"], [' ', '...', '"'], $message);
		$text .= $message . "\n";

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_backwpup_send_log_notification_text', $text, $args );

		if ( ! $text )
			return $args;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$this->telegram->disable_web_page_preview( true );
			$keyboard  = array(
				array(
					array(
						'text' => __( 'BackWPup Dashboard', $this->plugin_key ),
						'url'  => admin_url( 'admin.php?page=backwpup' )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );

			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, $keyboards, $user['user_id'] );
			}
		}

		return $args;
	}

	/**
	 * Returns an instance of class
	 * @return BackWPupWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new BackWPupWPTP();

		return self::$instance;
	}
}

$BackWPupWPTP = BackWPupWPTP::getInstance();