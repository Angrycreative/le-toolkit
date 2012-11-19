<?php

function gl_user_login() {
    global $current_user;
    get_currentuserinfo();
    $meta = get_user_meta($current_user->ID, 'last_login_date', true);
    if(empty($meta)) {
        add_user_meta($current_user->ID, 'last_login_date', time());
    } else {
        update_user_meta($current_user->ID, 'last_login_date', time());
    } 
}
add_action('wp_login', 'gl_user_login');
