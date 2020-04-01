<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $BackUpWordPressWPTP;

class BackUpWordPressWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'backupwordpress_plugin_new_backup_notification', false ) ) {
			add_action( 'wptelegrampro_backupwordpress_plugin_new_backup', [ $this, 'new_backup' ], 10, 2 );
			//add_action('plugins_loaded', [$this, 'plugins_loaded'], 99999);
			$this->plugins_loaded();
		}
	}

	function plugins_loaded() {
		require_once WPTELEGRAMPRO_PLUGINS_DIR . 'BackUpWordPressServiceWPTP.php';
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2">
                <span>BackUpWordPress</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="backupwordpress_plugin_new_backup_notification"><?php _e( 'Notification for new backup',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="backupwordpress_plugin_new_backup_notification"
                              name="backupwordpress_plugin_new_backup_notification" <?php checked( $this->get_option( 'backupwordpress_plugin_new_backup_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
                <p class="description"><?php _e( 'Requires the Schedule "Telegram Notification" option to be enabled.',
						WPTELEGRAMPRO_PLUGIN_KEY ); ?></p>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when subscribe or unsubscribe email
	 *
	 * @param  HM\BackUpWordPress\Backup  $backup
	 * @param  bool  $attacheFile  Check attache file is active
	 *
	 * @see  Backup::do_action
	 */
	function new_backup( $backup, $attacheFile ) {
		if ( ! is_object( $backup ) )
			return;

		$file     = $backup->get_backup_filepath();
		$download = add_query_arg( 'hmbkp_download', base64_encode( $file ), HMBKP_ADMIN_URL );
		$domain   = parse_url( home_url(), PHP_URL_HOST ) . parse_url( home_url(), PHP_URL_PATH );

		// The backup failed, send a message saying as much
		if ( ! file_exists( $file ) && ( $errors = array_merge( $backup->get_errors(), $backup->get_warnings() ) ) ) {
			$error_message = '';

			foreach ( $errors as $error_set ) {
				$error_message .= implode( "\n- ", $error_set );
			}

			if ( $error_message )
				$error_message = '- ' . $error_message;

			$title   = sprintf( __( 'Backup of %s Failed', $this->plugin_key ), $domain );
			$message = sprintf( __( 'BackUpWordPress was unable to backup your site %1$s.', $this->plugin_key ) . "\n" .
			                    __( 'Here are the errors that we\'ve encountered:',
				                    $this->plugin_key ) . "\n" . '%2$s' . "\n" .
			                    __( 'If the errors above look like Martian, you can find further assistance on our support forum:',
				                    $this->plugin_key ) . '%3$s' . "\n", home_url(), $error_message,
				'http://wordpress.org/support/plugin/backupwordpress' );

			$this->send_notification( $title, $message, $backup );

		} elseif ( file_exists( $file ) ) {
			$title         = sprintf( __( 'Backup of %s', $this->plugin_key ), $domain );
			$start_message = sprintf( __( 'BackUpWordPress has completed a backup of your site %s.',
					$this->plugin_key ) . "\n", home_url() );
			$end_message   = __( 'You can download the backup file by clicking the link below:',
					$this->plugin_key ) . "\n\n" . $download . "\n";

			if ( $attacheFile ) {
				// If it's larger than the max attachment size limit assume it's not going to be able to send the backup
				$maxFileSize = wp_convert_hr_to_bytes( WPTELEGRAMPRO_MAX_FILE_SIZE );
				if ( @filesize( $file ) < $maxFileSize ) {
					$mid_message = __( 'The backup file should be attached to this message.',
							$this->plugin_key ) . "\n";
					$message     = $start_message . $mid_message . $end_message;
					$this->send_notification( $title, $message, $backup, $file );
				}

				// If we didn't send the telegram message notification above then send just the notification
				$result = $this->telegram->get_last_result();
				if ( isset( $result['ok'] ) && ! $result['ok'] ) {
					$mid_message = __( 'Unfortunately, the backup file was too large to attach to this message.',
							$this->plugin_key ) . "\n";
					$message     = $start_message . $mid_message . $end_message;
					$this->send_notification( $title, $message, $backup );
				}

			} else {
				$message = $start_message . $end_message;
				$this->send_notification( $title, $message, $backup );
			}
		}
	}

	/**
	 * Send notification to admin users when new backup
	 *
	 * @param  string  $title  Title
	 * @param  string  $message  Message text
	 * @param  HM\BackUpWordPress\Backup  $backup
	 * @param  bool  $file  Attache File
	 */
	protected function send_notification( $title, $message, $backup, $file = false ) {
		$text = $title . "\n\n";
		$text .= $message;
		$text .= "\n" . __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_backupwordpress_plugin_new_backup_notification_text', $text, $title,
			$message, $backup );

		if ( ! $text )
			return;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$this->telegram->disable_web_page_preview( true );
			$keyboard  = array(
				array(
					array(
						'text' => __( 'BackUpWordPress Dashboard', $this->plugin_key ),
						'url'  => admin_url( 'tools.php?page=backupwordpress' )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );

			foreach ( $users as $user ) {
				if ( $file )
					$this->telegram->sendFile( 'sendDocument', $file, $text, $keyboards, $user['user_id'] );
				else
					$this->telegram->sendMessage( $text, $keyboards, $user['user_id'] );
			}
		}
	}

	/**
	 * Returns an instance of class
	 * @return BackUpWordPressWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new BackUpWordPressWPTP();

		return self::$instance;
	}
}

$BackUpWordPressWPTP = BackUpWordPressWPTP::getInstance();