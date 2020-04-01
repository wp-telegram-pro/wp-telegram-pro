<?php

namespace wptelegrampro;

use TNP_User;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $NewsletterWPTP;

class NewsletterWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'newsletter_plugin_new_subscriber_notification', false ) )
			add_action( 'newsletter_user_confirmed', [ $this, 'send_notification' ] );
		if ( $this->get_option( 'newsletter_plugin_unsubscribe_notification', false ) )
			add_action( 'newsletter_unsubscribed', [ $this, 'send_notification' ] );
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAP9JREFUeNrslFFqwkAQhmd3A1bENkfIUXoE3wW1pX22N4iewwfbk+hNfPZNMSVg3J3+k2ZhESVWfWsGPggzsx+T2RCiJu4SPHzvgfiG8wkYybOucmOwQnJyhWguZ8FQclFQlwlTNEhhqr5mn+dExeAt1kp9SP9xLTrRn4A5xNL8AvHSF7b919hAdGAeGwzA8opKkaoRhuIFxCKcrvdFUjCnDnlAgql2pitxndDHs/AUGdpZR44ZQIjzVv1KTXAZ0aUX8KB1Se4cZRBbiEXk5Zr/KPTRhrQFvq2lXMTBlFcJ/bfWNYY6IDvYcmqrbhCG4kfstsNa9rtpfhH/JX4EGABR/Fz7GiR5NAAAAABJRU5ErkJggg=="
                     class="pg-icon">
                <span>Newsletter</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="newsletter_plugin_new_subscriber_notification"><?php _e( 'Notification for new subscriber',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="newsletter_plugin_new_subscriber_notification"
                              name="newsletter_plugin_new_subscriber_notification" <?php checked( $this->get_option( 'newsletter_plugin_new_subscriber_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <label for="newsletter_plugin_unsubscribe_notification"><?php _e( 'Notification for unsubscribe',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="newsletter_plugin_unsubscribe_notification"
                              name="newsletter_plugin_unsubscribe_notification" <?php checked( $this->get_option( 'newsletter_plugin_unsubscribe_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when subscribe or unsubscribe email
	 *
	 * @param  array|object|null|void  $user  Database query result form newsletter DB table
	 */
	function send_notification( $user ) {
		if ( $user == null || ! is_object( $user ) || ! class_exists( 'TNP_User' ) )
			return;

		if ( $user->status == TNP_User::STATUS_CONFIRMED ) {
			$status = 'subscribe';
		} elseif ( $user->status == TNP_User::STATUS_UNSUBSCRIBED ) {
			$status = 'unsubscribe';
		} else
			return;

		//$status_value = $status == 'subscribe' ? __('Subscriber', $this->plugin_key) : __('Unsubscribe', $this->plugin_key);
		$title = $status == 'subscribe' ? __( 'New subscriber for newsletter',
			$this->plugin_key ) : __( 'Unsubscribe from newsletter', $this->plugin_key );

		$text = "*" . $title . "*\n\n";
		$text .= __( 'Name', $this->plugin_key ) . ': ' . $user->name . "\n";
		$text .= __( 'Email', $this->plugin_key ) . ': ' . $user->email . "\n";
		//$text .= __('Status', $this->plugin_key) . ': ' . $status_value . "\n";
		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_newsletter_plugin_' . $status . '_notification_text', $text, $user );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$keyboard  = array(
				array(
					array(
						'text' => __( 'Newsletter Dashboard', $this->plugin_key ),
						'url'  => admin_url( 'admin.php?page=newsletter_main_index' )
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
	 * @return NewsletterWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new NewsletterWPTP();

		return self::$instance;
	}
}

$NewsletterWPTP = NewsletterWPTP::getInstance();