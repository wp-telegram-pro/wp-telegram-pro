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
            //  array('text' => '🔄', 'callback_data' => 'test')
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

            if (substr($button_data, 0, 24) == 'product_variation_header') {
                $this->telegram->answerCallbackQuery(__('Select from the options below', $this->plugin_key));

            } elseif (substr($button_data, 0, 17) == 'product_variation') {
                $this->telegram->answerCallbackQuery($button_data);

            } elseif (substr($button_data, 0, 15) == 'image_galleries') {
                $product_id = intval(end(explode('_', $button_data)));
                if (get_post_status($product_id) === 'publish') {
                    $image_size = $this->get_option('image_size');
                    $this->telegram->answerCallbackQuery(__('Galleries: ', $this->plugin_key) . get_the_title($product_id));
                    $_product = new WC_Product($product_id);
                    $galleries = $_product->get_gallery_image_ids();
                    if (is_array($galleries) && count($galleries)) {
                        $keyboards = null;
                        $i = 1;
                        foreach ($galleries as $image) {
                            $meta_data = wp_get_attachment_metadata($image);
                            if (is_array($meta_data)) {
                                $upload_dir = wp_upload_dir();
                                $image_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $meta_data['file'];
                                if ($image_size != 'full' && isset($meta_data['sizes'][$image_size])) {
                                    $file_name = pathinfo($image_path, PATHINFO_BASENAME);
                                    $image_path = str_replace($file_name, $meta_data['sizes'][$image_size]['file'], $image_path);
                                }
                                if ($i == count($galleries)) {
                                    $keyboard = array(array(
                                        array('text' => __('Back to Product', $this->plugin_key), 'callback_data' => 'product_detail_' . $product_id)
                                    ));
                                    $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                                }
                                $this->telegram->sendFile('sendPhoto', $image_path, get_the_title($image), $keyboards);
                                $i++;
                            }
                        }
                    }
                } else {
                    $this->telegram->answerCallbackQuery(__('The product does not exist', $this->plugin_key));
                }
            } elseif (substr($button_data, 0, 11) == 'add_to_cart') {
                $product_id = intval(end(explode('_', $button_data)));
                if (get_post_status($product_id) === 'publish') {
                    $this->telegram->answerCallbackQuery(__('Add to Cart: ', $this->plugin_key) . get_the_title($product_id));

                } else {
                    $this->telegram->answerCallbackQuery(__('The product does not exist', $this->plugin_key));
                }

            } elseif (substr($button_data, 0, 14) == 'product_detail') {
                $product_id = intval(end(explode('_', $button_data)));
                if (get_post_status($product_id) === 'publish') {
                    $this->telegram->answerCallbackQuery(__('Product: ', $this->plugin_key) . get_the_title($product_id));
                    $product = $this->query(array('p' => $product_id));
                    $this->send_product($product);
                } else {
                    $this->telegram->answerCallbackQuery(__('The product does not exist', $this->plugin_key));
                }
                //$this->telegram->sendMessage(json_encode($this->telegram_input['input']));

            } elseif (substr($button_data, 0, 5) == 'page_') {
                $current_page = intval($this->user['page']) == 0 ? 1 : intval($this->user['page']);
                if ($button_data == 'page_next')
                    $current_page++;
                else
                    $current_page--;
                $this->update_user(array('page' => $current_page));
                //$this->telegram->sendMessage(json_encode($this->user['page']));
                $this->telegram->answerCallbackQuery(__('Page: ', $this->plugin_key) . $current_page);
                $keyboard = $this->telegram->keyboard($this->default_products_keyboard, 'inline_keyboard');
                //$this->telegram->editMessageReplyMarkup($keyboard);
                $products = $this->query(array('category_id' => $this->get_user_meta('product_category_id')));
                $this->send_products($products);

            } elseif (substr($button_data, 0, 16) == 'product_category') {
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
                //$this->telegram->sendMessage(json_encode($this->telegram_input));

            } elseif ($user_text == '/products' || $user_text == $words['products']) {
                $this->update_user(array('page' => 1));
                $this->update_user_meta('product_category_id', null);
                $products = $this->query();
                $this->send_products($products);
                //$this->telegram->sendMessage(json_encode($this->telegram_input));

            } elseif ($user_text == '/categories' || $user_text == $words['categories']) {
                $product_category = $this->get_tax_keyboard('product_cat');
                $keyboard = $this->telegram->keyboard($product_category, 'inline_keyboard');
                $this->telegram->sendMessage($words['categories'] . ":", $keyboard);
            }
        }
        exit;
    }

    function send_product($product)
    {
        $product = current($product['product']);
        $price = (!empty($product['sale_price']) ? $product['sale_price'] : $product['price']);
        $terms = get_the_terms($product['id'], 'product_type');
        if ($terms && $terms[0]->slug == 'variable') {
            $price = $product['price'];
        }
        $price = !empty($price) ? strip_tags(html_entity_decode(wc_price($price))) : $price;
        $add_info = '';
        $metas = array();

        // Weight
        if (!empty($product['weight']))
            $metas[] = __('Weight', $this->plugin_key) . ': ' . $product['weight'] . ' ' . get_option('woocommerce_weight_unit');

        // Dimensions
        if (!empty($product['dimensions']) && $product['dimensions'] != __('N/A', 'woocommerce'))
            $metas[] = __('Dimensions', $this->plugin_key) . ': ' . $product['dimensions'];

        // Attribute
        if (is_array($product['variations']) && count($product['variations'])) {
            foreach ($product['variations'] as $name => $variation) {
                if ($variation['is_visible'] == 0 || empty($variation['value']))
                    continue;
                $var_head = urldecode($name);
                if ($variation['is_variation'] == 1 && $variation['is_taxonomy'] == 1) {
                    $tax = get_taxonomy($var_head);
                    $var_head = $tax->labels->singular_name;
                }
                $items = array();
                if ($variation['is_taxonomy'] == 0) {
                    $items = array_map('urldecode', array_map('trim', explode('|', $variation['value'])));

                } elseif ($variation['is_taxonomy'] == 1) {
                    $terms = get_the_terms($product['id'], $variation['name']);
                    foreach ($terms as $term)
                        $items[] = $term->name;
                }
                $items = implode(', ', $items);
                $metas[] = $var_head . ': ' . $items;
            }
        }
        if (!empty($product['average_rating']) && intval($product['average_rating']) > 0) {
            $star = '';
            for ($i = 1; $i <= intval($product['average_rating']); $i++)
                $star .= "⭐️";
            $metas[] = $star;
        }

        if (count($metas))
            $add_info = "\n" . implode(' / ', $metas);

        $text = $product['title'] . "\n" . $price . $add_info . "\n" . $product['content'];

        // Keyboard
        $keyboard = $this->product_keyboard($product);
        $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');

        if ($product['image_path'] !== null && mb_strlen($text) <= 200)
            $this->telegram->sendFile('sendPhoto', $product['image_path'], $text, $keyboards);
        else {
            if ($product['image_path'] !== null)
                $this->telegram->sendFile('sendPhoto', $product['image_path']);
            $this->telegram->sendMessage($text, $keyboards);
        }
    }

    function product_keyboard($product)
    {
        /*$terms = get_the_terms($product['id'], 'product_type');
        if ($terms) {
            $product_type = $terms[0]->slug;
        }*/

        $keyboard = array(array(
            array('text' => __('Detail', $this->plugin_key), 'callback_data' => 'product_detail_' . $product['id']),
            array('text' => __('🔗', $this->plugin_key), 'url' => $product['link']),
            array('text' => __('➕🛒', $this->plugin_key), 'callback_data' => 'add_to_cart_' . $product['id']),
        ));

        // Gallery Emoji Button
        if (is_array($product['galleries']) && count($product['galleries'])) {
            $keyboard[0][] = array('text' => __('🖼️', $this->plugin_key), 'callback_data' => 'image_galleries_' . $product['id']);
        }

        // Category Button
        if (is_array($product['categories']) && count($product['categories'])) {
            //$max_lengths = max(array_map('strlen', count($product['categories'])));
            //$columns = $this->keyboard_columns($max_lengths, count($product['categories']));
            $terms_r = $terms_d = array();
            $c = 1;
            foreach ($product['categories'] as $category) {
                $term = get_term(intval($category));
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

            $keyboard = array_merge($keyboard, $terms_r);
        }

        // Variations
        if (is_array($product['variations']) && count($product['variations'])) {
            $vi = 0;
            $attributes = wc_get_product_variation_attributes($product['product_variation_id']);
            foreach ($product['variations'] as $name => $variation) {
                if ($variation['is_variation'] != 1)
                    continue;
                $var_head = urldecode($name);
                if ($variation['is_variation'] == 1 && $variation['is_taxonomy'] == 1) {
                    $tax = get_taxonomy($var_head);
                    $var_head = $tax->labels->singular_name;
                }
                $keyboard[] = array(array(
                    'text' => '- ' . $var_head . ' -',
                    'callback_data' => 'product_variation_header_' . $vi
                ));
                $vi++;

                // is custom variation
                if ($variation['is_taxonomy'] == 0) {
                    $items = explode('|', $variation['value']);
                    $items = array_map('urldecode', array_map('trim', $items));
                    $terms_r = $terms_d = array();
                    $c = 1;
                    $max_lengths = max(array_map('strlen', $items));
                    $columns = $this->keyboard_columns($max_lengths, count($items));
                    foreach ($items as $item) {
                        if ($attributes) {
                            $attributes_ = array_keys($attributes);
                            if (in_array('attribute_' . $name, $attributes_)) {
                                $value = get_post_meta($product['product_variation_id'], 'attribute_' . $name, true);
                                if (!empty($value) && $value != $item)
                                    continue;
                            }
                        }
                        $terms_d[] = array(
                            'text' => $item,
                            'callback_data' => 'product_variation_' . $product['id'] . '_text_' . $var_head . '_' . $item
                        );
                        if ($c % $columns == 0) {
                            $terms_r[] = $terms_d;
                            $terms_d = array();
                        }
                        $c++;
                    }
                    if (count($terms_d))
                        $terms_r[] = $terms_d;
                    $keyboard = array_merge($keyboard, $terms_r);

                    // is taxonomy variation
                } elseif ($variation['is_taxonomy'] == 1) {
                    $terms = get_the_terms($product['id'], $variation['name']);
                    if ($terms) {
                        $temps = array();
                        foreach ($terms as $term)
                            $temps[] = $term->name;
                        $max_lengths = max(array_map('strlen', $temps));
                        $columns = $this->keyboard_columns($max_lengths, count($terms));
                        $terms_r = $terms_d = array();
                        $c = 1;
                        foreach ($terms as $term) {
                            $terms_d[] = array(
                                'text' => $term->name,
                                'callback_data' => 'product_variation_' . $product['id'] . '_tax_' . $term->term_id
                            );
                            if ($c % $columns == 0) {
                                $terms_r[] = $terms_d;
                                $terms_d = array();
                            }
                            $c++;
                        }
                        if (count($terms_d))
                            $terms_r[] = $terms_d;

                        $keyboard = array_merge($keyboard, $terms_r);
                    }
                }
            }
        }

        return $keyboard;
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
            $columns = 6;
        for ($i = 2; $i <= $columns; $i++)
            if ($count % $columns != 0 && $count % $i == 0 && $count != $i && $count / $i <= $columns) {
                $columns = $count / $i;
                break;
            }
        return $columns;
    }

    function send_products($products)
    {
        if (count($products['product'])) {
            $keyboard = $this->default_products_keyboard;
            $i = 1;
            $current_page = $this->user['page'];
            foreach ($products['product'] as $product) {
                $price = (!empty($product['sale_price']) ? $product['sale_price'] : $product['price']);
                $terms = get_the_terms($product['id'], 'product_type');
                if ($terms && $terms[0]->slug == 'variable') {
                    $price = $product['price'];
                }
                $price = !empty($price) ? strip_tags(html_entity_decode(wc_price($price))) : $price;
                $text = $product['title'] . "\n" . $price . "\n" . $product['excerpt'];
                $keyboard[0][0]['callback_data'] = 'product_detail_' . $product['id'];
                if ($products['max_num_pages'] > 1 && $i == count($products['product'])) {
                    $keyboard[1] = array();
                    if ($current_page < $products['max_num_pages'])
                        $keyboard[1][] = array('text' => $this->words['next_page'], 'callback_data' => 'page_next');
                    if ($current_page > 1)
                        $keyboard[1][] = array('text' => $this->words['prev_page'], 'callback_data' => 'page_prev');
                }
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');

                if ($product['image_path'] !== null)
                    $this->telegram->sendFile('sendPhoto', $product['image_path'], $text, $keyboards);
                else
                    $this->telegram->sendMessage($text, $keyboards);
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
                <a id="TabMB2" class="mb-tab nav-tab"><?php _e('Product', $this->plugin_key) ?></a>
                <a id="TabMB3" class="mb-tab nav-tab"><?php _e('Messages', $this->plugin_key) ?></a>
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
                            <th colspan="2"><?php _e('Display', $this->plugin_key) ?></th>
                        </tr>
                        <tr>
                            <td><?php _e('Display', $this->plugin_key) ?></td>
                            <td>
                                <label><input type="checkbox" value="1" name="weight_display" <?php checked($this->get_option('weight_display'),1) ?>><?php _e('Weight', $this->plugin_key) ?></label>,
                                <label><input type="checkbox" value="1" name="dimensions_display" <?php checked($this->get_option('dimensions_display'),1) ?>><?php _e('Dimensions', $this->plugin_key) ?></label>,
                                <label><input type="checkbox" value="1" name="attributes_display" <?php checked($this->get_option('attributes_display'),1) ?>><?php _e('Attributes', $this->plugin_key) ?></label>,
                                <label><input type="checkbox" value="1" name="rating_display" <?php checked($this->get_option('rating_display'),1) ?>><?php _e('Rating', $this->plugin_key) ?></label>
                                <label><input type="checkbox" value="1" name="gallery_display" <?php checked($this->get_option('gallery_display'),1) ?>><?php _e('Gallery Button', $this->plugin_key) ?></label>
                            </td>
                        </tr>
                    </table>
                </div>
                <div id="TabMB3Content" class="mb-tab-content hidden">
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
        }
        <?php

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

        $product_type_valid = array('simple', 'variable');
        $temp = $post;
        $per_page = $query['per_page'] == null ? $this->get_option('products_per_page') : $query['per_page'];
        $page = $this->user['page'];
        // $this->telegram->sendMessage(json_encode($this->user));

        $image_size = $this->get_option('image_size');
        $items = array($query['post_type'] => array());
        $args = array('post_type' => $query['post_type']);
        if (isset($query['p'])) {
            $args['p'] = $query['p'];
        } else {
            $args = array_merge($args, array(
                'posts_per_page' => intval($per_page),
                'paged' => intval($page),
                'order' => 'DESC',
                'orderby' => 'modified',
            ));
            $args['tax_query'] = array('relation' => 'AND');
            if ($query['post_type'] == 'product') {
                if ($query['category_id'] !== null)
                    $args['tax_query'][] = array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => intval($query['category_id'])
                    );
                $args['tax_query'][] = array(
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => $product_type_valid
                );

                // Meta Query
                $args['meta_query'] = array(
                    array(
                        'key' => '_stock_status',
                        'value' => 'instock',
                        'compare' => '=',
                    ),
                );
            }
        }
        $query_ = new WP_Query($args);
        $max_num_pages = $query_->max_num_pages;
        if (!$max_num_pages)
            $max_num_pages = 1;
        $items['max_num_pages'] = $max_num_pages;
        $items['parameter'] = array('category_id' => $query['category_id'], 'post_type' => $query['post_type']);

        if ($query_->have_posts()) {
            while ($query_->have_posts()) {
                $query_->the_post();
                $post_id = $product_id = get_the_ID();
                $image = $image_path = $file_name = null;
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
                $price = $regular_price = $variation = $product_variation_id = $average_rating = $galleries = $categories = $sale_price = $weight = $dimensions = $attributes = null;
                $content = get_the_content();
                $excerpt = get_the_excerpt();
                $title = get_the_title();
                $link = get_the_permalink();
                if ($query['post_type'] == 'product') {
                    $args = array(
                        'post_type' => 'product_variation',
                        'post_status' => 'publish',
                        'numberposts' => 1,
                        'orderby' => 'menu_order',
                        'order' => 'asc',
                        'post_parent' => $post_id
                    );
                    $variations = get_posts($args);
                    if ($variations)
                        $product_variation_id = $variations[0]->ID;

                    //$this->telegram->sendMessage();

                    $_product = new WC_Product($product_id);

                    $content = $_product->get_description();
                    $excerpt = empty($_product->get_short_description()) ? get_the_excerpt() : $_product->get_short_description();
                    $title = $_product->get_name();
                    $weight = $_product->get_weight();
                    $dimensions = $_product->get_dimensions();
                    $price = $_product->get_price();
                    $regular_price = $_product->get_regular_price();
                    $sale_price = $_product->get_sale_price();
                    $average_rating = $_product->get_average_rating();
                    // Check Sale Price Dates
                    if (!empty($_product->get_date_on_sale_from()) || !empty($_product->get_date_on_sale_to())) {
                        if ((!empty($_product->get_date_on_sale_from()) && strtotime($_product->get_date_on_sale_from()) > time()) ||
                            (!empty($_product->get_date_on_sale_to()) && strtotime($_product->get_date_on_sale_to()) < time()))
                            $sale_price = null;
                    }
                    // Get Product Attribute
                    $_attributes = array_keys($_product->get_attributes());
                    if (count($_attributes)) {
                        $attributes = array();
                        foreach ($_attributes as $key) {
                            $attributes[$key] = $_product->get_attribute($key);
                        }
                    }
                    $variation = get_post_meta($post_id, '_product_attributes', true);
                    $categories = $_product->get_category_ids();
                    $galleries = $_product->get_gallery_image_ids();
                }

                $items[$query['post_type']][] = array(
                    'id' => $post_id,
                    'title' => $title,
                    'content' => $content,
                    'excerpt' => $excerpt,
                    'link' => $link,
                    'image' => $image,
                    'image_path' => $image_path,
                    'price' => $price,
                    'regular_price' => $regular_price,
                    'sale_price' => $sale_price,
                    'weight' => $weight,
                    'dimensions' => $dimensions,
                    'attributes' => $attributes,
                    'variations' => $variation,
                    'categories' => $categories,
                    'galleries' => $galleries,
                    'average_rating' => $average_rating,
                    'product_variation_id' => $product_variation_id,
                    'test' => $_product->get_type(),
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
            $this->set_user($this->telegram_input['form']['id']);
    }

    function set_user($user_id = null)
    {
        global $wpdb;
        if ($user_id != null && is_numeric($user_id))
            return $this->user = $wpdb->get_row("SELECT * FROM {
        $this->db_table} WHERE user_id = '{$user_id}'", ARRAY_A);

        $from = $this->telegram_input['from'];
        if (isset($from['id'])) {
            $sql = "SELECT * FROM {$this->db_table} WHERE user_id = '{$from['id']}'";
            $user = $wpdb->get_row($sql, ARRAY_A);
            //$this->telegram->sendMessage(json_encode($user));
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

            //if ($result)
            $this->user = $wpdb->get_row($sql, ARRAY_A);
            // else
            //    return false;
        }
        return false;
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
        $select .= '<option value="full" ' . selected('full', $selected, false) . '>' . __('Full', $this->plugin_key) . '</option>';
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
}

new WooCommerceTelegramBot();