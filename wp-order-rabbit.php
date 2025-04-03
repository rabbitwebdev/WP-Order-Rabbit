<?php
/**
 * Plugin Name: WP Order Rabbit
 * Description: A plugin to manage food menu items, take orders, and process payments using Stripe.
 * Version: 5.5.0
 * Author: Your Name
 */


define('STRIPE_TEST_SECRET_KEY', 'sk_test_51D6ONeIF6Cy7jqRet27KpvHQKXN0jEKPm6tj2OUZhgjhbxKQY7QekwB3kothe2oVCRsvlOcFJme7AIBVqfwDuGVA00yBMG4twd');
define('STRIPE_TEST_PUBLISHABLE_KEY', 'pk_test_51D6ONeIF6Cy7jqReWezmkFBNCNdGvKHr1HyWNaSTCH8dFRZL9K26NINBrzB1Yh9nPjbWoRDTuT7XSdxAQVCpLPrQ00WH5dLzyT');


// Define constants for plugin paths
define('WPOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once WPOR_PLUGIN_DIR . 'includes/custom-post-type.php';
require_once WPOR_PLUGIN_DIR . 'includes/class-wpor-cart.php';
require_once WPOR_PLUGIN_DIR . 'includes/class-wpor-stripe.php';
require_once WPOR_PLUGIN_DIR . 'includes/class-wpor-orders.php';

// Hook for activating the plugin
function wpor_activate_plugin() {
    // Register custom post types or perform other activation tasks
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'wpor_activate_plugin');

// Hook for deactivating the plugin
function wpor_deactivate_plugin() {
    // Cleanup tasks like removing custom post types
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'wpor_deactivate_plugin');

// Register styles and scripts
function wpor_enqueue_assets() {
    wp_enqueue_style('wpor-style', WPOR_PLUGIN_URL . 'assets/css/wpor-style.css');
    wp_enqueue_script('wpor-scripts', WPOR_PLUGIN_URL . 'assets/js/wpor-scripts.js', array('jquery'), null, true);
}

add_action('wp_enqueue_scripts', 'wpor_enqueue_assets');



// Add a custom meta box for the price field
function wpor_add_price_meta_box() {
    add_meta_box(
        'wpor_price_meta_box', // ID
        'Price', // Title
        'wpor_display_price_meta_box', // Callback function to display the field
        'wpor_menu_item', // Post type
        'normal', // Context
        'high' // Priority
    );
}

add_action('add_meta_boxes', 'wpor_add_price_meta_box');

function wpor_add_sale_price_meta_box() {
    add_meta_box(
        'wpor_sale_price_meta_box', // ID
        'Sale Price', // Title
        'wpor_display_sale_price_meta_box', // Callback function to display the field
        'wpor_menu_item', // Post type
        'normal', // Context
        'high' // Priority
    );
}

add_action('add_meta_boxes', 'wpor_add_sale_price_meta_box');

function wpor_display_sale_price_meta_box($post) {
    $sale_price = get_post_meta($post->ID, 'sale_price', true);
    ?>
    <label for="wpor_sale_price">sale_Price:</label>
    <input type="number" name="wpor_sale_price" id="wpor_sale_price" value="<?php echo esc_attr($sale_price); ?>" step="0.01" min="0" />
    <?php
}

// Display the price input field in the meta box
function wpor_display_price_meta_box($post) {
    $price = get_post_meta($post->ID, 'price', true);
    ?>
    <label for="wpor_price">Price:</label>
    <input type="number" name="wpor_price" id="wpor_price" value="<?php echo esc_attr($price); ?>" step="0.01" min="0" />
    <?php
}

// Save the price field data when the post is saved
function wpor_save_price_meta_box($post_id) {
    // Check if it's a valid save request
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;

    if (isset($_POST['wpor_price'])) {
        update_post_meta($post_id, 'price', sanitize_text_field($_POST['wpor_price']));
    }
    return $post_id;
}

add_action('save_post', 'wpor_save_price_meta_box');

function wpor_save_sale_price_meta_box($post_id) {
    // Check if it's a valid save request
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;

    if (isset($_POST['wpor_sale_price'])) {
        update_post_meta($post_id, 'sale_price', sanitize_text_field($_POST['wpor_sale_price']));
    }
    return $post_id;
}

add_action('save_post', 'wpor_save_sale_price_meta_box');

// Add price column to the menu item list in admin
function wpor_add_price_column($columns) {
    $columns['price'] = 'Price';
    return $columns;
}

add_filter('manage_wpor_menu_item_posts_columns', 'wpor_add_price_column');

// Display price in the custom column
function wpor_display_price_column($column, $post_id) {
    if ($column == 'price') {
        $price = get_post_meta($post_id, 'price', true);
        echo $price ? '£' . $price : 'N/A';
    }
}

add_action('manage_wpor_menu_item_posts_custom_column', 'wpor_display_price_column', 10, 2);

 // Start the session if it's not already started
// function wpor_start_session() {
//     if (!session_id()) {
//         session_start();
//     }
// }

// add_action('init', 'wpor_start_session');

function wpor_display_menu() {
    $args = array('post_type' => 'wpor_menu_item', 'posts_per_page' => -1);
    $menu_items = get_posts($args);

    $output = '<div class="wpor-menu">';
    foreach ($menu_items as $item) {
       
        $output .= '<div class="wpor-menu-item">';
        $output .= '<div class="wpor-menu-item-image">' . get_the_post_thumbnail($item->ID, 'medium') . '</div>';
        $output .= '<div class="wpor-menu-item-details">';
        $output .= '<h3>' . $item->post_title . '</h3>';
        $output .= '<p class="small">' . $item->post_content . '</p>';
        if (get_post_meta($item->ID, 'sale_price', true)) {
            $output .= '<p class="wpor-price small">£' . get_post_meta($item->ID, 'price', true) . '</p>';
            $output .= '<p class="wpor-sale-price">New Price £' . get_post_meta($item->ID, 'sale_price', true) . '</p>';
        } else {
            $output .= '<p class="wpor-price">£' . get_post_meta($item->ID, 'price', true) . '</p>';
        }
        $output .= '</div>';
        $output .= '<button class="add-to-cart btn btn-secondary-sm" data-item-id="' . $item->ID . '">Add to Cart</button>';
        $output .= '</div>';
    }
    $output .= '</div>';
     // Include JavaScript to handle adding items to the cart
    $output .= '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $(".add-to-cart").click(function() {
                var itemId = $(this).data("item-id");

                $.post("'. admin_url('admin-ajax.php') .'", {
                    action: "wpor_add_to_cart",
                    item_id: itemId
                }, function(response) {
                    alert("Item added to cart!");
                });
            });
        });
    </script>';

    return $output;
}

add_shortcode('wpor_menu', 'wpor_display_menu');

// Handle adding items to the cart via AJAX
function wpor_add_to_cart() {
    if (isset($_POST['item_id'])) {
        $item_id = intval($_POST['item_id']);
        WPOR_Cart::add_item($item_id); // Default quantity is 1
        wp_send_json_success('Item added to cart!');
    }
    wp_send_json_error('Invalid item ID');
}

add_action('wp_ajax_wpor_add_to_cart', 'wpor_add_to_cart');
add_action('wp_ajax_nopriv_wpor_add_to_cart', 'wpor_add_to_cart');



// function wpor_create_payment_intent() {
//     if (isset($_POST['payment_intent_id'])) {
//         $payment_intent_id = sanitize_text_field($_POST['payment_intent_id']);
//         $stripe = new WPOR_Stripe();
//         $payment_intent = $stripe->create_payment_intent($payment_intent_id);
        
//         if ($payment_intent) {
//             wp_send_json_success(['client_secret' => $payment_intent->client_secret]);
//         } else {
//             wp_send_json_error(['message' => 'Error creating payment intent.']);
//         }
//     }
//     wp_die();
// }

// add_action('wp_ajax_wpor_create_payment_intent', 'wpor_create_payment_intent');
// add_action('wp_ajax_nopriv_wpor_create_payment_intent', 'wpor_create_payment_intent');


function wpor_cart_page() {
    $cart = WPOR_Cart::get_cart();
     $total_price = WPOR_Cart::get_cart_total();

    if (empty($cart)) {
        return '<p>Your cart is empty.</p>';
    }

    $output = '<h2>Your Cart</h2>';
    $output .= '<ul>';

    foreach ($cart as $item_id => $item) {
        $menu_item = get_post($item_id);
        $output .= '<li>' . $menu_item->post_title . ' x' . $item['quantity'] . '</li>';
    }

    $output .= '</ul>';
    $output .= '<a href="' . esc_url(add_query_arg('wpor_add_to_wc_cart', 'true', wc_get_checkout_url())) . '" class="button btn btn-secondary">Proceed to Checkout</a>';
 $output .= '<p>Total: £' . $total_price . '</p>';
    return $output;
}
add_shortcode('wpor_cart', 'wpor_cart_page');

// function wpor_cart_page() {
//     $cart = WPOR_Cart::get_cart();
//     $total_price = WPOR_Cart::get_cart_total();

//     if (empty($cart)) {
//         return '<p>Your cart is empty.</p>';
//     }

//     // Initialize Stripe Payment Intent
//     $stripe = new WPOR_Stripe();
//     $payment_intent = $stripe->create_payment_intent($total_price);

//     if ($payment_intent) {
//         $output = '<h2>Your Cart</h2>';
//         $output .= '<ul>';
//         foreach ($cart as $item_id => $item) {
//             $menu_item = get_post($item_id);
//             $output .= '<li>' . $menu_item->post_title . ' x' . $item['quantity'] . '</li>';
//         }
//         $output .= '</ul>';
//         $output .= '<p>Total: £' . $total_price . '</p>';
        
//         // Stripe checkout form with card element
//         $output .= '<div id="card-element">
//             <!-- A Stripe Element will be inserted here. -->
//         </div>';
//         $output .= '<div id="card-errors" role="alert"></div>';  // Show errors from Stripe

//         $output .= '<button id="stripe-checkout" data-payment-intent="' . $payment_intent->id . '">Checkout</button>';

//         // Include Stripe.js and custom JS
      
//         $output .= '<script type="text/javascript">
//             var stripe = Stripe("' . STRIPE_TEST_PUBLISHABLE_KEY . '");
//             var elements = stripe.elements();
//             var cardElement = elements.create("card");

//             cardElement.mount("#card-element");

//             var checkoutButton = document.getElementById("stripe-checkout");

//             checkoutButton.addEventListener("click", function (event) {
//                 event.preventDefault();

//                 var paymentIntentId = checkoutButton.getAttribute("data-payment-intent");

//                 fetch("' . admin_url('admin-ajax.php') . '", {
//                     method: "POST",
//                     headers: { "Content-Type": "application/json" },
//                     body: JSON.stringify({
//                         action: "wpor_create_payment_intent",
//                         payment_intent_id: paymentIntentId
//                     })
//                 })
//                 .then(function(response) { return response.json(); })
//                 .then(function(data) {
//                     var clientSecret = data.client_secret;

//                     stripe.confirmCardPayment(clientSecret, {
//                         payment_method: {
//                             card: cardElement,
//                         }
//                     }).then(function(result) {
//                         if (result.error) {
//                             console.log(result.error.message);
//                         } else {
//                             window.location.href = "' . site_url('/thank-you') . '";
//                         }
//                     });
//                 });
//             });
//         </script>';

//         return $output;
//     } else {
//         return '<p>Error processing payment. Try again later.</p>';
//     }
// }
// add_shortcode('wpor_cart', 'wpor_cart_page');


// function wpor_cart_page() {
//     $cart = WPOR_Cart::get_cart();
//     $total_price = WPOR_Cart::get_cart_total();

//     // Display cart items and total price
//     $output = '<h2>Your Cart</h2>';
//     $output .= '<ul>';
//     foreach ($cart as $item_id => $item) {
//         $menu_item = get_post($item_id);
//         $output .= '<li>' . $menu_item->post_title . ' x' . $item['quantity'] . '</li>';
//     }
//     $output .= '</ul>';
//     $output .= '<p>Total: £' . $total_price . '</p>';

//     // Stripe checkout button
//     $stripe = new WPOR_Stripe();
//     $payment_intent = $stripe->create_payment_intent($total_price);

//     if ($payment_intent) {
//         $output .= '<button id="stripe-checkout" data-payment-intent="' . $payment_intent->id . '">Checkout</button>';
//     } else {
//         $output .= '<p>Error processing payment. Try again later.</p>';
//     }

//     return $output;
// }

// add_shortcode('wpor_cart', 'wpor_cart_page');


// function wpor_test_cart() {
//     // For testing, add an item manually to the cart
//     WPOR_Cart::add_item(857, 1); // Replace 123 with an actual post ID of a menu item
// }
// add_action('init', 'wpor_test_cart');

// function wpor_cart_page() {
//     $cart = WPOR_Cart::get_cart();
//     $total_price = WPOR_Cart::get_cart_total();

//     if (empty($cart)) {
//         return '<p>Your cart is empty.</p>';
//     }

//     $output = '<h2>Your Cart</h2>';
//     $output .= '<ul>';
//     foreach ($cart as $item_id => $item) {
//         $menu_item = get_post($item_id);
//         $output .= '<li>' . $menu_item->post_title . ' x' . $item['quantity'] . '</li>';
//     }
//     $output .= '</ul>';
//     $output .= '<p>Total: $' . $total_price . '</p>';

//     return $output;
// }

// add_shortcode('wpor_cart', 'wpor_cart_page');


// function wpor_cart_page() {
//     $cart = WPOR_Cart::get_cart();
//     $total_price = WPOR_Cart::get_cart_total();

//     // Display cart items and total price
//     $output = '<h2>Your Cart</h2>';
//     $output .= '<ul>';
//     foreach ($cart as $item_id => $item) {
//         $menu_item = get_post($item_id);
//         $output .= '<li>' . $menu_item->post_title . ' x' . $item['quantity'] . '</li>';
//     }
//     $output .= '</ul>';
//     $output .= '<p>Total: $' . $total_price . '</p>';

//     // Stripe checkout button
//     $stripe = new WPOR_Stripe();
//     $payment_intent = $stripe->create_payment_intent($total_price);

//     if ($payment_intent) {
//         $output .= '<button id="stripe-checkout" data-payment-intent="' . $payment_intent->id . '">Checkout</button>';
//     } else {
//         $output .= '<p>Error processing payment. Try again later.</p>';
//     }

//     return $output;
// }

// add_shortcode('wpor_cart', 'wpor_cart_page');

// function wpor_cart_page() {
//     $cart = WPOR_Cart::get_cart();
//     $total_price = WPOR_Cart::get_cart_total();

//     // Debugging output
//     var_dump($cart);  // Will output the cart array
//     var_dump($total_price); // Will output the total price

//     $output = '<h2>Your Cart</h2>';
//     $output .= '<ul>';
//     foreach ($cart as $item_id => $item) {
//         $menu_item = get_post($item_id);
//         $output .= '<li>' . $menu_item->post_title . ' x' . $item['quantity'] . '</li>';
//     }
//     $output .= '</ul>';
//     $output .= '<p>Total: $' . $total_price . '</p>';

//     return $output;
// }

// add_shortcode('wpor_cart', 'wpor_cart_page');




add_action('template_redirect', 'wpor_add_to_woocommerce_cart');

function wpor_add_to_woocommerce_cart() {
    if (isset($_GET['wpor_add_to_wc_cart']) && $_GET['wpor_add_to_wc_cart'] == 'true') {
        WC()->cart->empty_cart(); // Clear existing cart

        $cart = WPOR_Cart::get_cart();

        foreach ($cart as $item_id => $item) {
            $menu_item = get_post($item_id);
            $price = get_post_meta($item_id, 'price', true);
            $sale_price = get_post_meta($item_id, 'sale_price', true);
            $thumbnail_id = get_post_meta( $item_id, '_thumbnail_id', true );
         

            // Add custom WooCommerce product for WP Order Rabbit items
            $product_id = wpor_create_woocommerce_product($menu_item->post_title, $price, $sale_price, $thumbnail_id);
            
            // Add product to WooCommerce cart
            WC()->cart->add_to_cart($product_id, $item['quantity']);
        }

        // Redirect to WooCommerce checkout
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
}
function wpor_create_woocommerce_product($title, $price, $sale_price, $thumbnail_id) {    
    $product = new WC_Product_Simple();
    $product->set_name($title);
    // Convert price to a valid number
    $price = floatval($price);
    
    // Ensure price is greater than zero
    if ($price <= 0) {
        $price = 1.00; // Set a default price if something goes wrong
    }

    $product->set_price($price);
    $product->set_regular_price($price);
    if ($sale_price) {
        $product->set_sale_price($sale_price);
    } 
    $product->set_image_id($thumbnail_id); // Set the featured image
    $product->set_status('publish'); // Ensure it's published
    $product->set_catalog_visibility('hidden'); // Hide from the shop
    $product->set_virtual(true); // Make it a virtual product
    $product->set_manage_stock(false); // No stock management
    $product->set_sold_individually(true); // Prevent duplicate purchases

    $product_id = $product->save(); // Save the product

    return $product_id;
}

add_filter('woocommerce_checkout_fields', 'wpor_add_delivery_address_field');

function wpor_add_delivery_address_field($fields) {
    $fields['billing']['wpor_delivery_address'] = array(
        'type'        => 'text',
        'label'       => __('Delivery Address', 'wpor'),
        'placeholder' => __('Enter your delivery address', 'wpor'),
        'required'    => true, // Make it required
        'class'       => array('form-row-wide'),
        'priority'    => 25, // Position after billing address
    );

    return $fields;
}

add_action('woocommerce_checkout_update_order_meta', 'wpor_save_delivery_address');

function wpor_save_delivery_address($order_id) {
    if (!empty($_POST['wpor_delivery_address'])) {
        update_post_meta($order_id, '_wpor_delivery_address', sanitize_text_field($_POST['wpor_delivery_address']));
    }
}


add_action('woocommerce_admin_order_data_after_billing_address', 'wpor_display_delivery_address_in_admin', 10, 1);

function wpor_display_delivery_address_in_admin($order) {
    $delivery_address = get_post_meta($order->get_id(), '_wpor_delivery_address', true);
    if (!empty($delivery_address)) {
        echo '<p><strong>' . __('Delivery Address:', 'wpor') . '</strong> ' . esc_html($delivery_address) . '</p>';
    }
}

add_filter('woocommerce_email_order_meta_keys', 'wpor_add_delivery_address_to_email');

function wpor_add_delivery_address_to_email($keys) {
    $keys['_wpor_delivery_address'] = 'Delivery Address';
    return $keys;
}
