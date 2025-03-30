<?php
/**
 * Plugin Name: WP Order Rabbit
 * Description: A plugin to manage food menu items, take orders, and process payments using Stripe.
 * Version: 1.3.2
 * Author: Your Name
 */




// Define constants for plugin paths
define('WPOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
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

// Register Custom Post Type for Menu Items
function wpor_register_menu_items_post_type() {
    $args = array(
        'public' => true,
        'label'  => 'Menu Items',
        'supports' => array('title', 'editor'),
    );
    register_post_type('wpor_menu_item', $args);
}

add_action('init', 'wpor_register_menu_items_post_type');

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
        echo $price ? '$' . $price : 'N/A';
    }
}

add_action('manage_wpor_menu_item_posts_custom_column', 'wpor_display_price_column', 10, 2);

 // Start the session if it's not already started
function wpor_start_session() {
    if (!session_id()) {
        session_start();
    }
}

add_action('init', 'wpor_start_session');

function wpor_display_menu() {
    $args = array('post_type' => 'wpor_menu_item', 'posts_per_page' => -1);
    $menu_items = get_posts($args);

    $output = '<ul class="wpor-menu">';
    foreach ($menu_items as $item) {
        $output .= '<li>';
        $output .= '<h3>' . $item->post_title . '</h3>';
        $output .= '<p>' . $item->post_content . '</p>';
        $output .= '<span>$' . get_post_meta($item->ID, 'price', true) . '</span>';
        $output .= '<button class="add-to-cart" data-item-id="' . $item->ID . '">Add to Cart</button>';
        $output .= '</li>';
    }
    $output .= '</ul>';
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
        WPOR_Cart::add_item($item_id, 1); // Default quantity is 1
        wp_send_json_success('Item added to cart!');
    }
    wp_send_json_error('Invalid item ID');
}

add_action('wp_ajax_wpor_add_to_cart', 'wpor_add_to_cart');
add_action('wp_ajax_nopriv_wpor_add_to_cart', 'wpor_add_to_cart');



function wpor_cart_page() {
    $cart = WPOR_Cart::get_cart();
    $total_price = WPOR_Cart::get_cart_total();

    // Display cart items and total price
    $output = '<h2>Your Cart</h2>';
    $output .= '<ul>';
    foreach ($cart as $item_id => $item) {
        $menu_item = get_post($item_id);
        $output .= '<li>' . $menu_item->post_title . ' x' . $item['quantity'] . '</li>';
    }
    $output .= '</ul>';
    $output .= '<p>Total: $' . $total_price . '</p>';

    // Stripe checkout button
    $stripe = new WPOR_Stripe();
    $payment_intent = $stripe->create_payment_intent($total_price);

    if ($payment_intent) {
        $output .= '<button id="stripe-checkout" data-payment-intent="' . $payment_intent->id . '">Checkout</button>';
    } else {
        $output .= '<p>Error processing payment. Try again later.</p>';
    }

    return $output;
}

add_shortcode('wpor_cart', 'wpor_cart_page');


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


