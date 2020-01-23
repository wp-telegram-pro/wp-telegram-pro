<?php

namespace wptelegrampro;
if (!defined('ABSPATH')) exit;

class ChannelWPTP extends WPTelegramPro
{
    public static $instance = null;
    protected $tabID = 'channel-wptp-tab';
    public $excerpt_length = 100, $max_channel = 10;
    protected $post_types;

    public function __construct()
    {
        parent::__construct();
        add_action('wp_ajax_channel_members_count_wptp', [$this, 'channel_members_count']);
        add_action('wp_ajax_quick_send_channel_wptp', [$this, 'quick_send_channel']);
        add_filter('wptelegrampro_settings_tabs', [$this, 'settings_tab'], 20);
        add_action('wptelegrampro_settings_content', [$this, 'settings_content']);
        add_action('before_settings_updated_wptp', [$this, 'before_settings_updated'], 2);
        add_Shortcode('channel_members_wptp', [$this, 'channel_members_shortcode']);
        add_Shortcode('if_statement_wptp', [$this, 'if_statement_shortcode']);
        add_filter('wptelegrampro_channel_text', [$this, 'replace_channel_text'], 999999, 2);
        add_action('wptelegrampro_helps_content', [$this, 'helps_channel']);
        add_action('init', [$this, 'channel_init']);

        if ($this->get_option('send_to_channels') == 1) {
            add_action('init', [$this, 'schedule']);
            add_action('auto_channels_wptp', [$this, 'auto_update']);
            add_action('add_meta_boxes', [$this, 'register_meta_boxes'], 99999);
            add_action('save_post', [$this, 'meta_save'], 9999999995, 2);
        } else
            wp_clear_scheduled_hook('auto_channels_wptp');
    }

    function content_posts_column($column, $post_id)
    {
        if ('telegram-wptp' === $column) {
            $options = $this->options;
            $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
            foreach ($options['channel_username'] as $k => $v) {
                $channel_post_type = isset($options['channel_post_type'][$k]) && count($options['channel_post_type'][$k]) ? $options['channel_post_type'][$k] : [];
                $post_types = array_keys($channel_post_type);
                if (in_array($post_type, $post_types))
                    echo '<span class="dashicons-wptp-telegram quick-send-channel-wptp" title="' . __('Send to Channel', $this->plugin_key) . ': @' . $v . '" data-channel="' . $v . '" data-id="' . $post_id . '" data-index="' . $k . '"></span>';
            }
        }
    }

    function filter_posts_columns($columns)
    {
        $columns['telegram-wptp'] = __('Telegram', $this->plugin_key);
        return $columns;
    }

    function channel_init()
    {
        global $pagenow;
        $this->post_types = get_post_types(['public' => true, 'show_ui' => true], "objects");

        if (is_admin() && $pagenow == 'edit.php' && $this->check_metabox_display() && $this->get_option('quick_send_to_channel', false)) {
            $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
            $channel_post_type = $this->get_option('channel_post_type', []);
            $post_type_column = [];
            foreach ($channel_post_type as $k => $v) {
                if (count($v) > 0) {
                    $post_types = array_keys($v);
                    $post_type_column = array_merge($post_type_column, $post_types);
                }
            }
            $post_type_column = array_unique($post_type_column);
            if (count($post_type_column) && in_array($post_type, $post_type_column)) {
                add_filter("manage_{$post_type}_posts_columns", [$this, 'filter_posts_columns']);
                add_action("manage_{$post_type}_posts_custom_column", [$this, 'content_posts_column'], 10, 2);
            }
        }
    }

    function helps_channel()
    {
        ?>
        <div class="item">
            <button class="toggle" type="button"> <?php _e('Telegram Channel', $this->plugin_key) ?></button>
            <div class="panel">
                <div>
                    <strong>
                        <?php _e('Telegram channel is a great way for attracting people to your site.<br> This option allows you to send posts to your Telegram channel.', $this->plugin_key) ?>
                    </strong>
                    <ol>
                        <li><?php _e("Create a channel (if you don't already have one).", $this->plugin_key) ?></li>
                        <li><?php _e("Create a bot (if you don't already have one).", $this->plugin_key) ?></li>
                        <li><?php _e("Go to channel options and select 'Administrator' option.", $this->plugin_key) ?></li>
                        <li><?php _e("Select 'Add Administrator' option.", $this->plugin_key) ?></li>
                        <li><?php _e("Search the username of your bot and add it as administrator.", $this->plugin_key) ?></li>
                        <li><?php _e("Allow 'Post Messages' Permission", $this->plugin_key) ?></li>
                        <li><?php _e("Enter the username of the channel and hit SAVE button!!!", $this->plugin_key) ?></li>
                        <li><?php _e("Yes! Now, whenever you publish or update a post you can choose whether send it to Telegram (from post editor page)", $this->plugin_key) ?>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }

    function if_statement_shortcode($atts, $content = "")
    {
        $atts = shortcode_atts(array(
            'field' => '',
            'post_id' => false
        ), $atts);

        if (!$atts['post_id'] || empty($atts['field']))
            return '';

        $_field = explode(':', $atts['field']);
        if ($_field[0] == 'cf') {
            $value = get_post_meta($atts['post_id'], $_field[1], true);
            if (!$value) return '';
        }

        return $content;
    }

    function replace_channel_text($template, $post_id)
    {
        /**
         * @copyright Base on WP Telegram plugin, https://wordpress.org/plugins/wptelegram
         */
        if (preg_match_all('/(?<=\{)(terms|cf):([^\}]+?)(?=\})/iu', $template, $matches)) {
            foreach ($matches[0] as $field) {
                $_field = explode(':', $field);
                if ($_field[0] == 'cf') {
                    $value = get_post_meta($post_id, $_field[1], true);
                    $template = str_replace("{{$field}}", $value, $template);

                } elseif ($_field[0] == 'terms') {
                    $value = '';
                    if (taxonomy_exists($_field[1])) {
                        $terms = get_the_terms($post_id, $_field[1]);
                        $names = (is_wp_error($terms) || empty($terms)) ? array() : wp_list_pluck($terms, 'name');
                        if (!empty($names)) {
                            $delimiter = apply_filters('wptelegrampro_taxonomy_terms_delimiter', ' | ');
                            $value = implode($delimiter, $names);
                        }
                    }
                    $template = str_replace("{{$field}}", $value, $template);
                }
            }
        }

        /**
         * Check if statement
         */
        $template = preg_replace('/{if=\'(.*?)\'}(.*?){\/if}/', '[if_statement_wptp field="$1" post_id="' . $post_id . '"]$2[/if_statement_wptp]', $template);

        $template = do_shortcode($template);

        return $template;
    }

    function quick_send_channel()
    {
        if (!$this->check_metabox_display() || !$this->get_option('quick_send_to_channel', false)) return;
        $channel_username = $_POST['channel_username'];
        $channel_index = intval($_POST['channel_index']);
        $post_id = intval($_POST['post_id']);
        $result = $this->send_to_channel($post_id, $channel_username, $channel_index);
        $result = array('success' => isset($result['ok']) && $result['ok']);
        wp_send_json($result);
    }

    function auto_update($post_id = null)
    {
        $options = $this->options;
        if ($post_id !== null) {
            $post_type = get_post_type($post_id);
            $args = array(
                'p' => $post_id,
                'post_status' => 'publish',
                'post_type' => $post_type,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'compare' => '!=',
                        'value' => '',
                        'key' => '_send_to_channel_' . $post_type . '_wptp',
                    ),
                    array(
                        'key' => '_retry_posted_' . $post_type . '_wptp',
                        'compare' => 'NOT EXISTS',
                    )
                )
            );
        } else {
            foreach ($options['channel_username'] as $k => $v) {
                if (empty($v) || !isset($options['channel_post_type'][$k]) || isset($options['channel_post_type'][$k]) && is_array($options['channel_post_type'][$k]) && count($options['channel_post_type'][$k]) == 0)
                    continue;
                if (isset($options['channel_post_type'][$k]) && count($options['channel_post_type'][$k]) > 0) {
                    $post_types = array_keys($options['channel_post_type'][$k]);
                    $delay = intval($this->get_option('channels_delay_send', 0));

                    $args = array(
                        'posts_per_page' => 1,
                        'post_status' => 'publish',
                        'post_type' => $post_types,
                        'orderby' => 'modified',
                        'meta_query' => array(
                            'relation' => 'AND',
                            array(
                                'compare' => '=',
                                'value' => '1',
                                'key' => '_send_to_channel_' . $options['channel_username'][$k] . '_wptp',
                            ),
                            array(
                                'key' => '_retry_posted_' . $options['channel_username'][$k] . '_wptp',
                                'compare' => 'NOT EXISTS',
                            )
                        )
                    );
                    if ($delay > 0) {
                        $args['date_query'] = array(
                            array(
                                'column' => 'post_modified',
                                'before' => "{$delay} minutes ago"
                            )
                        );
                    }
                    $query = new \WP_Query($args);
                    if ($query->have_posts()) {
                        $query->the_post();
                        $post_id = get_the_ID();
                        $this->send_to_channel($post_id, $options['channel_username'][$k], $k);
                    }
                }
            }
        }
    }

    function send_to_channel($post_id, $channel, $index = 0)
    {
        $image_send_mode = apply_filters('wptelegrampro_image_send_mode', 'image_path');
        $keyboards = null;
        $options = $this->options;
        $result = false;

        if ($options['channel_username'][$index] == $channel) {
            $post = $this->query(array('p' => $post_id, 'post_type' => get_post_type($post_id)));

            $text = get_post_meta($post_id, '_channel_message_pattern_' . $options['channel_username'][$index] . '_wptp', true);
            if (empty($text))
                $text = $options['channel_message_pattern'][$index];

            $text = stripslashes($text);

            if (isset($options['channel_inline_button_title'][$index]) && !empty($options['channel_inline_button_title'][$index])) {
                $keyboard = array(array(
                    array('text' => $options['channel_inline_button_title'][$index], 'url' => $post['short-link'])
                ));
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
            }

            if ($post['image'] !== null) {
                $featured_image = get_post_meta($post_id, '_featured_image_' . $options['channel_username'][$index] . '_wptp', true);
                if (empty($featured_image))
                    $featured_image = isset($options['channel_with_featured_image'][$index]);
            } else {
                $featured_image = false;
            }

            $excerpt_length = isset($options['channel_excerpt_length'][$index]) && !empty($options['channel_excerpt_length'][$index]) ? $options['channel_excerpt_length'][$index] : $this->excerpt_length;
            add_filter('excerpt_length', function () use ($excerpt_length) {
                return $excerpt_length;
            });

            $disable_web_page_preview = isset($options['channel_disable_web_page_preview'][$index]);
            //$image_position = $options['channel_image_position'][$index];
            $formatting_messages = $options['channel_formatting_messages'][$index];
            $formatting_messages = $formatting_messages == 'simple' ? null : $formatting_messages;

            $post['content'] = $formatting_messages != 'HTML' ? strip_tags($post['content']) : $post['content'];

            if (isset($post['tags']) && is_array($post['tags']))
                if (count($post['tags']) > 0)
                    $post['tags'] = '#' . implode(' #', str_replace(' ', '_', array_keys($post['tags'])));
                else
                    $post['tags'] = '';

            if (isset($post['categories']) && is_array($post['categories']))
                if (count($post['categories']) > 0)
                    $post['categories'] = implode(' | ', array_keys($post['categories']));
                else
                    $post['categories'] = '';

            $this->patterns_tags = apply_filters('wptelegrampro_patterns_tags', $this->patterns_tags);
            foreach ($this->patterns_tags as $group => $group_item) {
                if (isset($group_item['plugin']) && !$this->check_plugin_active($group_item['plugin']))
                    continue;
                $tags = array_keys($group_item['tags']);
                foreach ($tags as $tag) {
                    $replace = $post[$tag];
                    if (isset($post[$tag]))
                        $replace = gettype($replace) === 'boolean' ? ($replace ? $this->words['yes'] : $this->words['no']) : $replace;
                    else
                        $replace = '';
                    $text = str_replace('{' . $tag . '}', $replace, $text);
                }
            }

            $text = apply_filters('wptelegrampro_channel_text', $text, $post_id);

            $this->telegram->disable_web_page_preview($disable_web_page_preview);
            if ($featured_image && $post[$image_send_mode] !== null)
                $this->telegram->sendFile('sendPhoto', $post[$image_send_mode], $text, $keyboards, '@' . $channel, $formatting_messages);
            else
                $this->telegram->sendMessage($text, $keyboards, '@' . $channel, $formatting_messages);

            $result = $this->telegram->get_last_result();
            if (isset($result['ok']) && $result['ok']) {
                update_post_meta($post_id, '_posted_status_' . $channel . '_wptp', 1);
                update_post_meta($post_id, '_retry_posted_' . $channel . '_wptp', 1);
                update_post_meta($post_id, '_send_to_channel_' . $channel . '_wptp', 2);
            }
        }

        return $result;
    }

    function schedule()
    {
        if (isset($this->options['send_to_channels']) && !wp_next_scheduled('auto_channels_wptp'))
            wp_schedule_event(current_time('U'), 'every_' . (intval($this->options['channels_cron_interval']) != 0 ? $this->options['channels_cron_interval'] : 1) . '_minutes', 'auto_channels_wptp');
    }

    function meta_save($post_id, $post)
    {
        $display_metabox = $this->check_metabox_display();

        if ($display_metabox) {
            if (!isset($_POST['wptp-nonce']))
                return $post_id;
            if (!isset($_POST["wptp-nonce"]) || !wp_verify_nonce($_POST["wptp-nonce"], basename(__FILE__)))
                return $post_id;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return $post_id;
        if (false !== wp_is_post_revision($post_id))
            return $post_id;
        if (isset($_POST['post_type']) && 'page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id))
                return $post_id;
        } else if (!current_user_can('edit_post', $post_id))
            return $post_id;
        if (!in_array(get_post_status($post_id), array('publish', 'draft')))
            return $post_id;
        if (strpos($post->post_name, 'autosave') !== false)
            return $post_id;
        if (strpos($post->post_name, 'revision') !== false)
            return $post_id;

        $options = $this->options;
        $post_type = $post->post_type;

        foreach ($options['channel_username'] as $k => $v) {
            if (empty($v) || !isset($options['channel_post_type'][$k]) || isset($options['channel_post_type'][$k]) && is_array($options['channel_post_type'][$k]) && !in_array($post_type, array_keys($options['channel_post_type'][$k])))
                continue;

            if ($display_metabox) {
                $posted_status = get_post_meta($post_id, '_retry_posted_' . $options['channel_username'][$k] . '_wptp', true);
                if ($posted_status == 1 && $_POST['send_to_channel'][$options['channel_username'][$k]] == 1)
                    delete_post_meta($post_id, '_retry_posted_' . $options['channel_username'][$k] . '_wptp');

                if (!empty($_POST['channel_message_pattern'][$options['channel_username'][$k]]) && stripslashes($options['channel_message_pattern'][$k]) != $_POST['channel_message_pattern'][$options['channel_username'][$k]])
                    update_post_meta($post_id, '_channel_message_pattern_' . $options['channel_username'][$k] . '_wptp', $_POST['channel_message_pattern'][$options['channel_username'][$k]]);
                elseif (empty($_POST['channel_message_pattern'][$options['channel_username'][$k]]))
                    delete_post_meta($post_id, '_channel_message_pattern_' . $options['channel_username'][$k] . '_wptp');

                $send_to_channel = $_POST['send_to_channel'][$options['channel_username'][$k]];
                $featured_image = $_POST['channel_with_featured_image'][$options['channel_username'][$k]];
            } else {
                $send_to_channel = isset($options['send_to_channel'][$k]) ? 1 : 2;
                $featured_image = isset($options['channel_with_featured_image'][$k]) ? 1 : 2;
            }

            update_post_meta($post_id, '_send_to_channel_' . $options['channel_username'][$k] . '_wptp', $send_to_channel);
            update_post_meta($post_id, '_featured_image_' . $options['channel_username'][$k] . '_wptp', $featured_image);
        }
        return $post_id;
    }

    function register_meta_boxes($post_type)
    {
        $options = $this->options;
        if (isset($options['channel_username'])) {
            if (!$this->check_metabox_display()) return;

            $post_types = array();
            foreach ($options['channel_username'] as $k => $v) {
                if (!empty($v) && isset($options['channel_post_type'][$k]) && is_array($options['channel_post_type'][$k]))
                    $post_types = array_merge($post_types, array_keys($options['channel_post_type'][$k]));
            }
            if (in_array($post_type, $post_types))
                add_meta_box('WPTPMetaBox', $this->plugin_name, array($this, 'post_display'), $post_type);
        }
    }

    function post_display()
    {
        $options = $this->options;
        if (!isset($options['channel_username']) || !is_array($options['channel_username']) || count($options['channel_username']) == 0)
            return;
        $post_id = get_the_ID();
        wp_nonce_field(basename(__FILE__), "wptp-nonce");
        ?>
        <div class="wrap wptp-metabox channel-list-wptp accordion-wptp">
            <?php
            $current_channel = array();
            foreach ($options['channel_username'] as $k => $v) {
                if (!is_array($options['channel_post_type'][$k]))
                    continue;

                $post_types = array_keys($options['channel_post_type'][$k]);
                if (empty($v) || in_array($options['channel_username'][$k], $current_channel) || !isset($options['channel_post_type'][$k]) || isset($options['channel_post_type'][$k]) && is_array($options['channel_post_type'][$k]) && !in_array(get_post_type($post_id), $post_types))
                    continue;
                $current_channel[] = $options['channel_username'][$k];
                $send_to_channel = get_post_meta($post_id, '_send_to_channel_' . $options['channel_username'][$k] . '_wptp', true);

                if (empty($send_to_channel))
                    $send_to_channel = isset($options['send_to_channel'][$k]) ? 1 : 2;

                $channel_message_pattern = get_post_meta($post_id, '_channel_message_pattern_' . $options['channel_username'][$k] . '_wptp', true);
                if (empty($channel_message_pattern))
                    $channel_message_pattern = $options['channel_message_pattern'][$k];
                $channel_message_pattern = stripslashes($channel_message_pattern);

                $featured_image = get_post_meta($post_id, '_featured_image_' . $options['channel_username'][$k] . '_wptp', true);
                if (empty($featured_image))
                    $featured_image = isset($options['channel_with_featured_image'][$k]) ? 1 : 2;

                $posted_status = get_post_meta($post_id, '_posted_status_' . $options['channel_username'][$k] . '_wptp', true);
                $posted = '';

                if ($send_to_channel == 1)
                    $send_to_channel_status = '<span class="dashicons dashicons-yes"></span>';
                else
                    $send_to_channel_status = '<span class="dashicons dashicons-no-alt"></span>';

                if (!empty($posted_status) && $posted_status == 1)
                    $posted = '<span class="dashicons dashicons-megaphone posted-to-channel"></span>';

                ?>
                <div class="item">
                    <button class="toggle" type="button">
                        <?php echo $send_to_channel_status . $posted . ' @' . $options['channel_username'][$k] ?></button>
                    <div class="panel">
                        <table>
                            <tr>
                                <td>
                                    <?php _e('Send to Channel', $this->plugin_key) ?>
                                </td>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="radio" class="send-to-channel send-to-channel-yes"
                                                   value="1" <?php checked(isset($send_to_channel) ? $send_to_channel : '', 1) ?>
                                                   name="send_to_channel[<?php echo $options['channel_username'][$k] ?>]"> <?php _e('Yes', $this->plugin_key) ?>
                                        </label>
                                        <label>
                                            <input type="radio" class="send-to-channel send-to-channel-no"
                                                   value="2" <?php checked(isset($send_to_channel) ? $send_to_channel : '', 2) ?>
                                                   name="send_to_channel[<?php echo $options['channel_username'][$k] ?>]"> <?php _e('No', $this->plugin_key) ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php _e('Message Pattern', $this->plugin_key) ?>
                                </td>
                                <td>
                                    <?php $this->select_tags() ?>
                                    <br>
                                    <textarea
                                            name="channel_message_pattern[<?php echo $options['channel_username'][$k] ?>]"
                                            cols="50" class="message-pattern-wptp emoji"
                                            rows="4"><?php echo $channel_message_pattern ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php _e('Attache featured Image', $this->plugin_key) ?>
                                </td>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="radio"
                                                   value="1" <?php checked(isset($featured_image) ? $featured_image : '', 1) ?>
                                                   name="channel_with_featured_image[<?php echo $options['channel_username'][$k] ?>]"> <?php _e('Yes', $this->plugin_key) ?>
                                        </label>
                                        <label>
                                            <input type="radio"
                                                   value="2" <?php checked(isset($featured_image) ? $featured_image : '', 2) ?>
                                                   name="channel_with_featured_image[<?php echo $options['channel_username'][$k] ?>]"> <?php _e('No', $this->plugin_key) ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    function channel_members_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'channel' => '',
            'formatting' => 1
        ), $atts);

        if (!$atts['channel'] || empty($atts['channel']))
            return __("[Set 'channel' attribute]", $this->plugin_key);

        $transient_key = 'channel_members_' . $atts['channel'] . '_wptp';

        if (WP_DEBUG || false === ($count = get_transient($transient_key))) {
            $channel = $this->telegram->get_members_count('@' . $atts['channel']);
            $channel_member = $this->telegram->get_last_result();

            if ($channel && $channel_member['ok'] && isset($channel_member['result'])) {
                $count = $atts['formatting'] == 1 ? number_format($channel_member['result']) : $channel_member['result'];
                set_transient($transient_key, $count, 60 * 60);
                return $count;
            } else {
                return __('[API Token or Channel Username Invalid]', $this->plugin_key);
            }
        } else {
            return get_transient($transient_key);
        }
    }

    function channel_members_count()
    {
        $channel_username = $_POST['channel_username'];
        $channel = $this->telegram->get_members_count('@' . $channel_username);
        $channel_member = $this->telegram->get_last_result();
        if ($channel && $channel_member['ok'] && isset($channel_member['result'])) {
            echo __('Channel Member Count:', $this->plugin_key) . ' ' . number_format($channel_member['result']);
        } else {
            _e('API Token or Channel Username Invalid, It may not have access to the channel! (Read Help)', $this->plugin_key);
        }
        exit;
    }

    function before_settings_updated()
    {
        if (!isset($_POST['send_to_channels']) || $_POST['channels_cron_interval'] != $this->options['channels_cron_interval']) {
            wp_clear_scheduled_hook('auto_channels_wptp');
        }
    }

    function settings_tab($tabs)
    {
        $tabs[$this->tabID] = __('Channel', $this->plugin_key);
        return $tabs;
    }

    function settings_content()
    {
        $this->options = get_option($this->plugin_key);

        ?>
        <div id="<?php echo $this->tabID ?>-content" class="wptp-tab-content hidden">
            <table>
                <tr>
                    <td>
                        <label for="send_to_channels"><?php _e('Send to Channels', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <label><input type="checkbox" id="send_to_channels" class="send_to_channels"
                                      value="1" <?php checked($this->get_option('send_to_channels'), 1) ?>
                                      name="send_to_channels"> <?php _e('Active', $this->plugin_key) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="channels_cron_interval"><?php _e('Check send every', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="number" name="channels_cron_interval" id="channels_cron_interval"
                               value="<?php echo $this->get_option('channels_cron_interval', 1) ?>" placeholder="1"
                               min="1" max="60"
                               class="small excerpt_length ltr"> <?php _e('Minutes', $this->plugin_key) ?><span
                                class="description">,
                            <?php printf(__('Work with <a href="%s" target="_blank">WP-Cron</a>.', $this->plugin_key), "https://code.tutsplus.com/articles/insights-into-wp-cron-an-introduction-to-scheduling-tasks-in-wordpress--wp-23119") ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="channels_delay_send"><?php _e('Delay in send', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="number" name="channels_delay_send" id="channels_delay_send"
                               value="<?php echo $this->get_option('channels_delay_send', 5) ?>" placeholder="0" min="0"
                               class="small excerpt_length ltr"> <?php _e('Minutes', $this->plugin_key) ?><span
                                class="description">, <?php _e('Least 5 minutes recommended.', $this->plugin_key) ?></span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label><?php _e('Display Metabox for', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <?php
                        $user_roles = $this->wp_user_roles();
                        $channels_user_roles = $this->get_option('channels_metabox_user_roles', ['administrator' => true]);
                        foreach ($user_roles as $role => $name)
                            echo '<label><input type="checkbox" name="channels_metabox_user_roles[' . $role . ']" value="1" ' . checked(isset($channels_user_roles[$role]), true, false) . '>' . $name . '</label>'
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="quick_send_to_channel"><?php _e('Enable Quick Send', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" id="quick_send_to_channel"
                                   value="1" <?php checked($this->get_option('quick_send_to_channel'), 1) ?>
                                   name="quick_send_to_channel"> <?php _e('Active', $this->plugin_key) ?>
                        </label><br>
                        <span class="description"><?php _e('Display Telegram column in admin post type archive (Requires the metabox display access).', $this->plugin_key) ?></span>
                    </td>
                </tr>
            </table>
            <br>
            <div class="channel-list-wptp accordion-wptp">
                <?php
                $options = $this->options;
                $c = 0;
                foreach ($options['channel_username'] as $k => $v) {
                    if (empty($v))
                        continue;
                    if ($c >= $this->max_channel)
                        break;
                    $item = array(
                        'index' => $c,
                        'channel_username' => $options['channel_username'][$k],
                        'channel_post_type' => isset($options['channel_post_type'][$k]) && is_array($options['channel_post_type'][$k]) ? $options['channel_post_type'][$k] : array(),
                        'send_to_channel' => isset($options['send_to_channel'][$k]) ? $options['send_to_channel'][$k] : '',
                        'message_pattern' => $options['channel_message_pattern'][$k],
                        'with_featured_image' => isset($options['channel_with_featured_image'][$k]) ? $options['channel_with_featured_image'][$k] : '',
                        'formatting_messages' => $options['channel_formatting_messages'][$k],
                        'excerpt_length' => $options['channel_excerpt_length'][$k],
                        'inline_button_title' => $options['channel_inline_button_title'][$k],
                        'disable_web_page_preview' => isset($options['channel_disable_web_page_preview'][$k]) ? $options['channel_disable_web_page_preview'][$k] : '',
                        //'image_position' => $options['channel_image_position'][$k]
                    );
                    $this->item($item);
                    $c++;
                }
                if ($c == 0) {
                    $item = array(
                        'index' => 0,
                        'channel_username' => '',
                        'channel_post_type' => array(),
                        'send_to_channel' => 1,
                        'message_pattern' => '',
                        'with_featured_image' => 1,
                        'formatting_messages' => 'simple',
                        'excerpt_length' => '',
                        'disable_web_page_preview' => 0,
                        //'channel_image_position' => 'before_text',
                    );
                    $this->item($item);
                }
                ?>
                <button type="button" class="add-channel" <?php echo $c >= $this->max_channel ? 'disabled' : '' ?>><span
                            class="dashicons dashicons-plus"></span> <?php _e('Add Channel', $this->plugin_key) ?>
                </button>
            </div>
        </div>
        <?php
    }

    private function item($item)
    {
        ?>
        <div class="item" data-index="<?php echo $item['index'] ?>">
            <button class="toggle"
                    type="button"><?php echo isset($item['channel_username']) && !empty($item['channel_username']) ? '@' . $item['channel_username'] : __('New Channel', $this->plugin_key); ?></button>
            <div class="panel">
                <table>
                    <tr>
                        <td>
                            <?php _e('Channel Username', $this->plugin_key) ?>
                        </td>
                        <td>
                            @<input type="text" name="channel_username[<?php echo $item['index'] ?>]"
                                    value="<?php echo isset($item['channel_username']) ? $item['channel_username'] : '' ?>"
                                    class="channel-username-wptp ltr">
                            <span class="dashicons dashicons-info channel-info-wptp" <?php echo !isset($item['channel_username']) || empty($item['channel_username']) ? 'style="display: none"' : '' ?>></span>
                            <span class="dashicons dashicons-trash remove-channel-wptp"></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Send to channel (Default)', $this->plugin_key) ?>
                        </td>
                        <td>
                            <label><input type="checkbox" class="send_to_channel"
                                          value="1" <?php checked(isset($item['send_to_channel']) ? $item['send_to_channel'] : '', 1) ?>
                                          name="send_to_channel[<?php echo $item['index'] ?>]"> <?php _e('Active', $this->plugin_key) ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e("Active on PostType", $this->plugin_key) ?></td>
                        <td>
                            <?php
                            foreach ($this->post_types as $post_type)
                                if (!in_array($post_type->name, $this->ignore_post_types))
                                    echo '<label><input type="checkbox" name="channel_post_type[' . $item['index'] . '][' . $post_type->name . ']" value="1" ' . checked(isset($item['channel_post_type'][$post_type->name]) ? $item['channel_post_type'][$post_type->name] : 0, 1, false) . ' class="channel_post_type"/>' . $post_type->label . '</label> &nbsp;';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Message Pattern', $this->plugin_key) ?>
                        </td>
                        <td>
                            <?php $this->select_tags() ?>
                            <br>
                            <textarea name="channel_message_pattern[<?php echo $item['index'] ?>]" cols="50"
                                      class="message-pattern-wptp emoji"
                                      rows="4"><?php echo isset($item['message_pattern']) ? stripslashes($item['message_pattern']) : '' ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('With featured image?', $this->plugin_key) ?>
                        </td>
                        <td>
                            <label><input type="checkbox" class="with_featured_image"
                                          value="1" <?php checked(isset($item['with_featured_image']) ? $item['with_featured_image'] : '', 1) ?>
                                          name="channel_with_featured_image[<?php echo $item['index'] ?>]"> <?php _e('Yes', $this->plugin_key) ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Formatting messages', $this->plugin_key) ?>
                        </td>
                        <td>
                            <span class="description"><?php _e('Telegram supports basic markdown and some HTML tags (bold, italic, inline links). By checking one of the following options, your messages will be compatible with Telegram format.', $this->plugin_key) ?>
                                (<a href="https://core.telegram.org/bots/api#formatting-options"
                                    target="_blank"><?php _e('Telegram Bot API', $this->plugin_key) ?></a>)</span>
                            <br>
                            <select name="channel_formatting_messages[<?php echo $item['index'] ?>]"
                                    class="formatting_messages">
                                <option value="simple" <?php selected(isset($item['formatting_messages']) ? $item['formatting_messages'] : '', 'simple') ?>><?php _e('Simple', $this->plugin_key) ?></option>
                                <option value="Markdown" <?php selected(isset($item['formatting_messages']) ? $item['formatting_messages'] : '', 'Markdown') ?>><?php _e('Markdown', $this->plugin_key) ?></option>
                                <option value="HTML" <?php selected(isset($item['formatting_messages']) ? $item['formatting_messages'] : '', 'HTML') ?>><?php _e('HTML', $this->plugin_key) ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Excerpt length', $this->plugin_key) ?>
                        </td>
                        <td>
                            <input type="number" name="channel_excerpt_length[<?php echo $item['index'] ?>]"
                                   value="<?php echo isset($item['excerpt_length']) ? $item['excerpt_length'] : '' ?>"
                                   placeholder="<?php echo $this->excerpt_length ?>"
                                   class="excerpt_length ltr"> <?php _e('Word', $this->plugin_key) ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Inline button title', $this->plugin_key) ?>
                        </td>
                        <td>
                            <input type="text" name="channel_inline_button_title[<?php echo $item['index'] ?>]"
                                   value="<?php echo isset($item['inline_button_title']) ? $item['inline_button_title'] : '' ?>"
                                   class="inline_button_title regular-text">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Web page preview in messages', $this->plugin_key) ?>
                        </td>
                        <td>
                            <label><input type="checkbox" class="disable_web_page_preview"
                                          value="1" <?php checked(isset($item['disable_web_page_preview']) ? $item['disable_web_page_preview'] : '', 1) ?>
                                          name="channel_disable_web_page_preview[<?php echo $item['index'] ?>]"> <?php _e('Disable', $this->plugin_key) ?>
                            </label>
                        </td>
                    </tr>
                    <!--<tr>
                        <td>
                            <?php /*_e('Image Position', $this->plugin_key) */ ?>
                        </td>
                        <td>
                            <span class="description"><?php /*_e('Telegram limits the photo caption to 200 characters. Here are two options if your message text exceeds this limit:', $this->plugin_key) */ ?></span><br>
                            <select name="channel_image_position[<?php /*echo $item['index'] */ ?>]"
                                    class="image_position">
                                <option value="before_text" <?php /*selected(isset($item['image_position']) ? $item['image_position'] : '', 'before_text') */ ?>><?php /*_e('Send image before text', $this->plugin_key) */ ?></option>
                                <option value="after_text" <?php /*selected(isset($item['image_position']) ? $item['image_position'] : '', 'after_text') */ ?>><?php /*_e('Send image after text', $this->plugin_key) */ ?></option>
                            </select>
                            <br>
                            <strong><?php /*_e('Send image before text', $this->plugin_key) */ ?>:</strong>
                            <span class="description"><?php /*_e('This will send the photo with the pattern content as the caption. If pattern content exceeds 200 characters limit, then this will send the photo with the post title', $this->plugin_key) */ ?></span>
                            <br>
                            <strong><?php /*_e('Send image after text', $this->plugin_key) */ ?>:</strong>
                            <span class="description"><?php /*_e('This will attach an invisible link of your photo to the beginning of your message. People wouldn\'t see the link, but Telegram clients will show the photo at the bottom of the message (All in one message).', $this->plugin_key) */ ?></span>
                        </td>
                    </tr>-->
                </table>
            </div>
        </div>
        <?php
    }

    private function select_tags()
    {
        $this->patterns_tags = apply_filters('wptelegrampro_patterns_tags', $this->patterns_tags);
        $select = '<select class="patterns-select-wptp">';
        $select .= '<option style="display:none;" selected> ' . __('- Select a Tag -', $this->plugin_key) . ' </option>';
        foreach ($this->patterns_tags as $group => $group_item) {
            if (isset($group_item['plugin']) && !$this->check_plugin_active($group_item['plugin']))
                continue;
            $select .= '<optgroup label="' . $group_item['title'] . '">';
            $tags = array_keys($group_item['tags']);
            foreach ($tags as $tag)
                $select .= '<option value="{' . $tag . '}" title="' . $group_item['tags'][$tag] . '">{' . $tag . '} - ' . $group_item['tags'][$tag] . '</option>';
            $select .= '</optgroup>';
        }
        $select .= '</select>';
        echo $select;
    }

    function check_metabox_display()
    {
        if (
            !isset($this->options['channels_metabox_user_roles'], $this->options['channel_username']) ||
            !is_array($this->options['channels_metabox_user_roles']) ||
            count($this->options['channels_metabox_user_roles']) == 0 ||
            !is_array($this->options['channel_username']) ||
            count($this->options['channel_username']) == 0
        )
            return false;

        $user_role = $this->get_user_role();
        $channels_user_roles = array_keys($this->options['channels_metabox_user_roles']);
        return in_array($user_role, $channels_user_roles);
    }

    /**
     * Returns an instance of class
     * @return  ChannelWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new ChannelWPTP();
        return self::$instance;
    }
}

$ChannelWPTP = ChannelWPTP::getInstance();
