<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $AllInOneWPSecurityFirewallWPTP;

class AllInOneWPSecurityFirewallWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();

		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'allinonewpsecurityfirewall_lock_user_notification', false ) ) {
			add_action( 'aiowps_lockdown_event', [ $this, 'new_blacklisted_ip' ], 10, 2 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBXaW5kb3dzIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjU4QUEwRjM3OTlBQjExRTJBRkIzRDZFMjA3RDVDMkE4IiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjU4QUEwRjM4OTlBQjExRTJBRkIzRDZFMjA3RDVDMkE4Ij4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NThBQTBGMzU5OUFCMTFFMkFGQjNENkUyMDdENUMyQTgiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6NThBQTBGMzY5OUFCMTFFMkFGQjNENkUyMDdENUMyQTgiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz4SBpF8AAADHklEQVR42nyTe0hTYRjGn3O2uYs6zUuWDpwahYGmlUlWhkVQVkSWRARRYJBkEF0siiL2V0F0o9uCCBEhMwwVb1m5xBDLILs4zbTmtlbTdHPb2c45O+frWAwswwe+f97vfX8878P3Ufi/VHlrNpZmZ2VmsKyfGfr8ud/U3lpBiOiZ3kQImTkpk8nn7jtwvsU8yBOGCRKW48iw7Rsx3nnwTq9ftP5fADW9EBUdv+zU2ZvVxTuK0wTBgQbzdaxO3g6T4z5SY7ORJObyb18NDlhsAzbj7cvHXBM/+6YAdGbWij2Fm3fsK1i3aXlOdobWbH2NVts1+MJGkPazDM7Yx2BVP6AW47E0ZjtW6YuwamXewY/ve4xyCZBYuGVnicFwIt8zyaCu5wY+eBsh0whQUpGwaKqhkHMQWTlcLh86AveQrMmV/P9xTUuHCrAC5ZHisTq/4NXYQ1AKaTdBAYGXGtR+MKwXsWw6jubdheAKl3anEVqf/hOGCD4ICCINOTQI+IIY/e4CLxU5VqJMzMGB1Qa8GHr02xkJqsBxHBsCEJ7jRJ9PAE2UYP0c4qmFKM29BP8YjfEffpTlX0SvpQsmexWilAlwT7AYHXVYpwBTGXjs9pHRCdcklGFa0IIGHq8Hi/VLcFR7BU6XA6Lkt+rNRahj1NDSenwdtnsm3WOfQg7c5r7eHpvdDpqKQJw6FQ72E8or9iJCpUVmSg6uPTkJhZoGxwjQReSiu7vjrTQ3EnKAr0PmZ319H0Td/BRaF5kFi/sNvLQThppDiNLEwkfGoQoqoUQcIoR0NDUerwm9nd8hcpy/p62l9rnFOoL0+Rsg48OlPMLghxsOZhAKqOFjvMiI34WX7Z3WL0Pvqv8CTKnTVH+ura3ej2A0ViaXgPF5QQQKMilYJuBBomoFEuh8GG+duSC1O2cAeJ7tqq66Wl5XXyUuSS7GGv1hBAMivIwL0bIFWJt6CjeunG5wfBs2YjYpVeFFO3cf6W986iSP2z+S67WVpKbZTLYVlZqk63mzfqZpmpuoS9u/tqBoqy4pbV5Lc2XT+96XBqk+9i/glwADAOuIdWgMD65XAAAAAElFTkSuQmCC"
                     class="pg-icon">
                <span>All In One WP Security & Firewall</span>
            </th>
        </tr>
        <tr>
            <td>
				<?php _e( 'Notification for lock the user', $this->plugin_key ); ?>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="allinonewpsecurityfirewall_lock_user_notification"
                              name="allinonewpsecurityfirewall_lock_user_notification" <?php checked( $this->get_option( 'allinonewpsecurityfirewall_lock_user_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when add log
	 *
	 * @param  string  $ip_range  IP range
	 * @param  string  $username  User Name
	 */
	function new_blacklisted_ip( $ip_range, $username ) {
		$text = "*" . __( 'All In One WP Security & Firewall lock the user', $this->plugin_key ) . "*\n\n";

		if ( is_email( $username ) )
			$text .= __( 'Email', $this->plugin_key ) . ': ' . $username . "\n";
		else
			$text .= __( 'User Name', $this->plugin_key ) . ': ' . $username . "\n";

		$text .= __( 'IP range', $this->plugin_key ) . ': ' . $ip_range . "\n";
		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_allinonewpsecurityfirewall_lock_user_notification_text', $text, $ip_range,
			$username );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$keyboard  = array(
				array(
					array(
						'text' => __( 'All In One WP Security & Firewall Dashboard', $this->plugin_key ),
						'url'  => admin_url( 'admin.php?page=aiowpsec' )
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
	 * @return AllInOneWPSecurityFirewallWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new AllInOneWPSecurityFirewallWPTP();

		return self::$instance;
	}
}

$AllInOneWPSecurityFirewallWPTP = AllInOneWPSecurityFirewallWPTP::getInstance();