<?php

namespace wptelegrampro;

if ( ! defined( 'ABSPATH' ) )
	exit;
global $WPStatisticsWPTP;

class WPStatisticsWPTP extends WPTelegramPro {
	public static $instance = null;

	public function __construct() {
		parent::__construct();
		add_action( 'wptelegrampro_plugins_settings_content', [ $this, 'settings_content' ] );

		if ( $this->get_option( 'wpstatistics_send_report_notification', false ) ) {
			add_filter( 'wp_statistics_final_text_report_email', [ $this, 'send_report' ] );
		}
	}

	function settings_content() {
		//global $WP_Statistics;
		$this->options = get_option( $this->plugin_key );
		?>
        <tr>
            <th colspan="2" class="title-with-icon">
                <div class="dashicons-before dashicons-chart-pie"><br></div>
                <span>WP Statistics</span>
            </th>
        </tr>
        <tr>
            <td>
                <label for="wpstatistics_send_report_notification"><?php _e( 'Notification for statistical reporting',
						$this->plugin_key ) ?></label>
            </td>
            <td>
                <label><input type="checkbox" value="1" id="wpstatistics_send_report_notification"
                              name="wpstatistics_send_report_notification" <?php checked( $this->get_option( 'wpstatistics_send_report_notification',
						0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                </label>
				<?php
				//if (!$WP_Statistics->get_option('stats_report'))
				echo '<br><span class="description">' . __( 'Requires enable "Statistical reporting" option in the Notification tab in WP Statistics settings.',
						$this->plugin_key ) . '</span>';
				?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Send notification to admin users when new report from WP Statistics
	 *
	 * @param  string  $final_text_report  Text message report
	 *
	 * @return string
	 */
	function send_report( $final_text_report ) {
		$text = "*" . __( 'WP Statistics report', $this->plugin_key ) . "*\n\n";

		$text_report = $final_text_report;
		$text_report = HelpersWPTP::br2nl( $text_report );
		$text_report = wp_strip_all_tags( $text_report );
		$text        .= $text_report . "\n";

		$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";
		$text = apply_filters( 'wptelegrampro_wpstatistics_send_report_notification_text', $text, $final_text_report );

		if ( ! $text )
			return $final_text_report;

		$users = $this->get_users( [ 'Administrator' ], [ 'telegram_receive_plugins_notification' => 1 ] );
		if ( $users ) {
			$this->telegram->disable_web_page_preview( true );
			$keyboard  = array(
				array(
					array(
						'text' => __( 'WP Statistics Dashboard', $this->plugin_key ),
						'url'  => admin_url( 'admin.php?page=wps_overview_page' )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );

			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
			}
		}

		return $final_text_report;
	}

	/**
	 * Returns an instance of class
	 * @return WPStatisticsWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new WPStatisticsWPTP();

		return self::$instance;
	}
}

$WPStatisticsWPTP = WPStatisticsWPTP::getInstance();