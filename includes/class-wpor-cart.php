<?php
class WPOR_Cart {

    // Add an item to the cart
    public static function add_item($item_id, $quantity) {
        // Start the session if it's not already started
        if (!session_id()) {
            session_start();
        }

        // Check if the cart exists, otherwise initialize it
        if (!isset($_SESSION['wpor_cart'])) {
            $_SESSION['wpor_cart'] = [];
        }

        // Add the item to the cart (or update the quantity if already exists)
        if (isset($_SESSION['wpor_cart'][$item_id])) {
            $_SESSION['wpor_cart'][$item_id]['quantity'] += $quantity;
        } else {
            $_SESSION['wpor_cart'][$item_id] = [
                'quantity' => $quantity,
            ];
        }
    }

    // Get the cart items
    public static function get_cart() {
        // Ensure session is started
        if (!session_id()) {
            session_start();
        }

        return isset($_SESSION['wpor_cart']) ? $_SESSION['wpor_cart'] : [];
    }

    // Get total price of the cart
    public static function get_cart_total() {
        $total = 0;
        foreach (self::get_cart() as $item_id => $item) {
            $menu_item = get_post($item_id);
            $price = get_post_meta($item_id, 'price', true);
            $total += $price * $item['quantity'];
        }
        return $total;
    }

    // Clear the cart
    public static function clear_cart() {
        if (isset($_SESSION['wpor_cart'])) {
            unset($_SESSION['wpor_cart']);
        }
    }
}

