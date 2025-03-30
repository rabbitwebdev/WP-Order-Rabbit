<?php
class WPOR_Orders {
    public static function create_order($cart, $total_price) {
        // Create a new order post
        $order_id = wp_insert_post([
            'post_title' => 'Order ' . time(),
            'post_type' => 'wpor_order',
            'post_status' => 'pending',
        ]);

        // Add order meta for cart items and total price
        update_post_meta($order_id, 'cart_items', $cart);
        update_post_meta($order_id, 'total_price', $total_price);
        return $order_id;
    }
}
