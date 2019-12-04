<?php

class WordPressWPTP extends WPTelegramPro
{
    public static $instance = null;
    protected $tabID = 'wordpress-wptp-tab';

    public function __construct()
    {
        parent::__construct(true);

        add_filter('wptelegrampro_patterns_tags', [$this, 'patterns_tags']);
        add_filter('wptelegrampro_settings_tabs', [$this, 'settings_tab'], 10);
        add_action('wptelegrampro_settings_content', [$this, 'settings_content']);
        add_action('wptelegrampro_inline_keyboard_response', array($this, 'inline_keyboard'));
        add_action('wptelegrampro_keyboard_response', array($this, 'keyboard'));
        add_action('wptelegrampro_keyboard_response', array($this, 'check_keyboard_need_update'), 9999);
        add_filter('wptelegrampro_before_settings_update_message', array($this, 'update_api_token'), 10, 3);
        add_filter('wptelegrampro_option_settings', array($this, 'update_bot_username'), 100, 2);
        add_filter('wptelegrampro_default_keyboard', [$this, 'default_keyboard'], 10);
        add_filter('wptelegrampro_default_commands', [$this, 'default_commands'], 10);

        add_action('show_user_profile', [$this, 'user_profile']);
        add_action('edit_user_profile', [$this, 'user_profile']);
        add_action('wp_before_admin_bar_render', [$this, 'admin_bar_render']);
        add_action('admin_notices', [$this, 'user_disconnect']);

        if (isset($this->options['new_comment_notification']))
            add_action('comment_post', array($this, 'comment_notification'), 10, 2);
    }

    function user_disconnect()
    {
        if (isset($_GET['user-disconnect-wptp']) && $this->disconnect_telegram_wp_user(isset($_GET['user_id']) ? $_GET['user_id'] : null))
            echo '<div class="notice notice-info is-dismissible">
          <p>' . __('Your profile was successfully disconnected from Telegram account.', $this->plugin_key) . '</p>
         </div>';
    }

    function admin_bar_render()
    {
        global $wp_admin_bar;
        if (!$this->get_option('telegram_connectivity', false)) return;

        if ($user_id = get_current_user_id()) {
            $bot_user = $this->set_user(array('wp_id' => $user_id));
            if (!$bot_user && $link = $this->get_bot_connect_link($user_id))
                $wp_admin_bar->add_menu(array(
                    'parent' => 'user-actions',
                    'id' => 'connect_telegram',
                    'title' => __('Connect to Telegram', $this->plugin_key),
                    'href' => $link,
                    'meta' => array('target' => '_blank')
                ));
        }
    }

    function user_profile($user)
    {
        if (!$this->get_option('telegram_connectivity', false)) return;

        $bot_user = $this->set_user(array('wp_id' => $user->ID));
        ?>
        <h2><?php _e('Telegram', $this->plugin_key); ?></h2>
        <table class="form-table">
            <tr>
                <?php if ($bot_user) { ?>
                    <th colspan="2"><?php echo __('Your profile has been linked to this Telegram account:', $this->plugin_key) . ' ' . $bot_user['first_name'] . ' ' . $bot_user['last_name'] . ' <a href="https://t.me/' . $bot_user['username'] . '" target="_blank">@' . $bot_user['username'] . '</a> (<a href="' . $this->get_bot_disconnect_link($user->ID) . '">' . __('Disconnect', $this->plugin_key) . '</a>)'; ?></th>
                <?php } else {
                    $code = $this->get_user_random_code($user->ID);
                    ?>
                    <th>
                        <label for="telegram_user_code"><?php _e('Telegram Bot Code', $this->plugin_key) ?></label>
                    </th>
                    <td>
                        <input type="text" id="telegram_user_code" class="regular-text ltr"
                               value="<?php echo $code ?>"
                               onfocus="this.select();" onmouseup="return false;"
                               readonly> <?php echo __('Or', $this->plugin_key) . ' <a href="' . $this->get_bot_connect_link($user->ID) . '" target="_blank">' . __('Request Connect', $this->plugin_key) . '</a>' ?>
                        <br>
                        <span class="description"><?php _e('Send this code from telegram bot to identify the your user.', $this->plugin_key) ?></span>
                    </td>
                <?php } ?>
            </tr>
        </table>
        <?php
    }

    function default_commands($commands)
    {
        $commands = array_merge($commands,
            array(
                'start' => __('Start Bot', $this->plugin_key),
                'posts' => __('Posts', $this->plugin_key),
                'categories' => __('Categories List', $this->plugin_key),
                'search' => __('Search', $this->plugin_key)
            ));
        return $commands;
    }

    function default_keyboard($keyboard)
    {
        $this->words = apply_filters('wptelegrampro_words', $this->words);
        $new_keyboard = array(
            $this->words['posts'],
            $this->words['categories']
        );

        $search_post_type = $this->get_option('search_post_type', array());
        if (count($search_post_type))
            $new_keyboard[] = $this->words['search'];

        $keyboard[] = is_rtl() ? array_reverse($new_keyboard) : $new_keyboard;

        return $keyboard;
    }

    function update_api_token($update_message, $current_option, $new_option)
    {
        if ($this->get_option('api_token') != $new_option['api_token']) {
            $telegram = new TelegramWPTP($new_option['api_token']);
            $webHook = $this->webHookURL();
            if ($telegram->setWebhook($webHook['url'])) {
                $update_message .= $this->message(__('Set Webhook Successfully.', $this->plugin_key));
                update_option('wptp-rand-url', $webHook['rand'], false);
                $this->telegram = $telegram;
            } else
                $update_message .= $this->message(__('Set Webhook with Error!', $this->plugin_key), 'error');
        }

        if (isset($new_option['force_update_keyboard']))
            update_option('update_keyboard_time_wptp', time(), false);

        return $update_message;
    }

    function update_bot_username($new_option, $current_option)
    {
        if ($this->get_option('api_token') != $new_option['api_token'] || !$this->get_option('api_bot_username', false)) {
            $this->telegram->bot_info();
            $bot_info = $this->telegram->get_last_result();
            if ($bot_info['ok'] && $bot_info['result']['is_bot'])
                $new_option['api_bot_username'] = $bot_info['result']['username'];
        }
        return $new_option;
    }

    function patterns_tags($tags)
    {
        $tags['WordPress'] = array(
            'title' => __('WordPress Tags', $this->plugin_key),
            'tags' => array(
                'ID' => __('The ID of this post', $this->plugin_key),
                'title' => __('The title of this post', $this->plugin_key),
                'slug' => __('The Slug of this post', $this->plugin_key),
                'excerpt' => __('The first 55 words of this post', $this->plugin_key),
                'content' => __('The whole content of this post', $this->plugin_key),
                'author' => __('The display name of author of this post', $this->plugin_key),
                'author-link' => __('The permalink of this author posts', $this->plugin_key),
                'link' => __('The permalink of this post', $this->plugin_key),
                'short-link' => __('The short url of this post', $this->plugin_key),
                'tags' => __('The tags of this post. Tags are automatically converted to Telegram hashtags', $this->plugin_key),
                'categories' => __('The categories of this post. Categories are automatically separated by | symbol', $this->plugin_key),
                'image' => __('The featured image URL', $this->plugin_key),
                'cf:' => __('The custom field of this post, Example {cf:price}', $this->plugin_key),
                'terms:' => __('The Taxonomy Terms of this post: {terms:taxonomy}, Example {terms:category}', $this->plugin_key),
                "if='cf:custom_field_name'}content{/if" => __("IF Statement for custom field, Example: {if='cf:price'}Price: {cf:price}{/if}", $this->plugin_key)
            )
        );
        return $tags;
    }

    function comment_notification($comment_ID, $comment_approved, $message_id = null)
    {
        global $wpdb;
        $comment = get_comment($comment_ID);
        if ($comment) {
            $comment_status = wp_get_comment_status($comment_ID);
            if ($message_id === null) {
                $users = get_users(array('role' => 'Administrator'));
                $user_ids = array();
                foreach ($users as $user) {
                    $user_ids[] = $user->ID;
                }
                $user_ids = implode(',', $user_ids);
                $users = $wpdb->get_results("SELECT user_id,wp_id FROM {$this->db_users_table} WHERE wp_id IN ({$user_ids})", ARRAY_A);
            } else {
                $users = false;
            }
            if ($users) {
                $text = "*" . __('New Comment', $this->plugin_key) . "*\n\n";
                $text .= __('Post') . ': ' . get_the_title($comment->comment_post_ID) . "\n";
                $text .= __('Author') . ': ' . $comment->comment_author . "\n";
                if (!empty($comment->comment_author_email))
                    $text .= __('Email') . ': ' . $comment->comment_author_email . "\n";
                if (!empty($comment->comment_author_url))
                    $text .= __('Website') . ': ' . $comment->comment_author_url . "\n";
                if (!empty($comment->comment_content))
                    $text .= __('Comment') . ":\n" . stripslashes(strip_tags($comment->comment_content)) . "\n";

                $keyboard_ = array(array(
                    array(
                        'text' => '🔗',
                        'url' => get_permalink($comment->comment_post_ID)
                    ),
                    array(
                        'text' => '💬',
                        'url' => admin_url('edit-comments.php')
                    )
                ));
                if ($message_id === null) {
                    foreach ($users as $user) {
                        if ($user['wp_id'] == $comment->user_id)
                            continue;
                        $keyboard = $keyboard_;
                        $this->telegram->sendMessage($text, null, $user['user_id'], 'Markdown');
                        $message_id = $this->telegram->get_last_result()['result']['message_id'];
                        $keyboard[0][] = array(
                            'text' => '🚮',
                            'callback_data' => 'comment_trash_' . $comment_ID . '_' . $message_id
                        );
                        if ($comment_approved)
                            $keyboard[0][] = array(
                                'text' => '💊',
                                'callback_data' => 'comment_hold_' . $comment_ID . '_' . $message_id
                            );
                        else
                            $keyboard[0][] = array(
                                'text' => '✔️',
                                'callback_data' => 'comment_approve_' . $comment_ID . '_' . $message_id
                            );

                        $keyboard[0][] = array(
                            'text' => '🛡️',
                            'callback_data' => 'comment_spam_' . $comment_ID . '_' . $message_id
                        );
                        $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                        $this->telegram->editMessageReplyMarkup($keyboards, $message_id, $user['user_id']);
                    }
                } else {
                    if ($comment_status === 'trash')
                        $keyboard_[0][] = array(
                            'text' => '🔄',
                            'callback_data' => 'comment_untrash_' . $comment_ID . '_' . $message_id
                        );
                    else
                        $keyboard_[0][] = array(
                            'text' => '🚮',
                            'callback_data' => 'comment_trash_' . $comment_ID . '_' . $message_id
                        );

                    if ($comment_status === 'approved')
                        $keyboard_[0][] = array(
                            'text' => '💊',
                            'callback_data' => 'comment_hold_' . $comment_ID . '_' . $message_id
                        );
                    else
                        $keyboard_[0][] = array(
                            'text' => '✔️',
                            'callback_data' => 'comment_approve_' . $comment_ID . '_' . $message_id
                        );

                    if ($comment_status !== 'spam')
                        $keyboard_[0][] = array(
                            'text' => '🛡️',
                            'callback_data' => 'comment_spam_' . $comment_ID . '_' . $message_id
                        );
                    $keyboards = $this->telegram->keyboard($keyboard_, 'inline_keyboard');
                    $this->telegram->editMessageReplyMarkup($keyboards, $message_id);
                }
            }
        }
    }

    function send_posts($posts)
    {
        if (!is_array($posts['parameter']['post_type']))
            $posts['parameter']['post_type'] = array($posts['parameter']['post_type']);

        $image_send_mode = apply_filters('wptelegrampro_image_send_mode', 'image_path');

        $posts_ = array();
        foreach ($posts['parameter']['post_type'] as $post_type)
            if (isset($posts[$post_type]))
                $posts_ = array_merge($posts_, $posts[$post_type]);

        if (count($posts_) > 0) {
            $this->words = apply_filters('wptelegrampro_words', $this->words);
            $i = 1;
            $current_page = $this->user['page'];
            //$this->telegram->sendMessage(serialize($posts));
            foreach ($posts_ as $post) {
                $keyboard = array(array(
                    array('text' => '🔗️ ' . $this->words['more'], 'url' => $post['link'])
                ));
                $text = $post['title'] . "\n" . $post['excerpt'] . "\n\n" . $post['short-link'];
                if ($posts['max_num_pages'] > 1 && $i == count($posts_)) {
                    $keyboard[1] = array();
                    if ($current_page > 1)
                        $keyboard[1][] = array('text' => $this->words['prev_page'], 'callback_data' => 'posts_page_prev');
                    if ($current_page < $posts['max_num_pages'])
                        $keyboard[1][] = array('text' => $this->words['next_page'], 'callback_data' => 'posts_page_next');
                    if (is_rtl())
                        $keyboard[1] = array_reverse($keyboard[1]);
                }
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                $this->telegram->disable_web_page_preview(true);
                if ($post[$image_send_mode] !== null)
                    $this->telegram->sendFile('sendPhoto', $post[$image_send_mode], $text, $keyboards);
                else
                    $this->telegram->sendMessage($text, $keyboards);
                $i++;
            }
        } else {
            $this->telegram->sendMessage(__('Your request without result!', $this->plugin_key));
        }
    }

    function check_keyboard_need_update()
    {
        $system_time = get_option('update_keyboard_time_wptp');
        if (!empty($system_time)) {
            $update_keyboard_time = $this->get_user_meta('update_keyboard_time');
            if (empty($update_keyboard_time) || $system_time > $update_keyboard_time) {
                $default_keyboard = apply_filters('wptelegrampro_default_keyboard', array());
                $default_keyboard = $this->telegram->keyboard($default_keyboard);
                $this->telegram->sendMessage(__('Update'), $default_keyboard);
                $this->update_user_meta('update_keyboard_time', time());
            }
        }
    }

    function keyboard($user_text)
    {
        $this->set_user();
        $this->words = $words = apply_filters('wptelegrampro_words', $this->words);
        $current_status = $this->user_field('status');

        if ($user_text == '/start' || strpos($user_text, '/start') !== false) {
            $message = $this->get_option('start_command');
            $message = empty(trim($message)) ? __('Welcome!', $this->plugin_key) : $message;
            $default_keyboard = apply_filters('wptelegrampro_default_keyboard', array());
            $default_keyboard = $this->telegram->keyboard($default_keyboard);
            $this->telegram->sendMessage($message, $default_keyboard);

        } else if ($user_text == '/search' || $user_text == $words['search']) {
            $this->telegram->sendMessage(__('Enter word for search:', $this->plugin_key));

        } elseif ($current_status == 'search') {
            $this->update_user_meta('search_query', $user_text);
            $this->update_user_meta('category_id', null);
            $this->update_user(array('page' => 1));
            $args = array(
                'post_type' => $this->get_option('search_post_type', array()),
                's' => $user_text,
                'per_page' => $this->get_option('posts_per_page', 1)
            );
            $posts = $this->query($args);
            $this->send_posts($posts);

        } else if ($user_text == '/posts' || $user_text == $words['posts']) {
            $this->update_user(array('page' => 1));
            $this->update_user_meta('category_id', null);
            $args = array(
                'post_type' => 'post',
                'per_page' => $this->get_option('posts_per_page', 1)
            );
            $posts = $this->query($args);
            $this->send_posts($posts);

        } elseif ($user_text == '/categories' || $user_text == $words['categories']) {
            $posts_category = $this->get_tax_keyboard('category', 'category', 'parent');
            $keyboard = $this->telegram->keyboard($posts_category, 'inline_keyboard');
            $this->telegram->sendMessage($words['categories'] . ":", $keyboard);
        }
    }

    function inline_keyboard($data)
    {
        $this->set_user();
        $button_data = $data['data'];

        if ($this->button_data_check($button_data, 'posts_page_')) {
            $current_page = intval($this->user['page']) == 0 ? 1 : intval($this->user['page']);
            if ($button_data == 'posts_page_next')
                $current_page++;
            else
                $current_page--;
            $this->update_user(array('page' => $current_page));
            $this->telegram->answerCallbackQuery(__('Page') . ': ' . $current_page);
            $args = array(
                'category_id' => $this->get_user_meta('category_id'),
                'post_type' => 'post',
                'per_page' => $this->get_option('posts_per_page', 1)
            );

            $search_query = $this->get_user_meta('search_query');
            if ($search_query != null) {
                $args['post_type'] = $this->get_option('search_post_type', array());
                $args['s'] = $search_query;
            }

            $products = $this->query($args);
            $this->send_posts($products);

        } elseif ($this->button_data_check($button_data, 'category')) {
            $this->update_user(array('page' => 1));
            $category_id = intval(end(explode('_', $button_data)));
            $this->update_user_meta('category_id', $category_id);
            $product_category = get_term($category_id, 'category');
            if ($product_category) {
                $this->telegram->answerCallbackQuery(__('Category') . ': ' . $product_category->name);
                $products = $this->query(array('category_id' => $category_id, 'per_page' => $this->get_option('posts_per_page', 1), 'post_type' => 'post'));
                $this->send_posts($products);
            } else {
                $this->telegram->answerCallbackQuery(__('Post Category Invalid!', $this->plugin_key));
            }

        } elseif ($this->button_data_check($button_data, 'comment')) {
            $button_data = explode('_', $button_data);
            $comment_ID = $button_data[2];
            $new_status = $button_data[1];
            $comment = get_comment($comment_ID);
            if ($comment) {
                $status_message = __('New Status:', $this->plugin_key) . ' ';
                if ($new_status == 'trash') {
                    wp_delete_comment($comment_ID);
                    $status_message .= __('Trash');
                } elseif ($new_status == 'untrash') {
                    wp_untrash_comment($comment_ID);
                    $status_message .= __('Restore from Trash', $this->plugin_key);
                } else {
                    if ($new_status == 'hold')
                        $status_message .= __('Unapprove', $this->plugin_key);
                    elseif ($new_status == 'approve')
                        $status_message .= __('Approve', $this->plugin_key);
                    elseif ($new_status == 'spam')
                        $status_message .= __('Spam');
                    wp_untrash_comment($comment_ID);
                    wp_set_comment_status($comment_ID, $new_status);
                }
                $this->telegram->answerCallbackQuery($status_message);
                $comment_status = wp_get_comment_status($comment_ID);
                $this->comment_notification($comment_ID, $comment_status == 'approved' ? 1 : 0, $button_data[3]);
            } else {
                $this->telegram->answerCallbackQuery(__('Not Found Comment!', $this->plugin_key));
            }
        }
    }

    function settings_tab($tabs)
    {
        $tabs[$this->tabID] = __('WordPress', $this->plugin_key);
        return $tabs;
    }

    function settings_content()
    {
        $this->options = get_option($this->plugin_key);
        $post_types = get_post_types(['public' => true, 'exclude_from_search' => false, 'show_ui' => true], "objects");
        ?>
        <div id="<?php echo $this->tabID ?>-content" class="wptp-tab-content">
            <table>
                <tr>
                    <td><label for="api_token"><?php _e('Telegram Bot API Token', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="password" name="api_token" id="api_token"
                               value="<?php echo $this->get_option('api_token') ?>"
                               class="regular-text ltr api-token">
                        <span class="dashicons dashicons-info bot-info-wptp"></span>
                        <input type="hidden" name="api_bot_username"
                               value="<?php echo $this->get_option('api_bot_username') ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="posts_per_page"><?php _e('Posts Per Page', $this->plugin_key) ?></label>
                    </td>
                    <td><input type="number" name="posts_per_page" id="posts_per_page"
                               value="<?php echo $this->get_option('posts_per_page', $this->per_page) ?>"
                               min="1" class="small-text ltr"></td>
                </tr>
                <tr>
                    <td>
                        <label for="image_size"><?php _e('Image Size', $this->plugin_key) ?></label>
                    </td>
                    <td><?php echo $this->image_size_select('image_size', $this->get_option('image_size'), '---') ?></td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e('Messages', $this->plugin_key) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="start_command"><?php _e('Start Command<br>(Welcome Message)', $this->plugin_key) ?></label>
                    </td>
                    <td>
                            <textarea name="start_command" id="start_command" cols="50" class="emoji"
                                      rows="4"><?php echo $this->get_option('start_command') ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e('Users', $this->plugin_key) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="telegram_connectivity"><?php _e('Telegram Connectivity', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="telegram_connectivity"
                                      name="telegram_connectivity" <?php checked($this->get_option('telegram_connectivity'), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e('Notification', $this->plugin_key) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="new_comment_notification"><?php _e('New Comment', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="new_comment_notification"
                                      name="new_comment_notification" <?php checked($this->get_option('new_comment_notification'), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e('Search', $this->plugin_key) ?></th>
                </tr>
                <tr>
                    <td>
                        <label><?php _e('Post Type', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <?php
                        $search_post_type = $this->get_option('search_post_type', array());
                        foreach ($post_types as $post_type) {
                            if (!in_array($post_type->name, $this->ignore_post_types))
                                echo '<label><input type="checkbox" name="search_post_type[]" value="' . $post_type->name . '" ' . checked(in_array($post_type->name, $search_post_type), true, false) . ' > ' . $post_type->label . '</label>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e('Telegram Keyboard', $this->plugin_key) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="force_update_keyboard"><?php _e('Force update keyboard', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" value="1" id="force_update_keyboard"
                                   name="force_update_keyboard"> <?php _e('Update') ?>
                        </label><br>
                        <span class="description">
                            <?php _e("You should update keyboard for users when change WordPress language, active Woocommerce plugin, search setting changed. (Status don't save)", $this->plugin_key) ?>
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
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new WordPressWPTP();
        return self::$instance;
    }
}

$WordPressWPTP = WordPressWPTP::getInstance();