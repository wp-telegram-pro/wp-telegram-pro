<?php
/**
 * Plugin Name: WP Telegram Pro
 * Plugin URI: https://github.com/parsakafi/wp-telegram-pro
 * Description: Integrate WordPress with Telegram
 * Author: Parsa Kafi
 * Version: 1.1
 * Author URI: http://parsa.ws
 * Text Domain: wp-telegram-pro
 */

if (!defined('ABSPATH')) exit;

// Define
if (!function_exists('get_plugin_data'))
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
/**
 * Define version.
 */
$plugin = get_plugin_data(__FILE__, false, false);
$version = $plugin['Version'];
define('WPTELEGRAMPRO_VERSION', $version);
define('WPTELEGRAMPRO_BASENAME', plugin_basename(__FILE__));
define('WPTELEGRAMPRO_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
define('WPTELEGRAMPRO_URL', untrailingslashit(plugins_url('', __FILE__)));
define('WPTELEGRAMPRO_INC_DIR', WPTELEGRAMPRO_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR);

// https://pippinsplugins.com/storing-session-data-in-wordpress-without-_session/
require_once WPTELEGRAMPRO_INC_DIR . 'TelegramWPTP.php';
require_once WPTELEGRAMPRO_INC_DIR . 'WordPressWPTP.php';
require_once WPTELEGRAMPRO_INC_DIR . 'WoocommerceWPTP.php';
require_once WPTELEGRAMPRO_INC_DIR . 'ChannelWPTP.php';

class WPTelegramPro
{
    public static $instance = null;
    public $plugin_key = 'wp-telegram-pro', $per_page = 1, $patterns_tags = array(),
        $rand_id_length = 10, $now, $db_table, $words = array(), $options, $telegram,
        $telegram_input, $user, $default_keyboard, $plugin_name;
    protected $helpTabID = 'help-wptp-tab', $aboutTabID = 'about-wptp-tab';
    
    public function __construct($bypass = false)
    {
        global $wpdb;
        
        load_plugin_textdomain($this->plugin_key, false, basename(dirname(__FILE__)) . '/languages');
        
        $this->options = get_option($this->plugin_key);
        $this->telegram = new TelegramWPTP($this->get_option('api_token'));
        $this->db_table = $wpdb->prefix . 'wptelegrampro_users';
        $this->plugin_name = __('WP Telegram Pro', $this->plugin_key);
        $this->now = date("Y-m-d H:i:s");
        $this->init($bypass);
        $this->words = apply_filters('wptelegrampro_words', $this->words);
        $this->patterns_tags = apply_filters('wptelegrampro_patterns_tags', $this->patterns_tags);
        
        add_filter('wptelegrampro_words', [$this, 'words']);
        
        if (!$bypass) {
            add_action('wptelegrampro_keyboard_response', array($this, 'change_user_status'), 1);
            add_filter('wptelegrampro_after_settings_update_message', array($this, 'after_settings_updated_message'), 10);
            add_action('wp_login', array($this, 'check_user_id'));
            add_action('user_register', array($this, 'check_user_id'));
            add_action('admin_menu', array($this, 'menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_ajax_bot_info_wptp', array($this, 'get_bot_info'));
            add_filter('cron_schedules', array($this, 'add_every_minutes'));
            
            add_filter('wptelegrampro_settings_tabs', [$this, 'settings_tab'], 100);
            add_action('wptelegrampro_settings_content', [$this, 'help_settings_content']);
            add_action('wptelegrampro_settings_content', [$this, 'about_settings_content']);
        }
    }
    
    function words($words)
    {
        $new_words = array(
            'yes' => __('Yes', $this->plugin_key),
            'no' => __('No', $this->plugin_key),
            'next' => __('Next >', $this->plugin_key),
            'prev' => __('< Previous', $this->plugin_key),
            'next_page' => __('Next Page >', $this->plugin_key),
            'prev_page' => __('< Previous Page', $this->plugin_key),
            'back' => __('Back', $this->plugin_key),
            'posts' => __('Posts', $this->plugin_key),
            'search' => __('Search', $this->plugin_key),
            'categories' => __('Categories', $this->plugin_key),
            'detail' => __('Detail', $this->plugin_key),
            'more' => __('More', $this->plugin_key),
        );
        $words = array_merge($words, $new_words);
        
        return $words;
    }
    
    function init($bypass = false)
    {
        if (isset($_GET['wptp']) && $_GET['wptp'] == get_option('wptp-rand-url')) {
            try {
                $this->telegram_input = $this->telegram->input();
                $this->set_user();
                if (!$bypass)
                    add_action('init', array($this, 'get_init'));
            } catch (Exception $e) {
                // Exception
            }
        }
    }
    
    function settings_tab($tabs)
    {
        $tabs[$this->helpTabID] = __('Help', $this->plugin_key);
        $tabs[$this->aboutTabID] = __('About', $this->plugin_key);
        return $tabs;
    }
    
    function help_settings_content()
    {
        $commands = apply_filters('wptelegrampro_default_commands', array());
        ?>
        <div id="<?php echo $this->helpTabID ?>-content" class="wptp-tab-content hidden">
            <table>
                <tr>
                    <th>
                        <?php _e('Default command list:', $this->plugin_key) ?>
                    </th>
                    <td>
                        <pre class="ltr"><?php
                            $list = array();
                            foreach ($commands as $command => $desc)
                                $list[] = '/' . $command . ': ' . $desc;
                            echo implode('<br>', $list);
                            ?>
                        </pre>

                        <span class="description">
                            <?php
                            echo sprintf(__('How to set bot commands: Start %s and select your bot > "Edit Bot" > "Edit Commands" > Send text below.', $this->plugin_key), '<a href="https://t.me/BotFather" target="_blank">@BotFather</a>');
                            ?>
                        </span>
                        <textarea cols="30" class="ltr" rows="<?php echo count($commands) ?>"
                                  onfocus="this.select();" onmouseup="return false;" readonly><?php
                            $list = array();
                            foreach ($commands as $command => $desc)
                                $list[] = $command . ' - ' . $desc;
                            echo implode("\n", $list);
                            ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Channel', $this->plugin_key) ?></th>
                    <td>
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
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    function about_settings_content()
    {
        ?>
        <div id="<?php echo $this->aboutTabID ?>-content" class="wptp-tab-content hidden">
            <p>
            <h3><?php _e('Integrate WordPress with Telegram', $this->plugin_key) ?></h3>
            </p>
            <p><?php _e('Do you like WP Telegram Pro?', $this->plugin_key) ?>
                <br>
                <a href="https://wordpress.org/support/plugin/wp-telegram-pro/reviews/#new-post" target="_blank">
                    <?php _e('Give it a rating', $this->plugin_key) ?>
                    <br><span class="star-ratings">★★★★★</span></a>
            </p>
            <p>
                <?php
                _e('Keep in touch with me:', $this->plugin_key);
                ?> <a href="http://parsa.ws">Parsa Kafi</a>
            </p>
        </div>
        <?php
    }
    
    function get_init()
    {
        $data = $this->telegram_input;
        $user_text = $data['text'];
        
        // When pressed inline keyboard button
        if (isset($data['data'])) {
            do_action('wptelegrampro_inline_keyboard_response', $data);
        } else {
            do_action('wptelegrampro_keyboard_response', $user_text);
        }
        exit;
    }
    
    function change_user_status($user_text)
    {
        $allow_status = array('search');
        $user_text = trim($user_text, '/');
        $this->words = $words = $words = apply_filters('wptelegrampro_words', $this->words);
        $words = array_flip($words);
        if (isset($words[$user_text])) {
            if (in_array($words[$user_text], $allow_status))
                $new_status = $words[$user_text];
            else
                $new_status = 'start';
            
            $this->update_user_meta('search_query', null);
            
            $this->update_user(array('status' => $new_status));
            $this->set_user();
        }
    }
    
    function button_data_check($button_data, $word)
    {
        return substr($button_data, 0, strlen($word)) == $word;
    }
    
    function get_bot_info()
    {
        $this->telegram->bot_info();
        $bot_info = $this->telegram->get_last_result();
        if ($bot_info['ok'] && $bot_info['result']['is_bot']) {
            echo __('Bot Name:', $this->plugin_key) . ' ' . $bot_info['result']['first_name'] . ' , @' . $bot_info['result']['username'];
        } else {
            _e('API Token Invalid (Need to save settings)', $this->plugin_key);
        }
        exit;
    }
    
    function check_user_id($user_id = null)
    {
        if (!isset($_COOKIE['wptpwc_user_id']) || empty($_COOKIE['wptpwc_user_id']) || strlen($_COOKIE['wptpwc_user_id']) != $this->rand_id_length)
            return $user_id;
        if (is_user_logged_in() && $user_id === null)
            $user_id = get_current_user_id();
        $user = $this->set_user(array('rand_id' => $_GET['wptpwc_user_id']));
        if ($user === null || !empty($user['wp_id']))
            return $user_id;
        $this->update_user(array('wp_id' => $user_id));
        $this->telegram->sendMessage(__('Welcome, your user is successfully connected to the store.', $this->plugin_key), null, $user['user_id']);
        setcookie('wptpwc_user_id', null, -1);
        unset($_COOKIE['wptpwc_user_id']);
    }
    
    function keyboard_columns($length, $count)
    {
        if ($length >= 3 && $length <= 5)
            $columns = 4;
        elseif ($length >= 6 && $length <= 8)
            $columns = 3;
        elseif ($length >= 9 && $length <= 11)
            $columns = 2;
        elseif ($length >= 12)
            $columns = 1;
        else
            $columns = 5;
        for ($i = 2; $i <= $columns; $i++)
            if ($count % $columns != 0 && $count % $i == 0 && $count != $i && $count / $i <= $columns) {
                $columns = $count / $i;
                break;
            }
        return $columns;
    }
    
    function enqueue_scripts()
    {
        $version = rand(100, 200) . rand(200, 300);
        wp_enqueue_script('textrange-js', plugin_dir_url(__FILE__) . 'assets/js/textrange.js', array('jquery'), $version, true);
        wp_enqueue_script('jquery.caret-js', plugin_dir_url(__FILE__) . 'assets/js/jquery.caret.js', array('jquery'), $version, true);
        wp_enqueue_script('emojionearea-js', plugin_dir_url(__FILE__) . 'assets/js/emojionearea.min.js', array('jquery'), $version, true);
        wp_enqueue_style('emojionearea-css', plugin_dir_url(__FILE__) . 'assets/css/emojionearea.min.css', array(), $version, false);
        wp_enqueue_script('wptp-js', plugin_dir_url(__FILE__) . 'assets/js/wptp.js', array('jquery'), $version, true);
        // Localize the script with new data
        $translation_array = array(
            'new_channel' => __('New Channel', $this->plugin_key),
            'confirm_remove_channel' => __('Remove % Channel?', $this->plugin_key),
            // 'max_channel' => $this->ChannelWPT->max_channel,
        );
        wp_localize_script('wptp-js', 'wptp', $translation_array);
        wp_enqueue_style('wptp-css', plugin_dir_url(__FILE__) . 'assets/css/wptp.css', array(), $version, false);
    }
    
    function message($message, $type = 'updated', $is_dismissible = true)
    {
        return '<div id="setting-error-settings_updated" class="' . $type . ($is_dismissible ? ' is-dismissible ' : '') . ' settings-error notice " ><p>' . $message . '</p></div> ';
    }
    
    function webHookURL()
    {
        $rand = 'wptp-' . rand(1000, 2000) . rand(2000, 3000) . rand(3000, 4000);
        $url = get_bloginfo('url') . '/' . '?wptp=' . $rand;
        return array('url' => $url, 'rand' => $rand);
    }
    
    function menu()
    {
        add_menu_page($this->plugin_name, $this->plugin_name, 'manage_options', $this->plugin_key, array($this, 'settings'), 'dashicons-wptp-telegram');
    }
    
    function after_settings_updated_message($update_message)
    {
        $update_message .= $this->message(__('Settings saved.', $this->plugin_key));
        return $update_message;
    }
    
    function settings()
    {
        $tabs_title_list = array();
        $tabs_title = apply_filters('wptelegrampro_settings_tabs', $tabs_title_list);
        
        $update_message = '';
        if (isset($_POST['wpt_nonce_field']) && wp_verify_nonce($_POST['wpt_nonce_field'], 'settings_submit')) {
            unset($_POST['wpt_nonce_field']);
            unset($_POST['_wp_http_referer']);
            
            do_action('wptelegrampro_before_settings_updated', $this->options, $_POST);
            $update_message = apply_filters('wptelegrampro_before_settings_update_message', $update_message, $this->options, $_POST);
            
            update_option($this->plugin_key, $_POST);
            
            do_action('wptelegrampro_after_settings_updated', $this->options, $_POST);
            $update_message = apply_filters('wptelegrampro_after_settings_update_message', $update_message, $this->options, $_POST);
        }
        
        $this->options = get_option($this->plugin_key);
        add_filter('wp_dropdown_cats', array($this, 'dropdown_filter'), 10, 2);
        ?>
        <div class="wrap wptp-wrap">
            <h1 class="wp-heading-inline"><?php echo $this->plugin_name ?></h1>
            <?php echo $update_message; ?>
            <div class="nav-tab-wrapper">
                <?php
                $first_tab = true;
                foreach ($tabs_title as $tab => $label) {
                    echo '<a id="' . $tab . '" class="wptp-tab nav-tab ' . ($first_tab ? 'nav-tab-active' : '') . '">' . $label . '</a>';
                    $first_tab = false;
                }
                ?>
            </div>
            <form action="" method="post">
                <?php wp_nonce_field('settings_submit', 'wpt_nonce_field');
                do_action('wptelegrampro_settings_content');
                ?>

                <button type="submit" class="button-save">
                    <span class="dashicons dashicons-yes"></span> <span><?php _e('Save') ?></span>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get Plugin Option
     * @param string $key Option Name
     * @param string $default Default Option Value
     * @return  string|array Option Value
     */
    function get_option($key, $default = '')
    {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    function query($query = array())
    {
        global $post;
        $keys = array('per_page', 'category_id', 'post_type', 's');
        foreach ($keys as $key) {
            if (!isset($query[$key]))
                $query[$key] = null;
        }
        
        if ($query['post_type'] === null)
            $query['post_type'] = 'post';
        
        $temp = $post;
        $per_page = $query['per_page'] == null ? $this->per_page : $query['per_page'];
        $page = $this->user['page'];
        
        $image_size = $this->get_option('image_size');
        $items = array();
        $args = array(
            'post_type' => $query['post_type'],
            'post_status' => 'publish'
        );
        if (isset($query['p'])) {
            $args['p'] = $query['p'];
        } else {
            $args = array_merge($args, array(
                'posts_per_page' => intval($per_page),
                'paged' => intval($page),
                'order' => 'DESC',
                'orderby' => 'modified'
            ));
            
            $args['tax_query'] = array('relation' => 'AND');
            
            if (isset($query['s']) && !empty(trim($query['s']))) {
                $args['s'] = $query['s'] . ' ' . mb_strtolower($query['s'], 'UTF-8');
                $args['sentence'] = '';
            }
            
            if (isset($query['tax_query']))
                $args['tax_query'] = array_merge($args['tax_query'], $query['tax_query']);
            
            if ($query['post_type'] == 'post') {
                if ($query['category_id'] !== null)
                    $args['cat'] = intval($query['category_id']);
            }
        }
        
        $args = apply_filters('wptelegrampro_query_args', $args, $query);
        
        $query_ = new WP_Query($args);
        
        $max_num_pages = $query_->max_num_pages;
        if (!$max_num_pages) $max_num_pages = 1;
        $items['max_num_pages'] = $max_num_pages;
        
        $items['parameter'] = array(
            'category_id' => $query['category_id'],
            'post_type' => $query['post_type'],
            's' => $query['s']
        );
        
        $c = 0;
        if ($query_->have_posts()) {
            while ($query_->have_posts()) {
                $query_->the_post();
                $post_id = get_the_ID();
                $image = $image_path = $file_name = null;
                $post_type = get_post_type($post_id);
                if (!isset($items[$post_type]))
                    $items[$post_type] = array();
                
                if (has_post_thumbnail($post_id) && !empty($image_size)) {
                    $image = get_the_post_thumbnail_url($post_id, $image_size);
                    $meta_data = wp_get_attachment_metadata(get_post_thumbnail_id());
                    if (is_array($meta_data)) {
                        $upload_dir = wp_upload_dir();
                        $image_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $meta_data['file'];
                        if ($image_size != 'full' && isset($meta_data['sizes'][$image_size])) {
                            $file_name = pathinfo($image_path, PATHINFO_BASENAME);
                            $image_path = str_replace($file_name, $meta_data['sizes'][$image_size]['file'], $image_path);
                        }
                    }
                }
                
                $items[$post_type][$c] = array(
                    'ID' => $post_id,
                    'title' => get_the_title(),
                    'slug' => $post->post_name,
                    'content' => get_the_content(),
                    'excerpt' => get_the_excerpt(),
                    'link' => get_the_permalink(),
                    'short-link' => wp_get_shortlink(),
                    'author' => get_the_author(),
                    'author-link' => get_author_posts_url(get_the_author_meta('ID'), get_the_author_meta('user_nicename')),
                    'image' => $image,
                    'image_path' => $image_path,
                    'tags' => null,
                    'categories' => null
                );
                
                if ($post_type == 'post') {
                    $items[$post_type][$c]['tags'] = $this->get_taxonomy_terms('post_tag', $post_id);
                    $items[$post_type][$c]['categories'] = $this->get_taxonomy_terms('category', $post_id);
                }
                
                $items = apply_filters('wptelegrampro_post_info', $items, $post_id, $query, $c);
                
                $c++;
            }
        }
        
        wp_reset_postdata();
        wp_reset_query();
        $post = $temp;
        
        if (isset($query['p']) && !is_array($query['post_type']) && count($items[$query['post_type']]) == 1)
            $items = current($items[$query['post_type']]);
        
        return $items;
    }
    
    /**
     * Get Taxonomy Terms
     * @param $taxonomy string Taxonomy Name
     * @param $post_id int Post ID
     * @return array
     */
    function get_taxonomy_terms($taxonomy, $post_id)
    {
        $terms_ = array();
        $terms = get_the_terms($post_id, $taxonomy);
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $terms_[$term->name] = array(
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug
                );
            }
        }
        return $terms_;
    }
    
    function get_user_meta($key)
    {
        $meta = $this->user['meta'];
        if (empty($meta))
            return null;
        
        $meta = unserialize($meta);
        if (isset($meta[$key]))
            return $meta[$key];
        else
            return null;
    }
    
    function update_user_meta($key, $value)
    {
        $meta = $this->user['meta'];
        if (empty($meta))
            $meta = array();
        else
            $meta = unserialize($meta);
        
        $meta[$key] = $value;
        $this->update_user(array('meta' => serialize($meta)));
    }
    
    function user_field($key)
    {
        return $this->user[$key];
    }
    
    function update_user($update_field)
    {
        global $wpdb;
        if (!is_array($update_field))
            return false;
        
        $result = $wpdb->update(
            $this->db_table,
            array_merge($update_field, array('updated_at' => $this->now)),
            array('user_id' => $this->user['user_id'])
        );
        if ($result)
            $this->set_user(array('user_id' => $this->telegram_input['form']['id']));
    }
    
    function set_user($args = array())
    {
        global $wpdb;
        
        if (isset($args['id']) && $args['id'] != null && is_numeric($args['id']))
            return $this->user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE id = '{$args['id']}'", ARRAY_A);
        if (isset($args['user_id']) && $args['user_id'] != null && is_numeric($args['user_id']))
            return $this->user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE user_id = '{$args['user_id']}'", ARRAY_A);
        if (isset($args['rand_id']) && $args['rand_id'] != null && is_numeric($args['rand_id']))
            return $this->user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE rand_id = '{$args['rand_id']}'", ARRAY_A);
        if (isset($args['wp_id']) && $args['wp_id'] != null && is_numeric($args['wp_id']))
            return $this->user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE wp_id = {$args['wp_id']}", ARRAY_A);
        
        $from = $this->telegram_input['from'];
        if (isset($from['id'])) {
            $sql = "SELECT * FROM {$this->db_table} WHERE user_id = '{$from['id']}'";
            $user = $wpdb->get_row($sql, ARRAY_A);
            if ($user)
                $wpdb->update(
                    $this->db_table,
                    array(
                        'first_name' => $from['first_name'],
                        'last_name' => $from['last_name'],
                        'username' => $from['username'],
                        'updated_at' => $this->now,
                    ),
                    array('user_id' => $from['id'])
                );
            else {
                $rand_id = $this->random_id();
                $wpdb->insert(
                    $this->db_table,
                    array(
                        'user_id' => $from['id'],
                        'rand_id' => $rand_id,
                        'first_name' => $from['first_name'],
                        'last_name' => $from['last_name'],
                        'username' => $from['username'],
                        'status' => 'start',
                        'meta' => serialize(array('update_keyboard_time' => time())),
                        'created_at' => $this->now,
                        'updated_at' => $this->now
                    )
                );
            }
            
            $this->user = $wpdb->get_row($sql, ARRAY_A);
        }
        return false;
    }
    
    function check_plugin_active($plugin)
    {
        if ($plugin == 'woocommerce')
            $plugin = 'woocommerce/woocommerce.php';
        
        if (!function_exists('is_plugin_active'))
            require_once($this->get_admin_path() . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php');
        return is_plugin_active($plugin);
    }
    
    /**
     * Obtain the path to the admin directory.
     * Thanks @andrezrv & @tazziedave, https://gist.github.com/tazziedave/72e03cecd0cd756785e0f28f652f7d8c
     * @return string
     */
    function get_admin_path()
    {
        // Replace the site base URL with the absolute path to its installation directory.
        $blogUrl = preg_replace("(^https?://)", "", get_bloginfo('url'));
        $adminUrl = preg_replace("(^https?://)", "", get_admin_url());
        $admin_path = str_replace($blogUrl . '/', ABSPATH, $adminUrl);
        // Make it filterable, so other plugins can hook into it.
        $admin_path = apply_filters('wptelegrampro_get_admin_path', $admin_path);
        return $admin_path;
    }
    
    /**
     * Get Taxonomy Terms Keyboard
     *
     * @param string $command Command Name
     * @param string $taxonomy Taxonomy Name
     * @param string $order_by Order by, Default: count
     * @param array $exclude Exclude Terms, Default: array()
     * @return array|boolean Terms list with Telegram Inline Keyboard Structure
     */
    function get_tax_keyboard($command, $taxonomy, $order_by = 'count', $exclude = array())
    {
        $terms = get_terms($taxonomy, [
            'hide_empty' => true,
            'orderby' => $order_by,
            'order' => 'DESC',
            'exclude' => $exclude
        ]);
        if ($terms) {
            $terms_r = $terms_d = array();
            $c = 1;
            foreach ($terms as $term) {
                $terms_d[] = array(
                    'text' => $term->name,
                    'callback_data' => $command . '_' . $term->term_id
                );
                if ($c % 3 == 0) {
                    $terms_r[] = $terms_d;
                    $terms_d = array();
                }
                $c++;
            }
            if (count($terms_d))
                $terms_r[] = $terms_d;
            return $terms_r;
        }
        return false;
    }
    
    /**
     * WordPress Image Size Select
     * @param string $name Select Name
     * @param string $selected Current Selected Value
     * @param string $none_select none option
     * @return  string HTML Image Size Select
     */
    function image_size_select($name, $selected = null, $none_select = null)
    {
        $image_sizes = $this->get_image_sizes();
        $select = '<select name="' . $name . '" id="' . $name . '">';
        if ($none_select != null)
            $select .= '<option value="">' . $none_select . '</option>';
        $select .= '<option value="full" ' . selected('full', $selected, false) . '>' . __('Full', $this->plugin_key) . '</option>';
        foreach ($image_sizes as $k => $v)
            $select .= '<option value="' . $k . '" ' . selected($k, $selected, false) . '>' . (ucwords(str_replace('_', ' ', $k))) . (!empty($v['width']) ? ' (' . $v['width'] . 'x' . $v['height'] . ($v['crop'] ? ', ' . __('Crop', $this->plugin_key) : '') . ')' : '') . '</option>';
        $select .= '</select>';
        return $select;
    }
    
    /** https://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
     * Get size information for all currently-registered image sizes.
     *
     * @return array $sizes Data for all currently-registered image sizes.
     * @uses   get_intermediate_image_sizes()
     * @global $_wp_additional_image_sizes
     */
    function get_image_sizes()
    {
        global $_wp_additional_image_sizes;
        $sizes = array();
        //var_dump($_wp_additional_image_sizes);
        foreach (get_intermediate_image_sizes() as $_size) {
            if (in_array($_size, array('thumbnail', 'medium', 'medium_large', 'large'))) {
                $sizes[$_size]['width'] = get_option("{
       $_size}_size_w");
                $sizes[$_size]['height'] = get_option("{
        $_size}_size_h");
                $sizes[$_size]['crop'] = (bool)get_option("{
        $_size}_crop");
            } elseif (isset($_wp_additional_image_sizes[$_size])) {
                $sizes[$_size] = array(
                    'width' => $_wp_additional_image_sizes[$_size]['width'],
                    'height' => $_wp_additional_image_sizes[$_size]['height'],
                    'crop' => $_wp_additional_image_sizes[$_size]['crop'],
                );
            }
        }
        return $sizes;
    }
    
    function dropdown_categories($name, $taxonomy, $selected = array())
    {
        $select = wp_dropdown_categories(array(
            'name' => $name,
            'id' => $name,
            'show_option_none' => __('- Category Select -', $this->plugin_key),
            'option_none_value' => '-1',
            'taxonomy' => $taxonomy,
            'hierarchical' => true,
            'echo' => false
        ));
        
        if (is_array($selected))
            foreach ($selected as $sel)
                if (!empty($sel) && $sel != '-1')
                    $select = str_replace('value="' . $sel . '"', 'value="' . $sel . '" selected', $select);
        
        return $select;
    }
    
    function dropdown_filter($output, $r)
    {
        $output = preg_replace('/<select (.*?) >/', '<select $1 size="5" multiple>', $output);
        return $output;
    }
    
    private function random_id()
    {
        global $wpdb;
        $id_not_exists = false;
        $id = '';
        while (!$id_not_exists) {
            $id = $this->random_strings($this->rand_id_length);
            $user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE rand_id = '{$id}'", ARRAY_A);
            if ($user === null)
                $id_not_exists = true;
        }
        return $id;
    }
    
    private function random_strings($length, $string_type = array('NUMBER'))
    {
        $original_string = array();
        if (in_array("NUMBER", $string_type)) {
            $original_string = array_merge(range(1, 9), $original_string);
        }
        if (in_array("CLCASE", $string_type)) {
            $original_string = array_merge(range('a', 'z'), $original_string);
        }
        if (in_array("CUCASE", $string_type)) {
            $original_string = array_merge(range('A', 'Z'), $original_string);
        }
        
        $original_string = implode("", $original_string);
        $original_string = strlen($original_string) < $length ? str_repeat($original_string, intval($length / strlen($original_string) + 1)) : $original_string;
        return substr(str_shuffle($original_string), 0, $length);
    }
    
    function add_every_minutes($schedules)
    {
        for ($i = 1; $i <= 60; $i++) {
            $title = str_replace("%", "%d", __('Every % Minutes', $this->plugin_key));
            $schedules['every_' . $i . '_minutes'] = array(
                'interval' => $i * 60,
                'display' => sprintf($title, $i)
            );
        }
        return $schedules;
    }
    
    static function install()
    {
        global $table_prefix, $wpdb;
        
        $table_name = 'wptelegrampro_users';
        $wp_table = $table_prefix . $table_name;
        
        if ($wpdb->get_var("show tables like '$wp_table'") != $wp_table) {
            $sql = "CREATE TABLE `{$wp_table}` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `rand_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `wp_id` bigint(20) DEFAULT NULL,
                  `user_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `first_name` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `last_name` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `username` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `cart` text COLLATE utf8mb4_unicode_ci,
                  `page` int(11) NOT NULL DEFAULT '1',
                  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `meta` text COLLATE utf8mb4_unicode_ci,
                  `user_active` tinyint(4) NOT NULL DEFAULT '1',
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `rand_id` (`rand_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        update_option('wptelegrampro_version', WPTELEGRAMPRO_VERSION);
        update_option('update_keyboard_time_wptp', time());
    }
    
    /**
     * Returns an instance of class
     * @return  WPTelegramPro
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new WPTelegramPro();
        return self::$instance;
    }
}

$WPTelegramPro = WPTelegramPro::getInstance();
register_activation_hook(__FILE__, array('WPTelegramPro', 'install'));