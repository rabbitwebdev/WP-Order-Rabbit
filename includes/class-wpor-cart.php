<?php
class WPOR_Cart {
    public static function add_item($item_id, $quantity) {
        // Add item to the session cart
        $_SESSION['wpor_cart'][$item_id] = array(
            'quantity' => $quantity,
        );
    }

    public static function get_cart() {
        return isset($_SESSION['wpor_cart']) ? $_SESSION['wpor_cart'] : [];
    }

    public static function get_cart_total() {
        $total = 0;
        foreach (self::get_cart() as $item_id => $item) {
            $menu_item = get_post($item_id);
            $total += get_post_meta($item_id, 'price', true) * $item['quantity'];
        }
        return $total;
    }

    public static function clear_cart() {
        unset($_SESSION['wpor_cart']);
    }
}
