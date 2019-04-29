<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class WoocommerceWPTP extends WPTelegramPro
{
    protected $tabID = 'woocommerce-wptp-tab', $default_products_keyboard;
    public static $instance = null;
    
    public function __construct()
    {
        parent::__construct(true);
        
        add_filter('wptelegrampro_words', [$this, 'words']);
        add_filter('wptelegrampro_patterns_tags', [$this, 'patterns_tags']);
        add_filter('wptelegrampro_query_args', [$this, 'query_args']);
        add_filter('wptelegrampro_post_info', [$this, 'product_info'], 10, 4);
        add_filter('wptelegrampro_default_keyboard', [$this, 'default_keyboard'], 20);
        
        $this->default_products_keyboard = array(array(
            array('text' => __('Detail', $this->plugin_key), 'callback_data' => 'product_detail')
        ));
        
        add_filter('wptelegrampro_settings_tabs', [$this, 'settings_tab'], 30);
        add_action('wptelegrampro_settings_content', [$this, 'settings_content']);
        add_action('wptelegrampro_inline_keyboard_response', array($this, 'inline_keyboard'));
        add_action('wptelegrampro_keyboard_response', array($this, 'keyboard'));
        
        add_action('init', array($this, 'cart_init'), 99999);
        add_action('woocommerce_payment_complete', array($this, 'woocommerce_payment_complete'));
        //add_action('query_wptb', array($this, 'woocommerce_before_query'));
        add_filter('wptelegrampro_default_commands', [$this, 'default_commands'], 20);
        
        $this->words = apply_filters('wptelegrampro_words', $this->words);
    }
    
    function default_commands($commands)
    {
        $commands = array_merge($commands,
            array(
                'products' => __('Products', $this->plugin_key),
                'product_categories' => __('Product Categories List', $this->plugin_key),
                'cart' => __('Cart', $this->plugin_key)
            ));
        return $commands;
    }
    
    function default_keyboard($keyboard)
    {
        $this->words = apply_filters('wptelegrampro_words', $this->words);
        $new_keyboard = array(
            $this->words['products'],
            $this->words['product_categories'],
            $this->words['cart']
        );
        $keyboard[] = is_rtl() ? array_reverse($new_keyboard) : $new_keyboard;
        return $keyboard;
    }
    
    function words($words)
    {
        $new_words = array(
            'products' => __('Products', $this->plugin_key),
            'product_categories' => __('Product Categories', $this->plugin_key),
            'cart' => __('Cart', $this->plugin_key),
            'checkout' => __('Checkout', $this->plugin_key),
            'cart_empty_message' => __('Your cart is empty.', $this->plugin_key),
            'confirm_empty_cart' => __('Empty Cart?', $this->plugin_key),
            'cart_emptied' => __('Cart has been empty.', $this->plugin_key),
            'refresh_cart' => __('Refresh Cart', $this->plugin_key),
            'instock' => __('In stock', $this->plugin_key),
            'outofstock' => __('Out of stock', $this->plugin_key),
        );
        $words = array_merge($words, $new_words);
        
        return $words;
    }
    
    function query_args($args, $query)
    {
        $product_type_valid = array('simple', 'variable');
        
        if (!isset($query['p']) && $query['post_type'] == 'product') {
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
            
            // If in Stock
            $args['meta_query'] = array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '=',
                ),
            );
        }
        
        return $args;
    }
    
    function product_info($items, $product_id, $query, $c)
    {
        if (!is_array($query['post_type']) && $query['post_type'] == 'product' && $this->check_plugin_active('woocommerce')) {
            $product_type = 'simple';
            $args = array(
                'post_type' => 'product_variation',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'menu_order',
                'order' => 'asc',
                'post_parent' => $product_id
            );
            $variations = get_posts($args);
            $product_variation_id = null;
            if ($variations)
                $product_variation_id = $variations[0]->ID;
            
            $_product = new WC_Product($product_id);
            $product_type_ = get_the_terms($product_id, 'product_type');
            if ($product_type_)
                $product_type = $product_type_[0]->slug;
            
            $items[$query['post_type']][$c]['content'] = $_product->get_description();
            $items[$query['post_type']][$c]['excerpt'] = empty($_product->get_short_description()) ? get_the_excerpt() : $_product->get_short_description();
            $items[$query['post_type']][$c]['title'] = $_product->get_name();
            $dimensions = wc_format_dimensions($_product->get_dimensions(false));
            $price = $_product->get_price();
            $regularprice = $_product->get_regular_price();
            $saleprice = $_product->get_sale_price();
            $average_rating = $_product->get_average_rating();
            // Check Sale Price Dates
            if (!empty($_product->get_date_on_sale_from()) || !empty($_product->get_date_on_sale_to())) {
                if ((!empty($_product->get_date_on_sale_from()) && strtotime($_product->get_date_on_sale_from()) > time()) ||
                    (!empty($_product->get_date_on_sale_to()) && strtotime($_product->get_date_on_sale_to()) < time()))
                    $saleprice = null;
            }
            // Get Product Attribute
            $_attributes = array_keys($_product->get_attributes());
            $attributes = array();
            if (count($_attributes)) {
                foreach ($_attributes as $key) {
                    $attributes[$key] = $_product->get_attribute($key);
                }
            }
            $variation = get_post_meta($product_id, '_product_attributes', true);
            $categories = $_product->get_category_ids();
            $galleries = $_product->get_gallery_image_ids();
            
            $items[$query['post_type']][$c]['tags'] = $this->get_taxonomy_terms('product_tag', $product_id);
            $items[$query['post_type']][$c]['categories'] = $this->get_taxonomy_terms('product_cat', $product_id);
            
            $product_args = array(
                'slug' => $_product->get_slug(),
                'price' => $price,
                'regularprice' => $regularprice,
                'saleprice' => $saleprice,
                'weight' => $_product->get_weight(),
                'width' => $_product->get_width(),
                'height' => $_product->get_height(),
                'length' => $_product->get_length(),
                'dimensions' => $dimensions,
                'sku' => $_product->get_sku(),
                'stock' => $_product->get_stock_quantity(),
                'stock-status' => $_product->get_stock_status(),
                'downloadable' => $_product->get_downloadable(),
                'virtual' => $_product->get_virtual(),
                'sold-individually' => $_product->get_sold_individually(),
                'tax-status' => $_product->get_tax_status(),
                'tax-class' => $_product->get_tax_class(),
                'back-orders' => $_product->get_backorders(),
                'featured' => $_product->get_featured(),
                'visibility' => $_product->get_catalog_visibility(),
                'attributes' => $attributes,
                'variations' => $variation,
                'categories_ids' => $categories,
                'galleries' => $galleries,
                'average_rating' => $average_rating,
                'product_variation_id' => $product_variation_id,
                'product_type' => $product_type
            );
            
            $items[$query['post_type']][$c] = array_merge($items[$query['post_type']][$c], $product_args);
        }
        return $items;
    }
    
    function patterns_tags($tags)
    {
        $tags['WooCommerce'] = array(
            'title' => __('WooCommerce Tags', $this->plugin_key),
            'plugin' => 'woocommerce',
            'tags' => array(
                'price' => __('The price of this product', $this->plugin_key),
                'regularprice' => __('The regular price of this product', $this->plugin_key),
                'saleprice' => __('The sale price of this product', $this->plugin_key),
                'width' => __('The width of this product', $this->plugin_key),
                'length' => __('The length of this product', $this->plugin_key),
                'height' => __('The height of this product', $this->plugin_key),
                'weight' => __('The weight of this product', $this->plugin_key),
                'dimensions' => __('The dimensions of this product', $this->plugin_key),
                'average_rating' => __('The average rating of this product', $this->plugin_key),
                'sku' => __('The SKU (Stock Keeping Unit) of this product', $this->plugin_key),
                'downloadable' => __('Is this product downloadable? (Yes or No)', $this->plugin_key),
                'virtual' => __('Is this product virtual? (Yes or No)', $this->plugin_key),
                'sold-individually' => __('Is this product sold individually? (Yes or No)', $this->plugin_key),
                'tax-status' => __('The tax status of this product', $this->plugin_key),
                'tax-class' => __('The tax class of this product', $this->plugin_key),
                'stock' => __('The stock amount of this product', $this->plugin_key),
                'stock-status' => __('The stock status of this product', $this->plugin_key),
                'back-orders' => __('Whether or not backorders allowed?', $this->plugin_key),
                'featured' => __('Is this a featured product? (Yes or No)', $this->plugin_key),
                'visibility' => __('Is this product visible? (Yes or No)', $this->plugin_key)
            )
        );
        return $tags;
    }
    
    function woocommerce_before_query($post_excerpt)
    {
        add_filter('woocommerce_short_description', array($this, 'woocommerce_short_description'), 10, 1);
    }
    
    function keyboard($user_text)
    {
        $words = $this->words;
        $this->words = apply_filters('wptelegrampro_words', $this->words);
        //$this->telegram->sendMessage(serialize($this->words));
        if ($user_text == '/products' || $user_text == $words['products']) {
            $this->update_user(array('page' => 1));
            $this->update_user_meta('product_category_id', null);
            $args = array(
                'post_type' => 'product',
                'per_page' => $this->get_option('products_per_page', 1),
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $this->get_option('exclude_categories'),
                        'operator' => 'NOT IN',
                    )
                )
            );
            
            $products = $this->query($args);
            //$this->telegram->sendMessage(serialize($products));
            $this->send_products($products);
            
        } elseif ($user_text == '/product_categories' || $user_text == $words['product_categories']) {
            $product_category = $this->get_tax_keyboard('product_category', 'product_cat', 'count', $this->get_option('exclude_display_categories'));
            $keyboard = $this->telegram->keyboard($product_category, 'inline_keyboard');
            $this->telegram->sendMessage($words['product_categories'] . ":", $keyboard);
            
        } elseif ($user_text == '/cart' || $user_text == $words['cart']) {
            $this->cart();
        }
    }
    
    function inline_keyboard($data)
    {
        $this->words = apply_filters('wptelegrampro_words', $this->words);
        $button_data = $data['data'];
        
        if ($this->button_data_check($button_data, 'product_variation_back')) {
            $button_data = explode('_', $button_data);
            $product = $this->query(array('p' => $button_data['3'], 'post_type' => 'product'));
            $keyboard = $this->product_keyboard($product, $button_data['4']);
            $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
            $this->telegram->editMessageReplyMarkup($keyboards, $button_data['4']);
            
        } elseif ($this->button_data_check($button_data, 'product_variation_header')) {
            $button_data = explode('_', $button_data);
            $this->telegram->answerCallbackQuery($button_data['5']);
            $product = $this->query(array('p' => $button_data['3'], 'post_type' => 'product'));
            $this->product_keyboard_variations($product, $button_data['5'], $button_data['4']);
            
        } elseif ($this->button_data_check($button_data, 'select_product_variation')) {
            $button_data_ = explode('||', $button_data);
            $button_data = explode('_', $button_data_[0]);
            $taxonomy = isset($button_data_[1]) ? $button_data_[1] : '';
            $product = $this->query(array('p' => $button_data['3'], 'post_type' => 'product'));
            $this->select_product_variation($product, $button_data['5'], $button_data['6'], $button_data['7'], $button_data['4'], $taxonomy);
            
        } elseif ($this->button_data_check($button_data, 'image_galleries')) {
            $image_send_mode = apply_filters('wptelegrampro_image_send_mode', 'image_path');
            
            $product_id = intval(end(explode('_', $button_data)));
            if (get_post_status($product_id) === 'publish') {
                $image_size = $this->get_option('image_size');
                $this->telegram->answerCallbackQuery(__('Galleries', $this->plugin_key) . ': ' . get_the_title($product_id));
                $_product = new WC_Product($product_id);
                $galleries = $_product->get_gallery_image_ids();
                if (is_array($galleries) && count($galleries)) {
                    $keyboards = null;
                    $i = 1;
                    foreach ($galleries as $image_id) {
                        $meta_data = wp_get_attachment_metadata($image_id);
                        if (is_array($meta_data)) {
                            if ($image_send_mode === 'image_path') {
                                $upload_dir = wp_upload_dir();
                                $image_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $meta_data['file'];
                                if ($image_size != 'full' && isset($meta_data['sizes'][$image_size])) {
                                    $file_name = pathinfo($image_path, PATHINFO_BASENAME);
                                    $image_path = str_replace($file_name, $meta_data['sizes'][$image_size]['file'], $image_path);
                                }
                            } else {
                                $image_path = wp_get_attachment_image_src($image_id, $image_size);
                                $image_path = $image_path[0];
                            }
                            
                            if ($i == count($galleries)) {
                                $keyboard = array(array(
                                    array('text' => __('Back to Product', $this->plugin_key), 'callback_data' => 'product_detail_' . $product_id)
                                ));
                                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                            }
                            $this->telegram->sendFile('sendPhoto', $image_path, get_the_title($image_id), $keyboards);
                            $i++;
                        }
                    }
                }
            } else {
                $this->telegram->answerCallbackQuery(__('The product does not exist', $this->plugin_key));
            }
            
        } elseif ($this->button_data_check($button_data, 'add_to_cart')) {
            $button_data = explode('_', $button_data);
            if (get_post_status($button_data['3']) === 'publish') {
                $in_cart = $this->check_cart($button_data['3']);
                $can_to_cart = $this->can_to_cart($button_data['3']);
                $can_to_cart_ = $this->can_to_cart($button_data['3'], true);
                $alert = false;
                if ($in_cart)
                    $message = __('Remove from Cart:', $this->plugin_key) . ' ' . get_the_title($button_data['3']);
                else
                    if ($can_to_cart)
                        $message = __('Add to Cart:', $this->plugin_key) . ' ' . get_the_title($button_data['3']);
                    else {
                        $message = __('Please select product variations:', $this->plugin_key) . ' ' . $can_to_cart_;
                        $alert = true;
                    }
                $this->telegram->answerCallbackQuery($message, null, $alert);
                
                // Add or Remove form Cart
                if ($in_cart == true || $can_to_cart)
                    $this->add_to_cart($button_data['3'], !$in_cart);
                
                $product = $this->query(array('p' => $button_data['3'], 'post_type' => 'product'));
                $keyboard = $this->product_keyboard($product, $button_data['4']);
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                $this->telegram->editMessageReplyMarkup($keyboards, $button_data['4']);
            } else {
                $this->telegram->answerCallbackQuery(__('The product does not exist', $this->plugin_key));
            }
            
        } elseif ($this->button_data_check($button_data, 'product_detail')) {
            $product_id = intval(end(explode('_', $button_data)));
            if (get_post_status($product_id) === 'publish') {
                $this->telegram->answerCallbackQuery(__('Product', $this->plugin_key) . ': ' . get_the_title($product_id));
                $product = $this->query(array('p' => $product_id, 'post_type' => 'product'));
                $this->send_product($product);
            } else {
                $this->telegram->answerCallbackQuery(__('The product does not exist', $this->plugin_key));
            }
            
        } elseif ($this->button_data_check($button_data, 'product_page_')) {
            $current_page = intval($this->user['page']) == 0 ? 1 : intval($this->user['page']);
            if ($button_data == 'product_page_next')
                $current_page++;
            else
                $current_page--;
            $this->update_user(array('page' => $current_page));
            $this->telegram->answerCallbackQuery(__('Page') . ': ' . $current_page);
            $args = array(
                'category_id' => $this->get_user_meta('product_category_id'),
                'post_type' => 'product',
                'per_page' => $this->get_option('products_per_page', 1),
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $this->get_option('exclude_categories'),
                        'operator' => 'NOT IN',
                    )
                )
            );
            $products = $this->query($args);
            $this->send_products($products);
            
        } elseif ($this->button_data_check($button_data, 'product_category')) {
            $this->update_user(array('page' => 1));
            $product_category_id = intval(end(explode('_', $button_data)));
            $this->update_user_meta('product_category_id', $product_category_id);
            $product_category = get_term($product_category_id, 'product_cat');
            if ($product_category) {
                $this->telegram->answerCallbackQuery(__('Category') . ': ' . $product_category->name);
                $products = $this->query(array('category_id' => $product_category_id, 'per_page' => $this->get_option('products_per_page', 1), 'post_type' => 'product'));
                $this->send_products($products);
            } else {
                $this->telegram->answerCallbackQuery(__('Product Category Invalid!', $this->plugin_key));
            }
            
        } elseif ($this->button_data_check($button_data, 'confirm_empty_cart')) {
            $message_id = intval(end(explode('_', $button_data)));
            $this->telegram->answerCallbackQuery($this->words['confirm_empty_cart']);
            $keyboard = array(array(
                array(
                    'text' => $this->words['yes'],
                    'callback_data' => 'empty_cart_yes_' . $message_id
                ),
                array(
                    'text' => $this->words['no'],
                    'callback_data' => 'empty_cart_no_' . $message_id
                )
            ));
            $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
            $this->telegram->editMessageReplyMarkup($keyboards, $message_id);
            
        } elseif ($this->button_data_check($button_data, 'empty_cart_no')) {
            $message_id = intval(end(explode('_', $button_data)));
            $this->cart($message_id);
            
        } elseif ($this->button_data_check($button_data, 'empty_cart_yes')) {
            $message_id = intval(end(explode('_', $button_data)));
            $this->telegram->answerCallbackQuery($this->words['cart_emptied']);
            $this->telegram->editMessageText($this->words['cart_emptied'], $message_id);
            $this->update_user(array('cart' => serialize(array())));
            
        } elseif ($this->button_data_check($button_data, 'refresh_cart')) {
            $message_id = intval(end(explode('_', $button_data)));
            $this->telegram->answerCallbackQuery($this->words['refresh_cart']);
            $this->cart($message_id, $refresh = true);
        }
    }
    
    function settings_tab($tabs)
    {
        $tabs[$this->tabID] = __('WooCommerce', $this->plugin_key);
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
                        <label for="products_per_page"><?php _e('Products Per Page', $this->plugin_key) ?></label>
                    </td>
                    <td><input type="number" name="products_per_page" id="products_per_page"
                               value="<?php echo $this->get_option('products_per_page', $this->per_page) ?>"
                               class="small-text ltr" min="1"></td>
                </tr>
                <tr>
                    <td>
                        <label for="exclude_categories"><?php _e('Exclude Categories', $this->plugin_key) ?></label>
                    </td>
                    <td><?php echo $this->dropdown_categories('exclude_categories[]', 'product_cat', $this->get_option('exclude_categories')); ?></td>
                </tr>
                <tr>
                    <td>
                        <label for="exclude_categories"><?php _e('Exclude Display Categories', $this->plugin_key) ?></label>
                    </td>
                    <td><?php echo $this->dropdown_categories('exclude_display_categories[]', 'product_cat', $this->get_option('exclude_display_categories')); ?></td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e('Empty the cart', $this->plugin_key) ?></th>
                </tr>
                <tr>
                    <td>
                        <label for="empty_cart_after_wc_redirect"><?php _e('After Redirect to Cart Page', $this->plugin_key) ?>                                </label>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="empty_cart_after_wc_redirect"
                                      name="empty_cart_after_wc_redirect" <?php checked($this->get_option('empty_cart_after_wc_redirect'), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="empty_cart_after_wc_payment_complete"><?php _e('After Payment Complete', $this->plugin_key) ?>                                </label>
                    </td>
                    <td>
                        <label><input type="checkbox" value="1" id="empty_cart_after_wc_payment_complete"
                                      name="empty_cart_after_wc_payment_complete" <?php checked($this->get_option('empty_cart_after_wc_payment_complete'), 1) ?>> <?php _e('Active', $this->plugin_key) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><?php _e('Display', $this->plugin_key) ?></th>
                </tr>
                <tr>
                    <td><?php _e('Meta Data', $this->plugin_key) ?></td>
                    <td>
                        <label><input type="checkbox" value="1"
                                      name="weight_display" <?php checked($this->get_option('weight_display'), 1) ?>><?php _e('Weight', $this->plugin_key) ?>
                        </label>
                        <label><input type="checkbox" value="1"
                                      name="dimensions_display" <?php checked($this->get_option('dimensions_display'), 1) ?>><?php _e('Dimensions', $this->plugin_key) ?>
                        </label>
                        <label><input type="checkbox" value="1"
                                      name="attributes_display" <?php checked($this->get_option('attributes_display'), 1) ?>><?php _e('Attributes', $this->plugin_key) ?>
                        </label>
                        <label><input type="checkbox" value="1"
                                      name="rating_display" <?php checked($this->get_option('rating_display'), 1) ?>><?php _e('Rating', $this->plugin_key) ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td><?php _e('Keyboard', $this->plugin_key) ?></td>
                    <td>
                        <label><input type="checkbox" value="1"
                                      name="gallery_keyboard" <?php checked($this->get_option('gallery_keyboard'), 1) ?>><?php _e('Gallery Button', $this->plugin_key) ?>
                        </label>
                        <label><input type="checkbox" value="1"
                                      name="category_keyboard" <?php checked($this->get_option('category_keyboard'), 1) ?>><?php _e('Category Buttons', $this->plugin_key) ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    function product_keyboard($product, $message_id)
    {
        /*$terms = get_the_terms($product['ID'], 'product_type');
        if ($terms) {
            $product_type = $terms[0]->slug;
        }*/
        
        $in_cart = $this->check_cart($product['ID']);
        $keyboard = array(array(
            // array('text' => __('Detail', $this->plugin_key), 'callback_data' => 'product_detail_' . $product['ID']),
            array('text' => '🔗️', 'url' => $product['link']),
            array('text' => ($in_cart ? '- ' : '+ ') . '🛒', 'callback_data' => 'add_to_cart_' . $product['ID'] . '_' . $message_id),
        ));
        
        // Gallery Emoji Button
        if ($this->get_option('gallery_keyboard') == 1 && is_array($product['galleries']) && count($product['galleries'])) {
            $keyboard[0][] = array('text' => '🖼️', 'callback_data' => 'image_galleries_' . $product['ID']);
        }
        
        // Variations
        if (is_array($product['variations']) && count($product['variations'])) {
            $terms_r = $terms_d = $temps = array();
            foreach ($product['variations'] as $name => $variation) {
                if ($variation['is_variation'] != 1)
                    continue;
                $var_head = urldecode($name);
                if ($variation['is_taxonomy'] == 1) {
                    $tax = get_taxonomy($var_head);
                    $temps[] = $tax->labels->singular_name;
                } else {
                    $temps[] = $var_head;
                }
            }
            
            $max_lengths = max(array_map('mb_strlen', $temps));
            $columns = $this->keyboard_columns($max_lengths, count($temps));
            $c = 1;
            foreach ($temps as $temp) {
                $in_cart = $this->check_cart($product['ID'], $temp);
                if ($in_cart === false)
                    $this->add_to_cart($product['ID'], null, $temp, '0');
                
                $terms_d[] = array(
                    'text' => ($in_cart === false || $in_cart == '0' ? '' : '✔️ ') . ucwords($temp),
                    'callback_data' => 'product_variation_header_' . $product['ID'] . '_' . $message_id . '_' . $temp
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
        
        // Category Button
        if ($this->get_option('category_keyboard') == 1 && is_array($product['categories_ids']) && count($product['categories_ids'])) {
            //$max_lengths = max(array_map('strlen', count($product['categories_ids'])));
            //$columns = $this->keyboard_columns($max_lengths, count($product['categories_ids']));
            $terms_r = $terms_d = array();
            $c = 1;
            $exclude = $this->get_option('exclude_display_categories');
            foreach ($product['categories_ids'] as $category) {
                if (in_array(intval($category), $exclude))
                    continue;
                $term = get_term(intval($category));
                $terms_d[] = array(
                    'text' => '📁 ' . $term->name,
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
        
        return $keyboard;
    }
    
    
    function select_product_variation($product, $variation_type, $variation_name, $variation_value, $message_id, $taxonomy)
    {
        if ($variation_type == 'text')
            $this->telegram->answerCallbackQuery(__('Select', $this->plugin_key) . ' ' . $variation_name . ': ' . $variation_value);
        elseif ($variation_type == 'tax' && !empty($taxonomy)) {
            $term = get_term_by('slug', $variation_value, $taxonomy);
            if ($term)
                $this->telegram->answerCallbackQuery(__('Select', $this->plugin_key) . ' ' . $variation_name . ': ' . $term->name);
        }
        $this->add_to_cart($product['ID'], null, $variation_name, $variation_value);
        //$this->product_keyboard_variations($product, $variation_name, $message_id);
        $keyboard = $this->product_keyboard($product, $message_id);
        $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
        $this->telegram->editMessageReplyMarkup($keyboards, $message_id);
    }
    
    function product_keyboard_variations($product, $variation_name, $message_id)
    {
        $keyboard = array();
        $attributes = wc_get_product_variation_attributes($product['product_variation_id']);
        if (is_array($product['variations']) && count($product['variations'])) {
            foreach ($product['variations'] as $name => $variation) {
                if ($variation['is_variation'] != 1)
                    continue;
                $var_head = urldecode($name);
                if ($variation['is_taxonomy'] == 1) {
                    $tax = get_taxonomy($var_head);
                    $var_head = $tax->labels->singular_name;
                }
                
                if ($var_head != $variation_name)
                    continue;
                
                $in_cart = $this->check_cart($product['ID'], $var_head);
                
                $c = 1;
                // is custom variation
                if ($variation['is_taxonomy'] == 0 && !empty($variation['value'])) {
                    $items = explode('|', $variation['value']);
                    $items = array_map('urldecode', array_map('trim', $items));
                    $terms_r = $terms_d = array();
                    $max_lengths = max(array_map('mb_strlen', $items));
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
                            'text' => ($in_cart === $item ? '✔️ ' : '') . $item,
                            'callback_data' => 'select_product_variation_' . $product['ID'] . '_' . $message_id . '_text_' . $var_head . '_' . $item
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
                    $terms = get_the_terms($product['ID'], $variation['name']);
                    if ($terms) {
                        $temps = array();
                        foreach ($terms as $term)
                            $temps[] = $term->name;
                        $max_lengths = max(array_map('mb_strlen', $temps));
                        $columns = $this->keyboard_columns($max_lengths, count($terms));
                        $terms_r = $terms_d = array();
                        /*if ($first) {
                            $terms_d[] = array(
                                'text' => '🔙',
                                'callback_data' => 'product_variation_back_' . $product['ID'] . '_' . $message_id
                            );
                            $first = false;
                        }*/
                        foreach ($terms as $term) {
                            $terms_d[] = array(
                                'text' => ($in_cart == $term->slug ? '✔️ ' : '') . $term->name,
                                'callback_data' => 'select_product_variation_' . $product['ID'] . '_' . $message_id . '_tax_' . $var_head . '_' . $term->slug . '||' . $variation['name']
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
                
                break;
            }
        }
        
        if (count($keyboard)) {
            $keyboard[][] = array(
                'text' => '🔙',
                'callback_data' => 'product_variation_back_' . $product['ID'] . '_' . $message_id
            );
            $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
            $this->telegram->editMessageReplyMarkup($keyboards, $message_id);
        }
    }
    
    function add_to_cart($product_id, $add = null, $variation_key = null, $variation_value = null)
    {
        $cart = $this->get_cart();
        
        if (!isset($cart[$product_id]))
            $cart[$product_id] = array();
        
        if (is_bool($add) === true)
            $cart[$product_id]['added'] = $add;
        
        if (!empty($variation_key) && $variation_value != null)
            $cart[$product_id]['variations'][$variation_key] = $variation_value;
        
        $this->update_user(array('cart' => serialize($cart)));
    }
    
    function get_cart()
    {
        $cart = $this->user['cart'];
        if (empty($cart))
            $cart = array();
        else
            $cart = unserialize($cart);
        return $cart;
    }
    
    function can_to_cart($product_id, $return_var = false)
    {
        $cart = $this->get_cart();
        $variations = array();
        $product = $this->query(array('p' => $product_id, 'post_type' => 'product'));
        if ($product) {
            if ($product['product_type'] == 'variable') {
                if (isset($cart[$product_id]['variations'])) {
                    $values = array_values($cart[$product_id]['variations']);
                    if (!$return_var && !in_array('0', $values)) {
                        return true;
                    } elseif ($return_var) {
                        foreach ($cart[$product_id]['variations'] as $variation => $val)
                            if ($val == '0')
                                $variations[] = $variation;
                        return implode(', ', $variations);
                    }
                }
            } else
                return true;
        }
        return false;
    }
    
    function check_cart($product_id, $variation_key = null)
    {
        $cart = $this->get_cart();
        if (isset($cart[$product_id])) {
            if ($variation_key !== null) {
                if (isset($cart[$product_id]['variations'][$variation_key]))
                    return $cart[$product_id]['variations'][$variation_key];
            } else {
                if (isset($cart[$product_id]['added']) && $cart[$product_id]['added'] == true)
                    return true;
            }
        }
        return false;
    }
    
    function send_products($products)
    {
        if (count($products['product'])) {
            $image_send_mode = apply_filters('wptelegrampro_image_send_mode', 'image_path');
            
            $this->words = apply_filters('wptelegrampro_words', $this->words);
            $keyboard = $this->default_products_keyboard;
            $i = 1;
            $current_page = $this->user['page'];
            foreach ($products['product'] as $product) {
                $price = $this->product_price($product);
                $text = $product['title'] . "\n" . $price . "\n" . $product['excerpt'];
                $keyboard[0][0]['callback_data'] = 'product_detail_' . $product['ID'];
                if ($products['max_num_pages'] > 1 && $i == count($products['product'])) {
                    $keyboard[1] = array();
                    if ($current_page > 1)
                        $keyboard[1][] = array('text' => $this->words['prev_page'], 'callback_data' => 'product_page_prev');
                    if ($current_page < $products['max_num_pages'])
                        $keyboard[1][] = array('text' => $this->words['next_page'], 'callback_data' => 'product_page_next');
                    if (is_rtl())
                        $keyboard[1] = array_reverse($keyboard[1]);
                }
                $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
                if ($product[$image_send_mode] !== null) {
                    $this->telegram->sendFile('sendPhoto', $product[$image_send_mode], $text, $keyboards);
                } else
                    $this->telegram->sendMessage($text, $keyboards);
                $i++;
            }
        } else {
            $this->telegram->sendMessage(__('Your request without result!', $this->plugin_key));
        }
    }
    
    function send_product($product)
    {
        $image_send_mode = apply_filters('wptelegrampro_image_send_mode', 'image_path');
        $price = $this->product_price($product);
        $add_info = '';
        $metas = array();
        
        // Weight
        if ($this->get_option('weight_display') == 1 && !empty($product['weight']))
            $metas[] = __('Weight', $this->plugin_key) . ': ' . $product['weight'] . ' ' . get_option('woocommerce_weight_unit');
        
        // Dimensions
        if ($this->get_option('dimensions_display') == 1 && !empty($product['dimensions']) && $product['dimensions'] != __('N/A', 'woocommerce'))
            $metas[] = __('Dimensions', $this->plugin_key) . ': ' . $product['dimensions'];
        
        // Attribute
        if ($this->get_option('attributes_display') == 1 && is_array($product['variations']) && count($product['variations'])) {
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
                    $terms = get_the_terms($product['ID'], $variation['name']);
                    foreach ($terms as $term)
                        $items[] = $term->name;
                }
                $items = implode(', ', $items);
                $metas[] = $var_head . ': ' . $items;
            }
        }
        
        // http://www.i2symbol.com/symbols/stars
        if ($this->get_option('rating_display') == 1 && !empty($product['average_rating']) && intval($product['average_rating']) > 0) {
            $star = '';
            for ($i = 1; $i <= intval($product['average_rating']); $i++)
                $star .= "☆"; // star ✰
            $metas[] = $star;
        }
        
        if (count($metas))
            $add_info = "\n" . implode(' / ', $metas);
        
        $text = $product['title'] . "\n" . $price . $add_info . "\n" . $product['content'];
        
        if ($product[$image_send_mode] !== null)
            $this->telegram->sendFile('sendPhoto', $product[$image_send_mode], $text);
        else
            $this->telegram->sendMessage($text);
        
        // Keyboard
        $message_id = $this->telegram->get_last_result()['result']['message_id'];
        $keyboard = $this->product_keyboard($product, $message_id);
        $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
        $this->telegram->editMessageReplyMarkup($keyboards, $message_id);
    }
    
    function product_price($product)
    {
        $price = (!empty($product['saleprice']) ? $product['saleprice'] : $product['price']);
        if ($product['product_type'] == 'variable')
            $price = $product['price'];
        $price = !empty($price) ? strip_tags(html_entity_decode(wc_price($price))) : $price;
        return $price;
    }
    
    
    function cart($message_id = null, $refresh = false)
    {
        $cart = $this->get_cart();
        $products = $result = '';
        $c = 0;
        $columns = 6;
        $keyboard = $product_d = array();
        if (count($cart)) {
            foreach ($cart as $product_id => $item) {
                if (isset($item['added']) && $item['added'] == true) {
                    $c++;
                    $product = $this->query(array('p' => $product_id, 'post_type' => 'product'));
                    $price = $this->product_price($product);
                    $products .= $c . '- ' . $product['title'] . ' , ' . $price . "\n";
                    $product_d[] = array(
                        'text' => $c,
                        'callback_data' => 'product_detail_' . $product_id
                    );
                    if ($c % $columns == 0) {
                        $keyboard[] = $product_d;
                        $product_d = array();
                    }
                }
            }
            
            if (!empty($products)) {
                $result = $this->words['cart'] . "\n" . $products;
            } else
                $result = $this->words['cart_empty_message'];
        } else
            $result = $this->words['cart_empty_message'];
        
        if (count($product_d))
            $keyboard[] = $product_d;
        
        if ($message_id == null)
            $this->telegram->sendMessage($result);
        elseif ($message_id != null && $refresh)
            $this->telegram->editMessageText($result, $message_id);
        
        if (count($keyboard)) {
            if ($message_id == null)
                $message_id = $this->telegram->get_last_result()['result']['message_id'];
            
            $keyboard[] = array(
                array(
                    'text' => '🚮',
                    'callback_data' => 'confirm_empty_cart_' . $message_id
                ),
                array(
                    'text' => '🔄',
                    'callback_data' => 'refresh_cart_' . $message_id
                ),
                array(
                    'text' => '🛒',
                    'url' => $this->cart_url()
                ),
            );
            $keyboards = $this->telegram->keyboard($keyboard, 'inline_keyboard');
            $this->telegram->editMessageReplyMarkup($keyboards, $message_id);
        }
    }
    
    function cart_url()
    {
        $url = wc_get_cart_url();
        $url .= strpos($url, '?') === false ? '?' : '&';
        $url .= 'wptpwc=' . $this->user_field('rand_id');
        return $url;
    }
    
    function cart_init()
    {
        if (!is_ajax() && isset($_GET['wptpwc']) && is_numeric($_GET['wptpwc']) && function_exists('wc')) {
            $user = $this->set_user(array('rand_id' => $_GET['wptpwc']));
            if ($user === null) return;
            if (!is_user_logged_in())
                setcookie('wptpwc_user_id', $_GET['wptpwc'], time() + (60 * 60 * 12 * 7));
            elseif (empty($user['wp_id'])) {
                $wp_id = get_current_user_id();
                $this->update_user(array('wp_id' => $wp_id));
                $this->telegram->sendMessage(__('Welcome, your user is successfully connected to the store.', $this->plugin_key), null, $user['user_id']);
            }
            $cart_item_id = false;
            $cart = $this->get_cart();
            $wc_cart = WC()->cart->get_cart();
            if (count($cart)) {
                foreach ($cart as $product_id => $item) {
                    $found = false;
                    if (!WC()->cart->is_empty())
                        foreach ($wc_cart as $cart_item_key => $values) {
                            $_product = $values['data'];
                            if ($_product->id == $product_id) {
                                $found = true;
                                $cart_item_id = $cart_item_key;
                            }
                        }
                    if (isset($item['added']) && $item['added'] == true) {
                        if (isset($cart[$product_id]['variations'])) {
                            if ($found)
                                WC()->cart->remove_cart_item($cart_item_id);
                            $product_variation = array();
                            $product = $this->query(array('p' => $product_id, 'post_type' => 'product'));
                            $variation_id = $product['product_variation_id'];
                            if (is_array($product['variations']) && count($product['variations'])) {
                                foreach ($product['variations'] as $name => $variation) {
                                    if ($variation['is_variation'] != 1)
                                        continue;
                                    $var_name = urldecode($name);
                                    if ($variation['is_taxonomy'] == 1) {
                                        $tax = get_taxonomy($var_name);
                                        $var_name = $tax->labels->singular_name;
                                    }
                                    if (isset($cart[$product_id]['variations'][$var_name]))
                                        $product_variation['attribute_' . $name] = $cart[$product_id]['variations'][$var_name];
                                }
                                WC()->cart->add_to_cart($product_id, 1, $variation_id, $product_variation);
                            }
                        } elseif (!$found) {
                            WC()->cart->add_to_cart($product_id);
                        }
                    } elseif (isset($item['added']) && $item['added'] == false && !empty($cart_item_id) && $found) {
                        WC()->cart->remove_cart_item($cart_item_id);
                    }
                }
                
                if (isset($this->options['empty_cart_after_wc_redirect']))
                    $this->update_user(array('cart' => serialize(array())));
            }
            wp_redirect(wc_get_cart_url());
        }
    }
    
    function woocommerce_payment_complete($order_id)
    {
        $order = wc_get_order($order_id);
        $user = $order->get_user();
        if ($user) {
            $user = $this->set_user(array('wp_id' => $user->ID));
            if ($user === null)
                return;
            if (isset($this->options['empty_cart_after_wc_payment_complete']))
                $this->update_user(array('cart' => serialize(array())));
        }
    }
    
    /**
     * Returns an instance of class
     * @return WoocommerceWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new WoocommerceWPTP();
        return self::$instance;
    }
}

$WoocommerceWPTP = WoocommerceWPTP::getInstance();