<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $WPUserAvatarWPTP;

class WPUserAvatarWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'wpuseravatar_avatar_change_notification', false ) ) {
			add_action( 'update_user_meta', [ $this, 'avatar_change_notification' ], 10, 4 );
		}
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAA8AAAAPCAYAAAA71pVKAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDowNzNCQTlEQjg2MTYxMUUzQUFBMEJFNEMyRUFDRTZGQiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDowNzNCQTlEQzg2MTYxMUUzQUFBMEJFNEMyRUFDRTZGQiI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjA3M0JBOUQ5ODYxNjExRTNBQUEwQkU0QzJFQUNFNkZCIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjA3M0JBOURBODYxNjExRTNBQUEwQkU0QzJFQUNFNkZCIi8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+CLdmbwAAAYRJREFUeNqckt9LwlAUx8/cRlpaQQYmIf1A8K3YQ4SB7L/oZf4ZPvl/9ORLr0N8r9ceDRQUoR7yISbMahvbtNnS2zkxYa35UAc+jN3z/d5zz7kX4HecIS+IFeIR2Y8KhRjzDpKNrK0hm1FhIsY8iVnz49aXlc+RI+QDuYgxryNV5AERkWfkbpm8RdgfuKfCXGDeC3qVGo3GdaVSAd/3YbFYQCKRAEEQoNlsevV6/RI1T4iJaD/OlslkqpqmsU6nw6bTKaOwbZv1+33W6/WoorxyYMlkcpe+hUIBUqnUckPI5/PA8zydIrvyqrDKyDAMEEURVFUFy7Igl8uBLMug6zq1oYf1fPhnPp9vcxynmKbJU8/pdBpc14XhcAitVsvB41+hbBz2bCEHyDFyWi6Xb1gkZrMZKxaLKuZPkMNATz5oI2/BjiN6jrVajXme9210HIcpikLDeg3y40Dfpqt6p1lFX4UkSVAqlaDb7cJgMIh5N+CR2UU24O8xoWkbyOc/zPaXAAMAZMCr2bjRbN4AAAAASUVORK5CYII="
                     class="pg-icon">
                <span>WP User Avatar</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="wpuseravatar_avatar_change_notification"><?php _e( 'Notification for user avatar change',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="wpuseravatar_avatar_change_notification"
                              name="wpuseravatar_avatar_change_notification" <?php checked( $this->get_option( 'wpuseravatar_avatar_change_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when user avatar change
	 *
	 * @param  int  $meta_id  ID of the metadata entry to update.
	 * @param  int  $user_id  User ID.
	 * @param  string  $meta_key  Meta key.
	 * @param  mixed  $meta_value  Meta value.
	 */
	function avatar_change_notification( $meta_id, $user_id, $meta_key, $meta_value ) {
		global $blog_id, $wpdb;

		if ( empty( $meta_value ) || ! is_numeric( $meta_value ) )
			return;

		$key = $wpdb->get_blog_prefix( $blog_id ) . 'user_avatar';
		if ( $key != $meta_key )
			return;

		$user      = get_userdata( $user_id );
		$user_role = $this->get_user_role( $user );
		if ( ! $user_role || $user_role == 'administrator' )
			return;

		$image_size = $this->get_option( 'image_size', 'medium' );
		$image      = wp_get_attachment_image_src( $meta_value, $image_size );
		if ( ! $image )
			return;
		$mediaURL = $image[0];

		$text = "*" . __( 'User avatar change notification', $this->plugin_key ) . "*\n\n";
		$text .= __( 'User Name', $this->plugin_key ) . ': ' . $user->user_login . "\n";
		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_wpuseravatar_avatar_change_notification_text', $text, $meta_id, $user_id,
			$meta_key, $meta_value );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$keyboard  = array(
				array(
					array(
						'text' => __( 'User Profile', $this->plugin_key ),
						'url'  => admin_url( 'user-edit.php?user_id=' . $user_id )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );

			foreach ( $users as $user ) {
				$this->telegram->sendFile( 'sendPhoto', $mediaURL, $text, $keyboards, $user['user_id'], 'Markdown' );
			}
		}
	}

	/**
	 * Returns an instance of class
	 * @return WPUserAvatarWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new WPUserAvatarWPTP();

		return self::$instance;
	}
}

$WPUserAvatarWPTP = WPUserAvatarWPTP::getInstance();