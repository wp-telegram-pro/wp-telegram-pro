<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $WPCerberSecurityWPTP;

class WPCerberSecurityWPTP extends WPTelegramPro {
	public static $instance = null;
	protected $events;

	public function __construct() {
		parent::__construct();

		$this->events = array(
			'citadel'     => __( 'Citadel mode is activated', $this->plugin_key ),
			'lockout'     => __( 'Number of lockouts is increasing', $this->plugin_key ),
			'new_version' => __( 'A new version of WP Cerber Security is available to install', $this->plugin_key ),
			'shutdown'    => __( 'The WP Cerber Security plugin has been deactivated', $this->plugin_key ),
			'newlurl'     => __( 'New Custom login URL', $this->plugin_key ),
			'subs'        => __( 'A new activity has been recorded', $this->plugin_key ),
			'report'      => __( 'Weekly report', $this->plugin_key ),
			'scan'        => __( 'Scanner Report', $this->plugin_key ),
		);

		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( count( $this->get_option( 'wpcerbersecurity_events', [] ) ) ) {
			add_action( 'cerber_notify_sent', [ $this, 'event_action' ], 10, 2 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <style>
                    .cerber-security-icon::before {
                        font-family: "cerber-icon" !important;
                        content: '\10ffff';
                    }
                </style>
                <div class="dashicons-before dashicons-shield cerber-security-icon"><br></div>
                <span>WP Cerber Security</span>
            </th>
        </tr>
        <tr>
            <td>
				<?php _e( 'Notification for events', $this->plugin_key ); ?>
            </td>
            <td>
				<?php
				$events = $this->get_option( 'wpcerbersecurity_events', [] );
				foreach ( $this->events as $key => $label ) {
					echo '<label>
                            <input type="checkbox" value="' . $key . '" name="wpcerbersecurity_events[]" ' . checked( in_array( $key,
							$events ), true, false ) . '> ' . $label . '
                            </label><br>';
				}
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new event
	 *
	 * @param  string  $body  Message
	 * @param  array  $params  Parameters (type, IP, to, subject)
	 */
	function event_action( $body, $params ) {
		$events = $this->get_option( 'wpcerbersecurity_events', [] );
		if ( ! is_array( $params ) || ! in_array( $params['type'], $events ) )
			return;

		$text = "*" . $params['subject'] . "*\n\n";

		$body = HelpersWPTP::br2nl( $body );
		$text .= wp_strip_all_tags( $body ) . "\n";

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_wpcerbersecurity_event_notification_text', $text, $body, $params );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			if ( $params['type'] == 'shutdown' ) {
				$keyboards = null;

			} else {
				$keyboard  = array(
					array(
						array(
							'text' => __( 'WP Cerber Security Dashboard', $this->plugin_key ),
							'url'  => admin_url( 'admin.php?page=cerber-security' )
						)
					)
				);
				$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );
			}

			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
			}
		}
	}

	/**
	 * Returns an instance of class
	 * @return WPCerberSecurityWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new WPCerberSecurityWPTP();

		return self::$instance;
	}
}

$WPCerberSecurityWPTP = WPCerberSecurityWPTP::getInstance();