<?php

namespace wptelegrampro;

global $WordPressWPTP;

class WordPressWPTP extends WPTelegramPro {
	public static $instance = null;
	protected $tabID = 'wordpress-wptp-tab';

	public function __construct() {
		parent::__construct();
		$this->words = apply_filters( 'wptelegrampro_words', $this->words );

		add_filter( 'wptelegrampro_patterns_tags', [ $this, 'patterns_tags' ] );
		add_filter( 'wptelegrampro_settings_tabs', [ $this, 'settings_tab' ], 10 );
		add_action( 'wptelegrampro_settings_content', [ $this, 'settings_content' ] );
		add_action( 'wptelegrampro_inline_keyboard_response', array( $this, 'inline_keyboard' ) );
		add_action( 'wptelegrampro_keyboard_response', array( $this, 'post_action' ) );
		add_action( 'wptelegrampro_keyboard_response', array( $this, 'check_keyboard_need_update' ), 9999 );
		add_filter( 'wptelegrampro_before_settings_update_message', array( $this, 'update_api_token' ), 10, 3 );
		add_filter( 'wptelegrampro_option_settings', array( $this, 'update_bot_username' ), 100, 2 );
		add_filter( 'wptelegrampro_default_keyboard', [ $this, 'default_keyboard' ], 10 );
		add_filter( 'wptelegrampro_default_commands', [ $this, 'default_commands' ], 10 );

		add_action( 'show_user_profile', [ $this, 'user_profile' ] );
		add_action( 'edit_user_profile', [ $this, 'user_profile' ] );
		add_action( 'personal_options_update', [ $this, 'save_profile_fields' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_profile_fields' ] );
		add_action( 'wp_before_admin_bar_render', [ $this, 'admin_bar_render' ] );
		add_action( 'admin_notices', [ $this, 'user_disconnect' ] );
		add_action( 'init', [ $this, 'user_init' ], 88888 );

		if ( $this->get_option( 'new_comment_notification', false ) )
			add_action( 'comment_post', array( $this, 'comment_notification' ), 10, 2 );
		if ( $this->get_option( 'admin_users_login_notification', false ) )
			add_action( 'wp_login', [ $this, 'admin_users_login_notification' ], 10, 2 );
		if ( $this->get_option( 'user_login_notification', false ) )
			add_action( 'wp_login', [ $this, 'user_login_notification' ], 10, 2 );
		if ( $this->get_option( 'admin_register_new_user_notification', false ) )
			add_action( 'user_register', array( $this, 'admin_register_new_user_notification' ) );
		if ( $this->get_option( 'admin_php_error_notification', false ) )
			add_filter( 'recovery_mode_email', [ $this, 'admin_recovery_mode_notification' ], 1000, 2 );
		if ( $this->get_option( 'admin_auto_core_update_notification', false ) )
			add_filter( 'auto_core_update_email', [ $this, 'admin_auto_core_update_notification' ], 1000, 4 );
	}

	function login_url() {
		$url = wp_login_url();
		$url .= strpos( $url, '?' ) === false ? '?' : '&';
		$url .= 'wptpurid=' . $this->user_field( 'rand_id' );

		return $url;
	}

	function user_init() {
		if ( isset( $_GET['wptpurid'] ) && is_numeric( $_GET['wptpurid'] ) ) {
			if ( is_user_logged_in() ) {
				$this->check_user_id( get_current_user_id(), $_GET['wptpurid'] );
			} else {
				setcookie( 'wptpurid', $_GET['wptpurid'], current_time( 'U' ) + ( 60 * 60 * 12 * 7 ) );
			}
		}
	}

	function user_disconnect() {
		if ( isset( $_GET['user-disconnect-wptp'] ) && $this->disconnect_telegram_wp_user( isset( $_GET['user_id'] ) ? $_GET['user_id'] : null ) ) {
			if ( isset( $_GET['user_id'] ) && get_current_user_id() != $_GET['user_id'] )
				$disconnect_message = $this->words['user_disconnect'];
			else
				$disconnect_message = $this->get_option( 'telegram_connectivity_disconnect_message',
					$this->words['profile_disconnect'] );
			if ( ! empty( $disconnect_message ) )
				echo '<div class="notice notice-info is-dismissible">
                      <p>' . $disconnect_message . '</p>
                     </div>';
		}
	}

	function admin_bar_render() {
		global $wp_admin_bar;
		if ( ! $this->get_option( 'telegram_connectivity', false ) )
			return;

		if ( $user_id = get_current_user_id() ) {
			$bot_user = $this->set_user( array( 'wp_id' => $user_id ) );
			if ( ! $bot_user && $link = $this->get_bot_connect_link( $user_id ) )
				$wp_admin_bar->add_menu( array(
					'parent' => 'user-actions',
					'id'     => 'connect_telegram',
					'title'  => __( 'Connect to Telegram', $this->plugin_key ),
					'href'   => $link,
					'meta'   => array( 'target' => '_blank' )
				) );
		}
	}

	function user_profile( $user ) {
		if ( ! $this->get_option( 'telegram_connectivity', false ) )
			return;

		$bot_user = $this->set_user( array( 'wp_id' => $user->ID ) );
		?>
        <h2 id="wptp"><?php _e( 'Telegram', $this->plugin_key ); ?></h2>
        <table class="form-table">
            <tr>
				<?php if ( $bot_user ) { ?>
                    <th>
						<?php _e( 'Connect', $this->plugin_key ) ?>
                    </th>
                    <td><?php echo __( 'This profile has been linked to this Telegram account:',
								$this->plugin_key ) . ' ' . $bot_user['first_name'] . ' ' . $bot_user['last_name'] . ' <a href="https://t.me/' . $bot_user['username'] . '" target="_blank">@' . $bot_user['username'] . '</a> (<a href="' . $this->get_bot_disconnect_link( $user->ID ) . '">' . __( 'Disconnect',
								$this->plugin_key ) . '</a>)'; ?></td>
				<?php } else {
					$code = $this->get_user_random_code( $user->ID );
					?>
                    <th>
                        <label for="telegram_user_code"><?php _e( 'Telegram Bot Code', $this->plugin_key ) ?></label>
                    </th>
                    <td>
                        <input type="text" id="telegram_user_code" class="regular-text ltr"
                               value="<?php echo $code ?>"
                               onfocus="this.select();" onmouseup="return false;"
                               readonly> <?php echo __( 'Or',
								$this->plugin_key ) . ' <a href="' . $this->get_bot_connect_link( $user->ID ) . '" target="_blank">' . __( 'Request Connect',
								$this->plugin_key ) . '</a>' ?>
                        <br>
                        <span class="description"><?php _e( 'Send this code from telegram bot to identify the your user.',
								$this->plugin_key ) ?></span>
                    </td>
				<?php } ?>
            </tr>
            <tr>
                <th><?php _e( 'Receive Plugins Notification', $this->plugin_key ) ?></th>
                <td><label><input type="checkbox" name="telegram_receive_plugins_notification" value="1"
							<?php checked( get_user_meta( $user->ID, 'telegram_receive_plugins_notification',
								true ), 1 ) ?>> <?php _e( 'Active',
							$this->plugin_key ) ?></label></td>
            </tr>
        </table>
		<?php
	}

	/**
	 * Save user profile meta fields
	 *
	 * @param  integer  $user_id  User ID
	 *
	 * @return void
	 * */
	function save_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) )
			return;

		update_user_meta( $user_id, 'telegram_receive_plugins_notification',
			intval( isset( $_POST['telegram_receive_plugins_notification'] ) ) );
	}

	function default_commands( $commands ) {
		$commands = array_merge( $commands,
			array(
				'start'      => __( 'Start Bot', $this->plugin_key ),
				'posts'      => __( 'Posts', $this->plugin_key ),
				'categories' => __( 'Categories List', $this->plugin_key ),
				'search'     => __( 'Search', $this->plugin_key )
			) );

		return $commands;
	}

	function default_keyboard( $keyboard ) {
		$this->words  = apply_filters( 'wptelegrampro_words', $this->words );
		$new_keyboard = array();

		if ( $this->get_option( 'display_posts_buttons', false ) )
			$new_keyboard = array(
				$this->words['posts'],
				$this->words['categories']
			);

		$search_post_type = $this->get_option( 'search_post_type', array() );
		if ( count( $search_post_type ) )
			$new_keyboard[] = $this->words['search'];

		$keyboard[] = is_rtl() ? array_reverse( $new_keyboard ) : $new_keyboard;

		return $keyboard;
	}

	function update_api_token( $update_message, $current_option, $new_option ) {
		if ( $this->get_option( 'api_token' ) != $new_option['api_token'] ) {
			$telegram = new TelegramWPTP( $new_option['api_token'] );
			$webHook  = $this->webHookURL();
			$webHook  = apply_filters( 'wptelegrampro_set_webhook', $webHook );
			$set      = $telegram->setWebhook( $webHook['url'] );

			if ( $set ) {
				$update_message .= $this->message( __( 'Set Webhook Successfully.', $this->plugin_key ) );
				update_option( 'wptp-rand-url', $webHook['rand'], false );
				$this->telegram = $telegram;
			} else
				$update_message .= $this->message( __( 'Set Webhook with Error!', $this->plugin_key ), 'error' );
		}

		if ( isset( $new_option['force_update_keyboard'] ) )
			update_option( 'update_keyboard_time_wptp', current_time( 'U' ), false );

		return $update_message;
	}

	function update_bot_username( $new_option, $current_option ) {
		if ( $this->get_option( 'api_token' ) != $new_option['api_token'] || ! $this->get_option( 'api_bot_username',
				false ) ) {
			$this->telegram->bot_info();
			$bot_info = $this->telegram->get_last_result();
			if ( $bot_info['ok'] && $bot_info['result']['is_bot'] )
				$new_option['api_bot_username'] = $bot_info['result']['username'];
		}

		return $new_option;
	}

	function patterns_tags( $tags ) {
		$tags['WordPress'] = array(
			'title' => __( 'WordPress Tags', $this->plugin_key ),
			'tags'  => array(
				'ID'                                    => __( 'The ID of this post', $this->plugin_key ),
				'title'                                 => __( 'The title of this post', $this->plugin_key ),
				'slug'                                  => __( 'The Slug of this post', $this->plugin_key ),
				'excerpt'                               => __( 'The first 55 words of this post', $this->plugin_key ),
				'content'                               => __( 'The whole content of this post', $this->plugin_key ),
				'author'                                => __( 'The display name of author of this post',
					$this->plugin_key ),
				'author-link'                           => __( 'The permalink of this author posts',
					$this->plugin_key ),
				'link'                                  => __( 'The permalink of this post', $this->plugin_key ),
				'short-link'                            => __( 'The short url of this post', $this->plugin_key ),
				'tags'                                  => __( 'The tags of this post. Tags are automatically converted to Telegram hashtags',
					$this->plugin_key ),
				'categories'                            => __( 'The categories of this post. Categories are automatically separated by | symbol',
					$this->plugin_key ),
				'image'                                 => __( 'The featured image URL', $this->plugin_key ),
				'cf:'                                   => __( 'The custom field of this post, Example {cf:price}',
					$this->plugin_key ),
				'terms:'                                => __( 'The Taxonomy Terms of this post: {terms:taxonomy}, Example {terms:category}',
					$this->plugin_key ),
				"if='cf:custom_field_name'}content{/if" => __( "IF Statement for custom field, Example: {if='cf:price'}Price: {cf:price}{/if}",
					$this->plugin_key )
			)
		);

		return $tags;
	}

	/**
	 * Send notification to user when logged in
	 *
	 * @param  string  $user_login  Username.
	 * @param  WP_User  $user  WP_User object of the logged-in user.
	 */
	function user_login_notification( $user_login, $user ) {
		$bot_user = $this->set_user( array( 'wp_id' => $user->ID ) );
		if ( $bot_user ) {
			$userIP = HelpersWPTP::getUserIP();
			$text   = "*" . sprintf( __( 'Dear %s', $this->plugin_key ), $user->display_name ) . "*\n";
			/* translators: 1: User name 2: Date 3: Time 4: IP address */
			$text .= sprintf( __( 'Your successful login to %1$s account on date %2$s at %3$s with %4$s IP address done.',
				$this->plugin_key ), $user_login, HelpersWPTP::localeDate( null, "l j F Y" ),
				HelpersWPTP::localeDate( null, "H:i" ), "[{$userIP}](http://{$userIP}.ipaddress.com)" );

			$text = apply_filters( 'wptelegrampro_user_login_notification_text', $text, $user_login, $user );

			if ( $text ) {
				$keyboard  = array(
					array(
						array(
							'text' => __( 'Display website', $this->plugin_key ),
							'url'  => get_bloginfo( 'url' )
						)
					)
				);
				$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );
				$this->telegram->disable_web_page_preview( true );
				$this->telegram->sendMessage( $text, $keyboards, $bot_user['user_id'], 'Markdown' );
			}
		}
	}

	/**
	 * Send notification to admin users when user login
	 *
	 * @param  string  $user_login  Username.
	 * @param  WP_User  $user  WP_User object of the logged-in user.
	 */
	function admin_users_login_notification( $user_login, $user ) {
		$user_role = $this->get_user_role( $user );
		if ( ! $user_role || $user_role == 'administrator' )
			return;

		$users = $this->get_users( [ 'Administrator' ] );
		if ( $users ) {
			$keyboard  = array(
				array(
					array(
						'text' => '📝',
						'url'  => admin_url( 'user-edit.php?user_id=' . $user->ID )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'users.php' )
					)
				)
			);
			$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );
			$userIP    = HelpersWPTP::getUserIP();

			$text = "*" . __( 'User login', $this->plugin_key ) . "*\n\n";

			$text .= __( 'User name', $this->plugin_key ) . ': ' . $user_login . "\n";
			$text .= __( 'Name', $this->plugin_key ) . ': ' . $user->display_name . "\n";
			$text .= __( 'User IP', $this->plugin_key ) . ': ' . "[{$userIP}](http://{$userIP}.ipaddress.com)" . "\n";

			$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

			$text = apply_filters( 'wptelegrampro_admin_users_login_notification_text', $text, $user_login, $user );

			if ( $text ) {
				$this->telegram->disable_web_page_preview( true );
				foreach ( $users as $user ) {
					$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
				}
			}
		}
	}

	/**
	 * Send notification to admin users when has been a PHP error
	 *
	 * @param  array  $email  Used to build wp_mail().
	 * @param  string  $url  URL to enter recovery mode.
	 *
	 * @return array
	 */
	function admin_recovery_mode_notification( $email, $url ) {
		$text = $email['message'];
		$text = str_replace( [ __( 'Email' ), __( 'emailed' ), __( 'email' ) ], __( 'notification', $this->plugin_key ),
			$text );
		$text = apply_filters( 'wptelegrampro_admin_recovery_mode_notification_text', $text, $email, $url );

		if ( ! $text )
			return $email;

		$users = $this->get_users();
		if ( $users ) {
			$this->telegram->disable_web_page_preview( true );
			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, null, $user['user_id'] );
			}
		}

		return $email;
	}

	/**
	 * Send notification to admin users when auto core update
	 *
	 * @param  array  $email  (to, subject, body, headers)
	 * @param  string  $type  The type of email being sent. Can be one of
	 *                            'success', 'fail', 'manual', 'critical'.
	 * @param  object  $core_update  The update offer that was attempted.
	 * @param  mixed  $result  The result for the core update. Can be WP_Error.
	 *
	 * @return array
	 */
	function admin_auto_core_update_notification( $email, $type, $core_update, $result ) {
		$text = $email['body'];
		$text = apply_filters( 'wptelegrampro_admin_auto_core_update_notification_text', $text, $email, $type,
			$core_update, $result );

		if ( ! $text )
			return $email;

		$users = $this->get_users();
		if ( $users ) {
			$this->telegram->disable_web_page_preview( true );
			foreach ( $users as $user ) {
				$this->telegram->sendMessage( $text, null, $user['user_id'] );
			}
		}

		return $email;
	}

	/**
	 * Send notification to admin users when new user registered
	 *
	 * @param  int  $user_id  User ID.
	 */
	function admin_register_new_user_notification( $user_id ) {
		$user      = get_userdata( $user_id );
		$user_role = $this->get_user_role( $user );
		if ( ! $user_role || $user_role == 'administrator' )
			return;

		$users = $this->get_users( [ 'Administrator' ] );
		if ( $users ) {
			$keyboard   = array(
				array(
					array(
						'text' => '📝',
						'url'  => admin_url( 'user-edit.php?user_id=' . $user->ID )
					),
					array(
						'text' => '📂',
						'url'  => admin_url( 'users.php' )
					)
				)
			);
			$keyboards  = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );
			$userIP     = HelpersWPTP::getUserIP();
			$user_roles = $this->wp_user_roles();
			$user_role  = $user_roles[ $user_role ];
			$text       = "*" . __( 'Register a new user', $this->plugin_key ) . "*\n\n";

			$text .= __( 'User name', $this->plugin_key ) . ': ' . $user->user_login . "\n";
			$text .= __( 'Name', $this->plugin_key ) . ': ' . $user->display_name . "\n";
			$text .= __( 'User role', $this->plugin_key ) . ': ' . $user_role . "\n";

			$current_user_role = $this->get_user_role();
			if ( ! $current_user_role || $current_user_role != 'administrator' )
				$text .= __( 'User IP',
						$this->plugin_key ) . ': ' . "[{$userIP}](http://{$userIP}.ipaddress.com)" . "\n";

			$text .= __( 'Date', $this->plugin_key ) . ': ' . HelpersWPTP::localeDate() . "\n";

			$text = apply_filters( 'wptelegrampro_admin_register_new_user_notification_text', $text, $user_id, $user );

			if ( $text ) {
				$this->telegram->disable_web_page_preview( true );
				foreach ( $users as $user ) {
					$this->telegram->sendMessage( $text, $keyboards, $user['user_id'], 'Markdown' );
				}
			}
		}
	}

	function comment_notification( $comment_ID, $comment_approved, $message_id = null ) {
		$comment = get_comment( $comment_ID );
		if ( $comment ) {
			$comment_status = wp_get_comment_status( $comment_ID );
			$keyboard_      = array(
				array(
					array(
						'text' => '🔗',
						'url'  => get_permalink( $comment->comment_post_ID )
					),
					array(
						'text' => '💬',
						'url'  => admin_url( 'edit-comments.php' )
					)
				)
			);

			if ( $message_id === null && $users = $this->get_users() ) {
				$text = "*" . __( 'New Comment', $this->plugin_key ) . "*\n\n";
				$text .= __( 'Post' ) . ': ' . get_the_title( $comment->comment_post_ID ) . "\n";
				$text .= __( 'Author' ) . ': ' . $comment->comment_author . "\n";
				if ( ! empty( $comment->comment_author_email ) )
					$text .= __( 'Email' ) . ': ' . $comment->comment_author_email . "\n";
				if ( ! empty( $comment->comment_author_url ) )
					$text .= __( 'Website' ) . ': ' . $comment->comment_author_url . "\n";
				if ( ! empty( $comment->comment_content ) )
					$text .= __( 'Comment' ) . ":\n" . stripslashes( strip_tags( $comment->comment_content ) ) . "\n";

				$text = apply_filters( 'wptelegrampro_wp_new_comment_notification_text', $text, $comment, $comment_ID );

				if ( $text )
					foreach ( $users as $user ) {
						if ( $user['wp_id'] == $comment->user_id )
							continue;
						$keyboard = $keyboard_;
						$this->telegram->sendMessage( $text, null, $user['user_id'], 'Markdown' );
						$message_id    = $this->telegram->get_last_result()['result']['message_id'];
						$keyboard[0][] = array(
							'text'          => '🚮',
							'callback_data' => 'comment_trash_' . $comment_ID . '_' . $message_id
						);
						if ( $comment_approved )
							$keyboard[0][] = array(
								'text'          => '💊',
								'callback_data' => 'comment_hold_' . $comment_ID . '_' . $message_id
							);
						else
							$keyboard[0][] = array(
								'text'          => '✔️',
								'callback_data' => 'comment_approve_' . $comment_ID . '_' . $message_id
							);

						$keyboard[0][] = array(
							'text'          => '🛡️',
							'callback_data' => 'comment_spam_' . $comment_ID . '_' . $message_id
						);
						$keyboards     = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );
						$this->telegram->editMessageReplyMarkup( $keyboards, $message_id, $user['user_id'] );
					}
			} elseif ( $message_id !== null ) {
				if ( $comment_status === 'trash' )
					$keyboard_[0][] = array(
						'text'          => '🔄',
						'callback_data' => 'comment_untrash_' . $comment_ID . '_' . $message_id
					);
				else
					$keyboard_[0][] = array(
						'text'          => '🚮',
						'callback_data' => 'comment_trash_' . $comment_ID . '_' . $message_id
					);

				if ( $comment_status === 'approved' )
					$keyboard_[0][] = array(
						'text'          => '💊',
						'callback_data' => 'comment_hold_' . $comment_ID . '_' . $message_id
					);
				else
					$keyboard_[0][] = array(
						'text'          => '✔️',
						'callback_data' => 'comment_approve_' . $comment_ID . '_' . $message_id
					);

				if ( $comment_status !== 'spam' )
					$keyboard_[0][] = array(
						'text'          => '🛡️',
						'callback_data' => 'comment_spam_' . $comment_ID . '_' . $message_id
					);
				$keyboards = $this->telegram->keyboard( $keyboard_, 'inline_keyboard' );
				$this->telegram->editMessageReplyMarkup( $keyboards, $message_id );
			}
		}
	}

	function send_posts( $posts ) {
		if ( ! is_array( $posts['parameter']['post_type'] ) )
			$posts['parameter']['post_type'] = array( $posts['parameter']['post_type'] );

		$image_send_mode = apply_filters( 'wptelegrampro_image_send_mode', 'image_path' );

		$posts_ = array();
		foreach ( $posts['parameter']['post_type'] as $post_type ) {
			if ( isset( $posts[ $post_type ] ) )
				$posts_ = array_merge( $posts_, $posts[ $post_type ] );
		}

		if ( count( $posts_ ) > 0 ) {
			$this->words  = apply_filters( 'wptelegrampro_words', $this->words );
			$i            = 1;
			$current_page = $this->user['page'];
			//$this->telegram->sendMessage(serialize($posts));
			foreach ( $posts_ as $post ) {
				$keyboard = array(
					array(
						array( 'text' => '🔗️ ' . $this->words['more'], 'url' => $post['link'] )
					)
				);
				$text     = $post['title'] . "\n" . $post['excerpt'] . "\n\n" . $post['short-link'];
				if ( $posts['max_num_pages'] > 1 && $i == count( $posts_ ) ) {
					$keyboard[1] = array();
					if ( $current_page > 1 )
						$keyboard[1][] = array(
							'text'          => $this->words['prev_page'],
							'callback_data' => 'posts_page_prev'
						);
					if ( $current_page < $posts['max_num_pages'] )
						$keyboard[1][] = array(
							'text'          => $this->words['next_page'],
							'callback_data' => 'posts_page_next'
						);
					if ( is_rtl() )
						$keyboard[1] = array_reverse( $keyboard[1] );
				}
				$keyboards = $this->telegram->keyboard( $keyboard, 'inline_keyboard' );
				$this->telegram->disable_web_page_preview( true );
				if ( $post[ $image_send_mode ] !== null )
					$this->telegram->sendFile( 'sendPhoto', $post[ $image_send_mode ], $text, $keyboards );
				else
					$this->telegram->sendMessage( $text, $keyboards );
				$i ++;
			}
		} else {
			$this->telegram->sendMessage( __( 'Your request without result!', $this->plugin_key ) );
		}
	}

	function check_keyboard_need_update() {
		$system_time = get_option( 'update_keyboard_time_wptp' );
		if ( ! empty( $system_time ) ) {
			$update_keyboard_time = $this->get_user_meta( 'update_keyboard_time' );
			if ( empty( $update_keyboard_time ) || $system_time > $update_keyboard_time ) {
				$default_keyboard = apply_filters( 'wptelegrampro_default_keyboard', array() );
				$default_keyboard = $this->telegram->keyboard( $default_keyboard );
				$this->telegram->sendMessage( __( 'Update' ), $default_keyboard );
				$this->update_user_meta( 'update_keyboard_time', current_time( 'U' ) );
			}
		}
	}

	function post_action( $user_text ) {
		$this->set_user();
		$this->words    = $words = apply_filters( 'wptelegrampro_words', $this->words );
		$current_status = $this->user_field( 'status' );

		if ( $user_text == '/start' || strpos( $user_text, '/start' ) !== false ) {
			$message          = $this->get_option( 'start_command' );
			$message          = empty( trim( $message ) ) ? __( 'Welcome!', $this->plugin_key ) : $message;
			$default_keyboard = apply_filters( 'wptelegrampro_default_keyboard', array() );
			$default_keyboard = $this->telegram->keyboard( $default_keyboard );
			$this->telegram->sendMessage( $message, $default_keyboard );

		} elseif ( $user_text == '/search' || $user_text == $words['search'] ) {
			$this->telegram->sendMessage( __( 'Enter word for search:', $this->plugin_key ) );

		} elseif ( $current_status == 'search' ) {
			$this->update_user_meta( 'search_query', $user_text );
			$this->update_user_meta( 'category_id', null );
			$this->update_user( array( 'page' => 1 ) );
			$args  = array(
				'post_type' => $this->get_option( 'search_post_type', array() ),
				's'         => $user_text,
				'per_page'  => $this->get_option( 'posts_per_page', 1 )
			);
			$posts = $this->query( $args );
			$this->send_posts( $posts );

		} elseif ( $user_text == '/posts' || $user_text == $words['posts'] ) {
			$this->update_user( array( 'page' => 1 ) );
			$this->update_user_meta( 'category_id', null );

			$args  = array(
				'post_type' => 'post',
				'per_page'  => $this->get_option( 'posts_per_page', 1 )
			);
			$posts = $this->query( $args );
			$this->send_posts( $posts );

		} elseif ( $user_text == '/categories' || $user_text == $words['categories'] ) {
			$posts_category = $this->get_tax_keyboard( 'category', 'category', 'parent' );
			$keyboard       = $this->telegram->keyboard( $posts_category, 'inline_keyboard' );
			$this->telegram->sendMessage( $words['categories'] . ":", $keyboard );
		}
	}

	function inline_keyboard( $data ) {
		$this->set_user();
		$button_data = $data['data'];

		if ( $this->button_data_check( $button_data, 'posts_page_' ) ) {
			$current_page = intval( $this->user['page'] ) == 0 ? 1 : intval( $this->user['page'] );
			if ( $button_data == 'posts_page_next' )
				$current_page ++;
			else
				$current_page --;
			$this->update_user( array( 'page' => $current_page ) );
			$this->telegram->answerCallbackQuery( __( 'Page' ) . ': ' . $current_page );
			$args = array(
				'category_id' => $this->get_user_meta( 'category_id' ),
				'post_type'   => 'post',
				'per_page'    => $this->get_option( 'posts_per_page', 1 )
			);

			$search_query = $this->get_user_meta( 'search_query' );
			if ( $search_query != null ) {
				$args['post_type'] = $this->get_option( 'search_post_type', array() );
				$args['s']         = $search_query;
			}

			$products = $this->query( $args );
			$this->send_posts( $products );

		} elseif ( $this->button_data_check( $button_data, 'category' ) ) {
			$this->update_user( array( 'page' => 1 ) );
			$category_id = intval( end( explode( '_', $button_data ) ) );
			$this->update_user_meta( 'category_id', $category_id );
			$product_category = get_term( $category_id, 'category' );
			if ( $product_category ) {
				$this->telegram->answerCallbackQuery( __( 'Category' ) . ': ' . $product_category->name );
				$products = $this->query( array(
					'category_id' => $category_id,
					'per_page'    => $this->get_option( 'posts_per_page', 1 ),
					'post_type'   => 'post'
				) );
				$this->send_posts( $products );
			} else {
				$this->telegram->answerCallbackQuery( __( 'Post Category Invalid!', $this->plugin_key ) );
			}

		} elseif ( $this->button_data_check( $button_data, 'comment' ) ) {
			$button_data = explode( '_', $button_data );
			$comment_ID  = $button_data[2];
			$new_status  = $button_data[1];
			$comment     = get_comment( $comment_ID );
			if ( $comment ) {
				$status_message = __( 'New Status:', $this->plugin_key ) . ' ';
				if ( $new_status == 'trash' ) {
					wp_delete_comment( $comment_ID );
					$status_message .= __( 'Trash' );
				} elseif ( $new_status == 'untrash' ) {
					wp_untrash_comment( $comment_ID );
					$status_message .= __( 'Restore from Trash', $this->plugin_key );
				} else {
					if ( $new_status == 'hold' )
						$status_message .= __( 'Unapprove', $this->plugin_key );
                    elseif ( $new_status == 'approve' )
						$status_message .= __( 'Approve', $this->plugin_key );
                    elseif ( $new_status == 'spam' )
						$status_message .= __( 'Spam' );
					wp_untrash_comment( $comment_ID );
					wp_set_comment_status( $comment_ID, $new_status );
				}
				$this->telegram->answerCallbackQuery( $status_message );
				$comment_status = wp_get_comment_status( $comment_ID );
				$this->comment_notification( $comment_ID, $comment_status == 'approved', $button_data[3] );
			} else {
				$this->telegram->answerCallbackQuery( __( 'Not Found Comment!', $this->plugin_key ) );
			}
		}
	}

	function settings_tab( $tabs ) {
		$tabs[ $this->tabID ] = __( 'WordPress', $this->plugin_key );

		return $tabs;
	}

	function settings_content() {
		$this->options = get_option( $this->plugin_key );
		$post_types    = get_post_types( [ 'public' => true, 'exclude_from_search' => false, 'show_ui' => true ],
			"objects" );
		?>
        <div id="<?php echo $this->tabID ?>-content" class="wptp-tab-content">
            <table>
                <tr>
                    <td><label for="api_token"><?php _e( 'Telegram Bot API Token', $this->plugin_key ) ?></label>
                    </td>
                    <td>
                        <input type="password" name="api_token" id="api_token"
                               value="<?php echo $this->get_option( 'api_token' ) ?>"
                               class="regular-text ltr api-token">
                        <span class="dashicons dashicons-info bot-info-wptp"></span>
                        <input type="hidden" name="api_bot_username"
                               value="<?php echo $this->get_option( 'api_bot_username' ) ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="posts_per_page"><?php _e( 'Posts Per Page', $this->plugin_key ) ?></label>
                    </td>
                    <td><input type="number" name="posts_per_page" id="posts_per_page"
                               value="<?php echo $this->get_option( 'posts_per_page', $this->per_page ) ?>"
                               min="1" class="small-text ltr"></td>
                </tr>
                <tr>
                    <td>
                        <label for="image_size"><?php _e( 'Image Size', $this->plugin_key ) ?></label>
                    </td>
                    <td><?php echo $this->image_size_select( 'image_size', $this->get_option( 'image_size' ),
							'---' ) ?></td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e( 'Messages', $this->plugin_key ) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="start_command"><?php _e( 'Start Command<br>(Welcome Message)',
								$this->plugin_key ); ?></label>
                    </td>
                    <td>
                        <textarea name="start_command" id="start_command" cols="50" class="emoji"
                                  rows="4"><?php echo $this->get_option( 'start_command',
		                        sprintf( __( 'Welcome to %s', $this->plugin_key ),
			                        get_bloginfo( 'name' ) ) ) ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e( 'Users', $this->plugin_key ) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="telegram_connectivity"><?php _e( 'Telegram Connectivity',
								$this->plugin_key ) ?></label>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="telegram_connectivity"
                                      name="telegram_connectivity" <?php checked( $this->get_option( 'telegram_connectivity' ),
								1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                        </label><br>
                        <span class="description">
                            <?php _e( 'Enable the ability to connect the WordPress profile to the Telegram account. This feature is displayed in the user profile, Admin bar, and WooCommerce account details.',
	                            $this->plugin_key ) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="telegram_connectivity_success_connect_message"><?php _e( 'Successful connect message',
								$this->plugin_key ) ?></label>
                    </td>
                    <td>
                        <textarea name="telegram_connectivity_success_connect_message"
                                  id="telegram_connectivity_success_connect_message" cols="50" class="emoji"
                                  rows="2"><?php echo $this->get_option( 'telegram_connectivity_success_connect_message',
		                        $this->words['profile_success_connect'] ) ?></textarea>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="telegram_connectivity_disconnect_message"><?php _e( 'Disconnect message',
								$this->plugin_key ) ?></label>
                    </td>
                    <td>
                        <textarea name="telegram_connectivity_disconnect_message"
                                  id="telegram_connectivity_disconnect_message" cols="50" class="emoji"
                                  rows="2"><?php echo $this->get_option( 'telegram_connectivity_disconnect_message',
		                        $this->words['profile_disconnect'] ) ?></textarea>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="telegram_bot_two_factor_auth"><?php _e( 'Two factor auth',
								$this->plugin_key ) ?></label>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="telegram_bot_two_factor_auth"
                                      name="telegram_bot_two_factor_auth" <?php checked( $this->get_option( 'telegram_bot_two_factor_auth',
								0 ), 1 ) ?>> <?php _e( 'Enable Two Step Telegram bot Auth', $this->plugin_key ) ?>
                        </label>
                        <p class="description">
							<?php _e( 'Verify text code for each login attempt. Users need to setup the Telegram account in their profile. To ensure no conflict with other plugins, Disable other Two step authentication.',
								$this->plugin_key ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="telegram_bot_force_two_factor_auth"><?php _e( 'Force Telegram bot auth validation',
								$this->plugin_key ) ?></label>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="telegram_bot_force_two_factor_auth"
                                      name="telegram_bot_force_two_factor_auth" <?php checked( $this->get_option( 'telegram_bot_force_two_factor_auth',
								0 ), 1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                        </label>
                        <p class="description">
							<?php _e( 'If enabled this, any user without linked Telegram account in profile will not be able to login.',
								$this->plugin_key ); ?>
							<?php
							if ( ! $this->user )
								echo '<div class="wptp-warning-h3">' . __( 'You need to setup your Telegram Connectivity before enabling this setting to avoid yourself being blocked from next time login.',
										$this->plugin_key ) .
								     '<br><a href="profile.php#wptp">' . __( 'Click here to connect the WordPress profile to the Telegram account',
										$this->plugin_key ) . '</a>' .
								     '</div>';
							?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e( 'Notification', $this->plugin_key ) ?></th>
                </tr>
                <tr>
                    <td>
						<?php _e( 'Administrators', $this->plugin_key ); ?>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="admin_php_error_notification"
                                      name="admin_php_error_notification" <?php checked( $this->get_option( 'admin_php_error_notification' ),
								1 ) ?>> <?php _e( 'Trying to send recovery mode (PHP errors) notification',
								$this->plugin_key ) ?>
                        </label><br>
                        <label><input type="checkbox" value="1" id="admin_auto_core_update_notification"
                                      name="admin_auto_core_update_notification" <?php checked( $this->get_option( 'admin_auto_core_update_notification' ),
								1 ) ?>> <?php _e( 'Auto core update', $this->plugin_key ) ?>
                        </label><br>
                        <label><input type="checkbox" value="1" id="new_comment_notification"
                                      name="new_comment_notification" <?php checked( $this->get_option( 'new_comment_notification' ),
								1 ) ?>> <?php _e( 'New comment', $this->plugin_key ) ?>
                        </label><br>
                        <label><input type="checkbox" value="1" id="admin_users_login_notification"
                                      name="admin_users_login_notification" <?php checked( $this->get_option( 'admin_users_login_notification' ),
								1 ) ?>> <?php _e( 'Users login', $this->plugin_key ) ?>
                        </label><br>
                        <label><input type="checkbox" value="1" id="admin_register_new_user_notification"
                                      name="admin_register_new_user_notification" <?php checked( $this->get_option( 'admin_register_new_user_notification' ),
								1 ) ?>> <?php _e( 'Register a new user', $this->plugin_key ) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td>
						<?php _e( 'Users', $this->plugin_key ) ?>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="user_login_notification"
                                      name="user_login_notification" <?php checked( $this->get_option( 'user_login_notification' ),
								1 ) ?>> <?php _e( 'User login', $this->plugin_key ) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e( 'Posts', $this->plugin_key ) ?></th>
                </tr>
                <tr>
                    <td>
						<?php _e( 'Posts Buttons', $this->plugin_key ) ?>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="display_posts_buttons"
                                      name="display_posts_buttons" <?php checked( $this->get_option( 'display_posts_buttons' ),
								1 ) ?>> <?php _e( 'Active', $this->plugin_key ) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e( 'Search', $this->plugin_key ) ?></th>
                </tr>
                <tr>
                    <td>
						<?php _e( 'Post Type', $this->plugin_key ) ?>
                    </td>
                    <td>
						<?php
						$search_post_type = $this->get_option( 'search_post_type', array() );
						foreach ( $post_types as $post_type ) {
							if ( ! in_array( $post_type->name, $this->ignore_post_types ) )
								echo '<label><input type="checkbox" name="search_post_type[]" value="' . $post_type->name . '" ' . checked( in_array( $post_type->name,
										$search_post_type ), true, false ) . ' > ' . $post_type->label . '</label>';
						}
						?>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e( 'Telegram Keyboard', $this->plugin_key ) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="force_update_keyboard"><?php _e( 'Force update keyboard',
								$this->plugin_key ) ?></label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" value="1" id="force_update_keyboard"
                                   name="force_update_keyboard"> <?php _e( 'Update' ) ?>
                        </label><br>
                        <span class="description">
                            <?php _e( "You should update keyboard for users when change WordPress language, Active Woocommerce plugin, Posts buttons or search setting changed. (Status don't save)",
	                            $this->plugin_key ) ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
		<?php
	}

	/**
	 * Returns an instance of class
	 * @return WordPressWPTP
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new WordPressWPTP();

		return self::$instance;
	}
}

$WordPressWPTP = WordPressWPTP::getInstance();