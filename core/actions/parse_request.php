<?php
/**
 * Stuff here will be fired long before even wp-rewrite gets the request.
 * We inject security functions here, like disabling wp-admin.
 * We also do some other evil things here,
 */

class sgl_parse_request {
    
    public function __construct() {
        add_action('admin_init', array(&$this, 'admin_init'), 1);
    }

    public function admin_init() {
        if(!current_user_can('manage_options') || !is_user_logged_in()) {
            wp_safe_redirect(site_url() . "/404");
        }
    }
}

new sgl_parse_request;
