<?php

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