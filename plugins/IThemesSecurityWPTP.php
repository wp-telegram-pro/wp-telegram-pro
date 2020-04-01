<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $IThemesSecurityWPTP;

class IThemesSecurityWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();

		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'ithemessecurity_new_blacklisted_ip_notification', 0 ) ) {
			add_action( 'itsec-new-blacklisted-ip', [ $this, 'new_blacklisted_ip' ] );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <div class="pg-icon it-icon-itsec"></div>
                <span>iThemes Security</span>
            </th>
        </tr>
        <tr>
            <td>
				<?php _e( 'Notification for new blacklisted IP', $this->plugin_key ); ?>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="ithemessecurity_new_blacklisted_ip_notification"
                              name="ithemessecurity_new_blacklisted_ip_notification" <?php checked( $this->get_option( 'ithemessecurity_new_blacklisted_ip_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when add log
	 *
	 * @param  string  $ip  IP
	 */
	function new_blacklisted_ip( $ip ) {
		$text = "*" . __( 'iThemes Security new blacklisted IP', $this->plugin_key ) . "*\n\n";
		$text .= __( 'IP', $this->plugin_key ) . ': ' . $ip . "\n";
		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_ithemessecurity_new_blacklisted_ip_notification_text', $text, $ip );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$keyboard  = array(
				array(
					array(
						'text' => __( 'iThemes Security Dashboard', $this->plugin_key ),
						'url'  => admin_url( 'admin.php?page=itsec' )
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
	 * @return IThemesSecurityWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new IThemesSecurityWPTP();

		return self::$instance;
	}
}

$IThemesSecurityWPTP = IThemesSecurityWPTP::getInstance();