<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $MailchimpForWPWPTP;

class MailchimpForWPWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'mailchimpforwp_email_subscribe_notification', false ) )
			add_action( 'mc4wp_form_subscribed', [ $this, 'email_subscribed' ], 10, 4 );
		if ( $this->get_option( 'mailchimpforwp_email_unsubscribe_notification', false ) )
			add_action( 'mc4wp_form_unsubscribed', [ $this, 'email_unsubscribed' ], 10, 2 );
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <svg xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                     viewBox="0 0 16 16" version="1.1">
                    <g fill="#333">
                        <path opacity="1" fill="#333" fill-opacity="1" stroke="none"
                              d="M 8.0097656 0.052734375 A 8 8 0 0 0 0.009765625 8.0527344 A 8 8 0 0 0 8.0097656 16.052734 A 8 8 0 0 0 16.009766 8.0527344 A 8 8 0 0 0 8.0097656 0.052734375 z M 9.2597656 4.171875 C 9.3205456 4.171875 9.9296146 5.0233822 10.611328 6.0664062 C 11.293041 7.1094313 12.296018 8.5331666 12.841797 9.2285156 L 13.833984 10.492188 L 13.316406 11.041016 C 13.031321 11.342334 12.708299 11.587891 12.599609 11.587891 C 12.253798 11.587891 11.266634 10.490156 10.349609 9.0859375 C 9.8610009 8.3377415 9.4126385 7.7229 9.3515625 7.71875 C 9.2904825 7.71455 9.2402344 8.3477011 9.2402344 9.1269531 L 9.2402344 10.544922 L 8.5839844 10.982422 C 8.2233854 11.223015 7.8735746 11.418294 7.8066406 11.417969 C 7.7397106 11.417644 7.4861075 10.997223 7.2421875 10.482422 C 6.9982675 9.9676199 6.6560079 9.3946444 6.4824219 9.2089844 L 6.1679688 8.8710938 L 6.0664062 9.34375 C 5.7203313 10.974656 5.6693219 11.090791 5.0917969 11.505859 C 4.5805569 11.873288 4.2347982 12.017623 4.1914062 11.882812 C 4.1839062 11.859632 4.1482681 11.574497 4.1113281 11.25 C 3.9708341 10.015897 3.5347399 8.7602861 2.8105469 7.5019531 C 2.5672129 7.0791451 2.5711235 7.0651693 2.9765625 6.8320312 C 3.2046215 6.7008903 3.5466561 6.4845105 3.7363281 6.3515625 C 4.0587811 6.1255455 4.1076376 6.1466348 4.4941406 6.6679688 C 4.8138896 7.0992628 4.9275606 7.166285 4.9941406 6.96875 C 5.0960956 6.666263 6.181165 5.8574219 6.484375 5.8574219 C 6.600668 5.8574219 6.8857635 6.1981904 7.1171875 6.6152344 C 7.3486105 7.0322784 7.5790294 7.3728809 7.6308594 7.3730469 C 7.7759584 7.3735219 7.9383234 5.8938023 7.8339844 5.5195312 C 7.7605544 5.2561423 7.8865035 5.0831575 8.4453125 4.6796875 C 8.8327545 4.3999485 9.1989846 4.171875 9.2597656 4.171875 z "
                              id="path5822"/>
                    </g>
                </svg>
                <span>Mailchimp for WordPress</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="mailchimpforwp_email_subscribe_notification"><?php _e( 'Notification for email subscribe',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="mailchimpforwp_email_subscribe_notification"
                              name="mailchimpforwp_email_subscribe_notification" <?php checked( $this->get_option( 'mailchimpforwp_email_subscribe_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="mailchimpforwp_email_unsubscribe_notification"><?php _e( 'Notification for email unsubscribe',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="mailchimpforwp_email_unsubscribe_notification"
                              name="mailchimpforwp_email_unsubscribe_notification" <?php checked( $this->get_option( 'mailchimpforwp_email_unsubscribe_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when email subscribed
	 *
	 * @param  MC4WP_Form  $form  Instance of the submitted form
	 * @param  string  $email
	 * @param  array  $data
	 * @param  MC4WP_MailChimp_Subscriber[]  $map
	 */
	function email_subscribed( $form, $email, $data, $map ) {
		$this->send_notification( $email, $form, $form->last_event );
	}

	/**
	 * Send notification to admin users when email unsubscribed
	 *
	 * @param  MC4WP_Form  $form  Instance of the submitted form.
	 * @param  string  $email
	 */
	function email_unsubscribed( $form, $email ) {
		$this->send_notification( $email, $form, $form->last_event );
	}

	/**
	 * Send notification
	 *
	 * @param  string  $email  Email.
	 * @param  MC4WP_Form  $form  Instance of the submitted form.
	 * @param  string  $event  Event.
	 */
	private function send_notification( $email, $form, $event = 'subscribed' ) {
		if ( ! in_array( $event, [ 'subscribed', 'updated_subscriber', 'unsubscribed' ] ) )
			return;

		switch ( $event ) {
			case 'subscribed':
				$title = __( 'New email subscribed to Mailchimp', $this->plugin_key );
				break;
			case 'updated_subscriber':
				$title = __( 'Subscriber email in Mailchimp updated', $this->plugin_key );
				break;
			case 'unsubscribed':
			default:
				$title = __( 'Email unsubscribed from Mailchimp', $this->plugin_key );
				break;
		}

		$text = "*" . $title . "*\n\n";
		$text .= __( 'Email', $this->plugin_key ) . ': ' . $email . "\n";
		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

		$text = apply_filters( 'wptelegrampro_mailchimpforwp_new_subscriber_notification_text', $text, $email, $form,
			$event );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users )
			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, null, $user['user_id'], 'Markdown' );
			}
	}

	/**
	 * Returns an instance of class
	 * @return MailchimpForWPWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new MailchimpForWPWPTP();

		return self::$instance;
	}
}

$MailchimpForWPWPTP = MailchimpForWPWPTP::getInstance();