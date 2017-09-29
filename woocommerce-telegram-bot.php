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
    protected $telegram, $telegram_input, $words, $options;

    public function __construct()
    {
        $this->words = array(
            'next' => __('Next', $this->plugin_key),
            'prev' => __('Previous', $this->plugin_key),
            'next_page' => __('Next Page', $this->plugin_key),
            'prev_page' => __('Previous Page', $this->plugin_key),
            'back' => __('Back', $this->plugin_key),
            'products' => __('Products', $this->plugin_key),
            'categories' => __('Categories', $this->plugin_key),
            'cart' => __('Cart', $this->plugin_key),
            'checkout' => __('Checkout', $this->plugin_key),
            'detail' => __('Detail', $this->plugin_key),
        );
        $this->options = get_option($this->plugin_key);
        $this->telegram = new TelegramWCTB($this->get_option('api_token'));
        try {
            if (isset($_GET['wctb']) && $_GET['wctb'] == get_option('wctb-rand-url')) {
                $this->telegram_input = $this->telegram->input();
                add_action('init', array($this, 'init'));
            }
        } catch (Exception $e) {
            // Exception
        }
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
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
        add_submenu_page('woocommerce',__('WooCommerce Telegram Bot', $this->plugin_key), __('Telegram Bot', $this->plugin_key), 'manage_options', $this->plugin_key, array($this, 'settings'));
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
                            <td><?php echo $this->image_size_select('image_size', $this->get_option('image_size')) ?></td>
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

    function init()
    {
        $data = $this->telegram_input;
        $words = $this->words;
        $user_text = $data['text'];
        if (isset($data['data'])) {
            $button_data = $data['data'];
            if (substr($button_data, 0, 16) == 'product_category') {
                $this->telegram->sendMessage($button_data);
            }
        } else {
            if ($user_text == '/start') {
                $keyboard = $this->telegram->keyboard(array($words['products'], $words['categories']));
                /*$keyboard = $this->telegram->keyboard(array(
                    array('text' => '↪️', 'callback_data' => '/new'),
                    array('text' => '🔄', 'callback_data' => '/sd')
                ), 'inline_keyboard');*/
                //$this->telegram->sendMessage($keyboard);
                $this->telegram->sendMessage($this->get_option('start_command'), $keyboard);

            } elseif ($user_text == '/products' || $user_text == $words['products']) {
                $product_category = $this->get_tax_keyboard('product_cat');
                $keyboard = $this->telegram->keyboard($product_category, 'inline_keyboard');
                $this->telegram->sendFile('sendPhoto', dirname(__FILE__) . '/test/pic.jpg', 'Test Caption', $keyboard);

            } elseif ($user_text == '/categories' || $user_text == $words['categories']) {
                $product_category = $this->get_tax_keyboard('product_cat');
                $keyboard = $this->telegram->keyboard($product_category, 'inline_keyboard');
                $this->telegram->sendMessage($words['categories'] . ":", $keyboard);
            }
        }
        exit;
    }

    function query($per_page = null, $category_id = null, $post_type = 'product')
    {
        global $post;
        $temp = $post;
        $per_page = $per_page == null ? $this->get_option('products_per_page') : $per_page;
        $items = array();
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => intval($per_page)
        );
        if ($category_id != null)
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            );
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $image = null;
                $image_path = null;
                if (has_post_thumbnail(get_the_ID())) {
                    $image = get_the_post_thumbnail_url(get_the_ID(), $this->get_option('image_size'));
                }
                $items[] = array(
                    'title' => get_the_title(),
                    'content' => get_the_content(),
                    'link' => get_the_permalink(),
                    'image' => $image,
                    'image_path' => $image_path,
                );
            }
        }
        wp_reset_postdata();
        wp_reset_query();
        $post = $temp;
        return $items;
    }

    /**
     * Get Taxonomy Terms Keyboard
     *
     * @param string $taxonomy Taxonomy Name
     * @param string $orderby Order by, Default: count
     * @return array|boolean Terms list with Telegram Inline Keyboard Structure
     */
    function get_tax_keyboard($taxonomy, $orderby = 'count')
    {
        $terms = get_terms($taxonomy, [
            'hide_empty' => true,
            'orderby' => $orderby,
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
     * @return  string HTML Image Size Select
     */
    function image_size_select($name, $selected = null)
    {
        $image_sizes = $this->get_image_sizes();
        $select = '<select name="' . $name . '" id="' . $name . '">';
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