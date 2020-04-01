<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $WordfenceWPTP;

class WordfenceWPTP extends WPTelegramPro {
	public static $instance = null;
	protected $security_events;

	public function __construct() {
		parent::__construct();
		$this->security_events = array(
			'autoUpdate'               => __( 'Wordfence auto update', $this->plugin_key ),
			'wafDeactivated'           => __( 'Wordfence firewall deactivated', $this->plugin_key ),
			'wordfenceDeactivated'     => __( 'Wordfence deactivated', $this->plugin_key ),
			'block'                    => __( 'IP address blocked', $this->plugin_key ),
			'throttle'                 => __( 'IP address throttled', $this->plugin_key ),
			'lostPasswdForm'           => __( 'Lost password form used', $this->plugin_key ),
			'loginLockout'             => __( 'User locked out from login', $this->plugin_key ),
			'adminLogin'               => __( 'Administrator user logged in', $this->plugin_key ),
			'adminLoginNewLocation'    => __( 'Administrator user logged in from a new device or location',
				$this->plugin_key ),
			'nonAdminLogin'            => __( 'Non administrator user logged in', $this->plugin_key ),
			'nonAdminLoginNewLocation' => __( 'Non administrator user logged in from a new device or location',
				$this->plugin_key ),
			'breachLogin'              => __( 'Someone is blocked from logging in for using a password found in a breach',
				$this->plugin_key ),
			'increasedAttackRate'      => __( 'Increase in attack rate', $this->plugin_key ),
		);

		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );
		add_filter( 'wptelegrampro_words', [ $this, 'new_words' ] );

		if ( count( $this->get_option( 'wordfence_security_events', [] ) ) ) {
			add_action( 'wordfence_security_event', [ $this, 'event_action' ], 10, 3 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg width="20" height="20" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg"
                     xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve"
                     style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:1.41421;">
                    <g>
                        <path d="M16,8.59L17.59,12.08L17.6,12.11L17.06,12.11L17.06,12.12C17.07,12.17 17.08,12.22 17.08,12.28C17.08,12.45 17.04,12.61 16.96,12.75C16.93,12.82 16.88,12.88 16.84,12.93C16.84,12.93 16.77,13.69 16.78,15.11C16.78,15.96 16.83,17.21 16.93,18.58C18.59,18.67 20.13,18.86 21.55,19.11L21.55,13.19L21.14,13.19L22.2,10.87L23.22,13.2L22.81,13.2L22.81,19.35C24.32,19.66 25.66,20.04 26.82,20.42L26.82,15.27L26.41,15.27L27.47,12.95L28.49,15.28L28.08,15.28L28.08,20.86C29.45,21.37 30.48,21.85 31.11,22.17C31.9,14.72 30.28,8.26 30.28,8.26C22.71,8.01 16,4 16,4L16,8.59Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                        <path d="M28.04,22.18L28.04,28L29.81,28C30.06,27.19 30.27,26.36 30.45,25.55C30.68,24.74 30.81,24.07 30.9,23.59C30.9,23.59 30.9,23.57 30.9,23.56C30.91,23.53 30.91,23.5 30.91,23.47C30.47,23.23 29.49,22.73 28.04,22.18Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                        <path d="M22.79,20.61L22.79,28L26.8,28L26.8,21.72C25.66,21.33 24.31,20.94 22.79,20.61Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                        <path d="M21.54,28L21.54,20.36C20.16,20.11 18.64,19.91 17.02,19.81C17.02,19.82 17.02,19.82 17.02,19.83C17.02,19.86 17.13,20.68 17.14,20.81C17.39,22.7 17.9,25.67 18.43,27.99L18.4,27.99L21.54,27.99L21.54,28Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                        <path d="M13.57,28C14.09,25.68 14.6,22.71 14.86,20.82L14.85,20.82L14.86,20.82C14.88,20.68 14.98,19.87 14.98,19.84C14.98,19.83 14.98,19.83 14.98,19.82C13.35,19.92 11.83,20.12 10.46,20.37L10.46,28L13.59,28L13.57,28Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                        <path d="M3.96,20.86L3.96,15.28L3.55,15.28L4.57,12.95L5.63,15.27L5.22,15.27L5.22,20.42C6.38,20.04 7.72,19.67 9.23,19.35L9.23,13.2L8.82,13.2L9.84,10.87L10.9,13.19L10.49,13.19L10.49,19.11C11.91,18.86 13.45,18.67 15.11,18.58C15.21,17.21 15.26,15.96 15.26,15.11C15.27,13.7 15.2,12.93 15.2,12.93C15.15,12.87 15.11,12.81 15.08,12.75C15,12.61 14.96,12.45 14.96,12.28C14.96,12.23 14.97,12.17 14.98,12.12L14.98,12.11L14.44,12.11L14.45,12.08L16,8.59L16,4C16,4 9.29,8.01 1.75,8.26C1.75,8.26 0.13,14.72 0.92,22.17C1.56,21.85 2.58,21.36 3.96,20.86Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                        <path d="M5.2,21.72L5.2,28L9.21,28L9.21,20.61C7.64,20.95 6.28,21.35 5.2,21.72Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                        <path d="M1.09,23.47C1.08,23.47 1.08,23.47 1.09,23.47C1.09,23.5 1.1,23.53 1.1,23.56C1.1,23.57 1.1,23.58 1.1,23.59C1.18,24.07 1.32,24.74 1.55,25.55C1.73,26.36 1.95,27.19 2.19,28L3.95,28L3.95,22.17C2.51,22.73 1.53,23.23 1.09,23.47Z"
                              style="fill:#333;fill-rule:nonzero;"/>
                    </g>
                </svg>
                <span>Wordfence Security</span>
            </th>
        </tr>
        <tr>
            <td>
				<?php _e( 'Notification for security events', $this->plugin_key ); ?>
            </td>
            <td>
				<?php
				$events = $this->get_option( 'wordfence_security_events', [] );
				foreach ( $this->security_events as $key => $label ) {
					echo '<label>
                            <input type="checkbox" value="' . $key . '" name="wordfence_security_events[]" ' . checked( in_array( $key,
							$events ), true, false ) . '> ' . $label . '
                            </label><br>';
				}
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new security events happened
	 *
	 * @param  string  $event  Event name
	 * @param  array  $data  Data
	 * @param  array  $alertCallback
	 */
	function event_action( $event, $data, $alertCallback = null ) {
		$events = $this->get_option( 'wordfence_security_events', [] );
		if ( ! in_array( $event, $events ) || ! is_array( $data ) )
			return;

		$text = "*" . __( 'Wordfence security event', $this->plugin_key ) . "*\n\n";

		$text .= __( 'Event', $this->plugin_key ) . ': ' . $this->security_events[ $event ] . "\n";

		foreach ( $data as $key => $value ) {
			$label = $key;
			if ( $label == 'ip' )
				$label = 'IP';
            elseif ( $label == 'username' )
				$label = 'userName';
			$label = HelpersWPTP::wordsCamelCaseToSentence( $label );

			if ( $key == 'duration' ) {
				$value = HelpersWPTP::secondsToHumanTime( $value,
					array(
						'days'    => __( 'Days', $this->plugin_key ),
						'hours'   => __( 'Hours', $this->plugin_key ),
						'minutes' => __( 'Minutes', $this->plugin_key ),
						'seconds' => __( 'Seconds', $this->plugin_key )
					) );

			} elseif ( $key == 'attackTable' ) {
				$attackTable = '';
				foreach ( $value as $row ) {
					$attackTable .= HelpersWPTP::localeDate( $row['date'] ) . ' | ' . $row['IP'] . ' | ' . $row['country'] . ' | ' . $row['message'] . "\n";
				}
				$value = '';
				if ( ! empty( $attackTable ) )
					$value = "\n" . $attackTable;
			}

			$text .= __( $label, $this->plugin_key ) . ': ' . $value . "\n";
		}

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_wordfence_security_event_notification_text', $text, $event, $data,
			$alertCallback );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			if ( $event == 'wordfenceDeactivated' ) {
				$keyboards = null;

			} else {
				$keyboard  = array(
					array(
						array(
							'text' => __( 'Wordfence Dashboard', $this->plugin_key ),
							'url'  => admin_url( 'admin.php?page=Wordfence' )
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

	function new_words( $words ) {
		$new_words = array(
			'ip'               => __( 'IP', $this->plugin_key ),
			'version'          => __( 'Version', $this->plugin_key ),
			'username'         => __( 'User Name', $this->plugin_key ),
			'reason'           => __( 'Reason', $this->plugin_key ),
			'duration'         => __( 'Duration', $this->plugin_key ),
			'email'            => __( 'Email', $this->plugin_key ),
			'resetPasswordURL' => __( 'Reset Password URL', $this->plugin_key ),
			'supportURL'       => __( 'Support URL', $this->plugin_key ),
			'attackCount'      => __( 'Attack Count', $this->plugin_key ),
			'attackTable'      => __( 'Attack Table', $this->plugin_key )
		);
		$words     = array_merge( $words, $new_words );

		return $words;
	}

	/**
	 * Returns an instance of class
	 * @return WordfenceWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new WordfenceWPTP();

		return self::$instance;
	}
}

$WordfenceWPTP = WordfenceWPTP::getInstance();