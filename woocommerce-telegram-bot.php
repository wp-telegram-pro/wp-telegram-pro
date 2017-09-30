<?php
/*
Plugin Name: WooCommerce Telegram Bot
Plugin URI: http://parsa.ws
Description: Telegram bot for WooCommerce
Author: Parsa Kafi
Version: 1.0
Author URI: http://parsa.ws
*/

if (!defined('ABSPATH'))
    exit;

define('WCTB_INC_PATH', str_replace('/', DIRECTORY_SEPARATOR, plugin_dir_path(__FILE__)) . 'inc' . DIRECTORY_SEPARATOR);
require_once WCTB_INC_PATH . 'TelegramWCTB.php';

class WooCommerceTelegramBot
{
    protected $plugin_key = 'woocommerce-telegram-bot';
    protected $telegram, $telegram_input, $words, $options, $db_table, $user, $now, $default_products_keyboard;

    public function __construct()
    {
        global $wpdb;
        $this->words = array(
            'next' => __('< Next', $this->plugin_key),
            'prev' => __('Previous >', $this->plugin_key),
            'next_page' => __('< Next Page', $this->plugin_key),
            'prev_page' => __('Previous Page >', $this->plugin_key),
            'back' => __('Back', $this->plugin_key),
            'products' => __('Products', $this->plugin_key),
            'categories' => __('Categories', $this->plugin_key),
            'cart' => __('Cart', $this->plugin_key),
            'checkout' => __('Checkout', $this->plugin_key),
            'detail' => __('Detail', $this->plugin_key),
        );
        $this->db_table = $wpdb->prefix . 'woocommerce_telegram_bot';
        $this->options = get_option($this->plugin_key);
        $this->now = date("Y-m-d H:i:s");
        $this->default_products_keyboard = array(array(
            array('text' => __('Detail', $this->plugin_key), 'callback_data' => 'product_detail'),
            array('text' => '🔄', 'callback_data' => 'test')
        ));
        $this->telegram = new TelegramWCTB($this->get_option('api_token'));
        try {
            if (isset($_GET['wctb']) && $_GET['wctb'] == get_option('wctb-rand-url')) {
                $this->telegram_input = $this->telegram->input();
                $this->set_user();
                add_action('init', array($this, 'init'));
            }
        } catch (Exception $e) {
            // Exception
        }
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    function init()
    {
        $data = $this->telegram_input;
        $words = $this->words;
        $user_text = $data['text'];
        // When pressed inline keyboard button
        if (isset($data['data'])) {
            $button_data = $data['data'];

            if (substr($button_data, 0, 5) == 'page_') {
                $current_page = $this->user['page'];
                if ($button_data == 'page_next')
                    $current_page++;
                else
                    $current_page--;

                $this->update_user(array('page' => $current_page));
                $this->telegram->answerCallbackQuery(__('Page: ', $this->plugin_key) . $current_page);
                $keyboard = $this->telegram->keyboard($this->default_products_keyboard, 'inline_keyboard');
                $this->telegram->editMessageReplyMarkup($keyboard);
                $products = $this->query(array('category_id' => $this->get_user_meta('product_category_id')));
                $this->send_products($products);
            }
            if (substr($button_data, 0, 16) == 'product_category') {
                $this->update_user(array('page' => 1));
                $product_category_id = intval(end(explode('_', $button_data)));
                $this->update_user_meta('product_category_id', $product_category_id);
                $product_category = get_term($product_category_id, 'product_cat');
                if ($product_category) {
                    $this->telegram->answerCallbackQuery(__('Category: ', $this->plugin_key) . $product_category->name);
                    $products = $this->query(array('category_id' => $product_category_id));
                    $this->send_products($products);
                } else {
                    $this->telegram->answerCallbackQuery(__('Product Category Invalid!', $this->plugin_key));
                }
            }
        } else {
            if ($user_text == '/start') {
                $keyboard = $this->telegram->keyboard(array($words['products'], $words['categories']));
                $this->telegram->sendMessage($this->get_option('start_command'), $keyboard);
                $this->telegram->sendMessage(json_encode($this->telegram_input));

            } elseif ($user_text == '/products' || $user_text == $words['products']) {
                $this->update_user(array('page' => 1));
                $this->update_user_meta('product_category_id', null);
                $products = $this->query();
                $this->send_products($products);

            } elseif ($user_text == '/categories' || $user_text == $words['categories']) {
                $product_category = $this->get_tax_keyboard('product_cat');
                $keyboard = $this->telegram->keyboard($product_category, 'inline_keyboard');
                $this->telegram->sendMessage($words['categories'] . ":", $keyboard);
            }
        }
        exit;
    }

    function send_products($products)
    {
        if (count($products['product'])) {
            $keyboard = $this->default_products_keyboard;
            $i = 1;
            $current_page = $this->user['page'];
            foreach ($products['product'] as $product) {
                if ($products['max_num_pages'] > 1 && $i == count($products['product'])) {
                    $keyboard[1] = array();
                    if ($current_page < $products['max_num_pages'])
                        $keyboard[1][] = array('text' => $this->words['next_page'], 'callback_data' => 'page_next');
                    if ($current_page > 1)
                        $keyboard[1][] = array('text' => $this->words['prev_page'], 'callback_data' => 'page_prev');
                }
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                if ($product['image_path'] !== null)
                    $this->telegram->sendFile('sendPhoto', $product['image_path'], $product['title'] . "\n" . $product['excerpt'], $keyboards);
                else
                    $this->telegram->sendMessage($product['title'] . "\n" . $product['excerpt'], $keyboards);
                $i++;
            }
        } else {
            $this->telegram->sendMessage(__('Your request without result!', $this->plugin_key));
        }
    }

    function set_const()
    {

    }

    function enqueue_scripts()
    {
        $version = rand(100, 200) . rand(200, 300);
        wp_register_script('wctb-js', plugin_dir_url(__FILE__) . 'assets/js/wctb.js', array('jquery'), $version);
        wp_enqueue_script('wctb-js');
        wp_enqueue_style('wctb-css', plugin_dir_url(__FILE__) . 'assets/css/wctb.css', array(), $version, false);
    }

    function menu()
    {
        add_submenu_page('woocommerce', __('WooCommerce Telegram Bot', $this->plugin_key), __('Telegram Bot', $this->plugin_key), 'manage_options', $this->plugin_key, array($this, 'settings'));
    }

    function message($message, $type = 'updated')
    {
        return '<div id="setting-error-settings_updated" class="' . $type . ' settings-error notice is-dismissible" ><p><strong>' . $message . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __('Dismiss this notice.') . '</span></button></div> ';
    }

    function webHookURL()
    {
        $rand = 'wctb-' . rand(1000, 2000) . rand(2000, 3000) . rand(3000, 4000);
        $url = get_bloginfo('url') . '/' . '?wctb=' . $rand;
        return array('url' => $url, 'rand' => $rand);
    }

    function settings()
    {
        $update_message = '';
        if (isset($_POST['wsb_nonce_field']) && wp_verify_nonce($_POST['wsb_nonce_field'], 'settings_submit')) {
            unset($_POST['wsb_nonce_field']);
            unset($_POST['_wp_http_referer']);

            update_option($this->plugin_key, $_POST);
            $update_message = $this->message(__('Settings saved.'));

            if (isset($_POST['setWebhook'])) {
                $webHook = $this->webHookURL();
                if ($this->telegram->setWebhook($webHook['url'])) {
                    $update_message .= $this->message(__('Set Webhook Successfully.'));
                    update_option('wctb-rand-url', $webHook['rand']);
                } else
                    $update_message .= $this->message(__('Set Webhook with Error!'), 'error');
            }
        }

        $this->options = get_option($this->plugin_key);
        ?>
        <div class="wrap wctb-wrap">
            <h1 class="wp-heading-inline"><?php _e('WooCommerce Telegram Bot', $this->plugin_key) ?></h1>
            <?php echo $update_message; ?>
            <div class="nav-tab-wrapper">
                <a id="TabMB1" class="mb-tab nav-tab nav-tab-active"><?php _e('Global', $this->plugin_key) ?></a>
                <a id="TabMB2" class="mb-tab nav-tab"><?php _e('Messages', $this->plugin_key) ?></a>
            </div>
            <form action="" method="post">
                <?php wp_nonce_field('settings_submit', 'wsb_nonce_field'); ?>
                <div id="TabMB1Content" class="mb-tab-content">
                    <table>
                        <tr>
                            <td><label for="api_token"><?php _e('Telegram Api Token', $this->plugin_key) ?></label></td>
                            <td><input type="text" name="api_token" id="api_token"
                                       value="<?php echo $this->get_option('api_token') ?>"
                                       class="regular-text ltr"></td>
                        </tr>
                        <tr>
                            <td><label for="setWebhook"><?php _e('Telegram Set Webhook', $this->plugin_key) ?></label>
                            </td>
                            <td><label><input type="checkbox" value="1"
                                              name="setWebhook"
                                              id="setWebhook"> <?php _e('Set Webhook', $this->plugin_key) ?></label>
                                <br><span
                                        class="description"><?php _e('One time is needed', $this->plugin_key) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="products_per_page"><?php _e('Products Per Page', $this->plugin_key) ?></label>
                            </td>
                            <td><input type="number" name="products_per_page" id="products_per_page"
                                       value="<?php echo $this->get_option('products_per_page') ?>"
                                       class="small-text ltr"></td>
                        </tr>
                        <tr>
                            <td>
                                <label for="image_size"><?php _e('Image Size', $this->plugin_key) ?></label>
                            </td>
                            <td><?php echo $this->image_size_select('image_size', $this->get_option('image_size'), '---') ?></td>
                        </tr>
                    </table>
                </div>
                <div id="TabMB2Content" class="mb-tab-content hidden">
                    <table>
                        <tr>
                            <td>
                                <label for="start_command"><?php _e('Start Command<br>(Welcome Message)', $this->plugin_key) ?></label>
                            </td>
                            <td>
                            <textarea name="start_command" id="start_command" cols="50"
                                      rows="4"><?php echo $this->get_option('start_command') ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
                <input type="submit" class="button button-primary"
                       value="<?php _e('Save') ?>">
            </form>
            <hr>
            <a href="http://parsa.ws">Parsa.ws</a>
        </div>
        <?php
    }

    function get_option($key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : '';
    }

    function query($query = array())
    {
        global $post;
        $keys = array('per_page', 'category_id', 'post_type');
        foreach ($keys as $key) {
            if (!isset($query[$key]))
                $query[$key] = null;
        }
        if ($query['post_type'] === null)
            $query['post_type'] = 'product';

        $temp = $post;
        $per_page = $query['per_page'] == null ? $this->get_option('products_per_page') : $query['per_page'];
        $page = $this->user['page'];
        $image_size = $this->get_option('image_size');
        $items = array($query['post_type'] => array());
        $args = array(
            'post_type' => $query['post_type'],
            'posts_per_page' => intval($per_page),
            'paged' => intval($page),
            'order' => 'DESC',
            'orderby' => 'modified',
        );
        if ($query['category_id'] !== null)
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => intval($query['category_id'])
                )
            );
        $query_ = new WP_Query($args);
        $max_num_pages = $query_->max_num_pages;
        if (!$max_num_pages)
            $max_num_pages = 1;
        $items['max_num_pages'] = $max_num_pages;
        $items['parameter'] = array('category_id' => $query['category_id'], 'post_type' => $query['post_type']);

        if ($query_->have_posts()) {
            while ($query_->have_posts()) {
                $query_->the_post();
                $image = $image_path = $file_name = null;
                if (has_post_thumbnail(get_the_ID()) && !empty($image_size)) {
                    $image = get_the_post_thumbnail_url(get_the_ID(), $image_size);
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
                $items[$query['post_type']][] = array(
                    'title' => get_the_title(),
                    'content' => get_the_content(),
                    'excerpt' => get_the_excerpt(),
                    'link' => get_the_permalink(),
                    'image' => $image,
                    'image_path' => $image_path
                );
            }
        }
        wp_reset_postdata();
        wp_reset_query();
        $post = $temp;
        return $items;
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
            $this->set_user($this->user['user_id']);
    }

    function set_user($user_id = null)
    {
        global $wpdb;
        if ($user_id != null && is_numeric($user_id))
            return $this->user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE user_id = " . $user_id, ARRAY_A);

        $from = $this->telegram_input['from'];
        if (isset($from['id'])) {
            $user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE user_id = " . $from['id'], ARRAY_A);
            if ($user)
                $result = $wpdb->update(
                    $this->db_table,
                    array(
                        'first_name' => $from['first_name'],
                        'last_name' => $from['last_name'],
                        'username' => $from['username'],
                        'updated_at' => $this->now,
                    ),
                    array('user_id' => $from['id'])
                );
            else
                $result = $wpdb->insert(
                    $this->db_table,
                    array(
                        'user_id' => $from['id'],
                        'first_name' => $from['first_name'],
                        'last_name' => $from['last_name'],
                        'username' => $from['username'],
                        'status' => 'start',
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    )
                );

            if ($result)
                $this->user = $wpdb->get_row("SELECT * FROM {$this->db_table} WHERE user_id = " . $from['id'], ARRAY_A);
            else
                return false;
        }
    }

    /**
     * Get Taxonomy Terms Keyboard
     *
     * @param string $taxonomy Taxonomy Name
     * @param string $order_by Order by, Default: count
     * @return array|boolean Terms list with Telegram Inline Keyboard Structure
     */
    function get_tax_keyboard($taxonomy, $order_by = 'count')
    {
        $terms = get_terms($taxonomy, [
            'hide_empty' => true,
            'orderby' => $order_by,
            'order' => 'DESC'
        ]);
        if ($terms) {
            $terms_r = $terms_d = array();
            $c = 1;
            foreach ($terms as $term) {
                $terms_d[] = array(
                    'text' => $term->name,
                    'callback_data' => 'product_category_' . $term->term_id
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
     * @param   string $name Select Name
     * @param   string $selected Current Selected Value
     * @param   string $none_select none option
     * @return  string HTML Image Size Select
     */
    function image_size_select($name, $selected = null, $none_select = null)
    {
        $image_sizes = $this->get_image_sizes();
        $select = '<select name="' . $name . '" id="' . $name . '">';
        if ($none_select != null)
            $select .= '<option value="">' . $none_select . '</option>';
        $select .= '<option value="full" ' . selected('full', $selected, false) . '>' . __('Full Image', $this->plugin_key) . '</option>';
        foreach ($image_sizes as $k => $v)
            $select .= '<option value="' . $k . '" ' . selected($k, $selected, false) . '>' . $k . ' (' . $v['width'] . '×' . $v['height'] . ($v['crop'] ? __(', Crop', $this->plugin_key) : '') . ')</option>';
        $select .= '</select>';
        return $select;
    }

    /** https://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
     * Get size information for all currently-registered image sizes.
     *
     * @global $_wp_additional_image_sizes
     * @uses   get_intermediate_image_sizes()
     * @return array $sizes Data for all currently-registered image sizes.
     */
    function get_image_sizes()
    {
        global $_wp_additional_image_sizes;
        $sizes = array();
        foreach (get_intermediate_image_sizes() as $_size) {
            if (in_array($_size, array('thumbnail', 'medium', 'medium_large', 'large'))) {
                $sizes[$_size]['width'] = get_option("{$_size}_size_w");
                $sizes[$_size]['height'] = get_option("{$_size}_size_h");
                $sizes[$_size]['crop'] = (bool)get_option("{$_size}_crop");
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
}

new WooCommerceTelegramBot();