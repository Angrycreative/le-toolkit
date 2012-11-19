<?php

class sgl_template_loader {

    // Allowed http methods when loading templates
    public static $allowed_http_methods = array(
        'GET',
        'POST'
    );


    public function __construct() {

        add_action('template_redirect', array(&$this, 'template_redirect'));

    }

    public function template_redirect() {
        $http_request_method = $_SERVER['REQUEST_METHOD'];
        $this->sanitize_request_data();


        switch($http_request_method) {
        case 'GET':
            $this->handle_http_get();
            break;
        case 'POST':
            $this->handle_http_post();
            break;
        }

    }

    public function handle_http_post() {


        $user_action = get_query_var('sgl_user_action');
        $user_view = get_query_var('sgl_user_view');

        if(!empty($user_action)) {
            if(!empty($user_view)) {
                $call_posthooks = function($callback) use (&$call_posthooks) {
                    call_user_func($callback);  
                };

                array_map($call_posthooks, sgl_views_user_get_posthooks($user_action, $user_view));
            }
        }

        $admin_action = get_query_var('sgl_admin_action');
        $admin_view = get_query_var('sgl_admin_view');

        if(!empty($admin_action)) {
            if(!empty($admin_view)) {
                $call_posthooks = function($callback) use (&$call_posthooks) {
                    call_user_func($callback);  
                };

                array_map($call_posthooks, sgl_views_admin_get_posthooks($admin_action, $admin_view));

            }
        }
    }

    public function sanitize_request_data() {
        $sanitize = function($value) use (&$sanitize) {
            return is_array($value) ? array_map($sanitize, $value) : filter_var($value, FILTER_SANITIZE_STRING);
        };

        $_POST = array_map($sanitize, $_POST);
        $_GET = array_map($sanitize, $_GET);
        $_REQUEST = array_map($sanitize, $_REQUEST);
        $_COOKIE = array_map($sanitize, $_COOKIE);

        dbgx_trace_var($_POST);
        dbgx_trace_var($_GET);
        dbgx_trace_var($_REQUEST);
        dbgx_trace_var($_COOKIE);
    }

    public function handle_http_get() {

        // Admin templates
        $admin_action = get_query_var('sgl_admin_action');
        $admin_view = get_query_var('sgl_admin_view');

        if(!empty($admin_action)) {
            sgl_require_login();

            $this->sanitize_wp_head_actions();
            $template_name = sgl_views_get_admin_template($admin_action, $admin_view);
            $template = empty($template_name) ? '' : sgl_get_query_template($template_name);

            $template_callback = sgl_views_get_admin_callback($admin_action, $admin_view);

            if(!empty($template_callback)) {
                foreach($template_callback as $callback) {
                    add_filter('sgl_template_data', $callback);
                }
            }

            if(!empty($template)) {
                $template_data = array();
                $template_data = apply_filters('sgl_template_data', $template_data);

                if ( $template = apply_filters( 'template_include', $template ) ) {
                    require( $template );
                    exit;
                }
            } else {
                sgl_trigger_404();
            }
        }

        $user_action = get_query_var('sgl_user_action');
        $user_view = get_query_var('sgl_user_view');

        if(!empty($user_action)) {
            $this->sanitize_wp_head_actions();

            $view = sgl_views_get_user_view($user_action, $user_view);

            if(!empty($view)) {
                if($view['require_login'] === true) {
                    sgl_require_login();
                }

                $template = empty($view['template_name']) ? '' : sgl_get_query_template($view['template_name']);



                if(!empty($view['callbacks'])) {
                    foreach($view['callbacks'] as $callback) {
                        add_filter('sgl_template_data', $callback);
                    }
                }   

                if(!empty($template)) {
                    $template_data = array();
                    $template_data = apply_filters('sgl_template_data', $template_data);

                    if($template = apply_filters('template_include', $template)) {
                        require($template);
                        exit;
                    }
                }
            }

        }

    }

    /** Remove cruft from wp_head */
    public function sanitize_wp_head_actions() {
        remove_action( 'wp_head', 'feed_links', 2 );
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );
        remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
        remove_action( 'wp_head', 'rel_canonical' );

        global $wp_version;
        if ( version_compare( $wp_version, '3.3', '<' ) ) {
            add_filter( 'pre_option_blog_public', '__return_zero' );
            add_action( 'login_head', 'noindex' );
        } else {
            add_action( 'login_head', 'wp_no_robots' );
        } 
    }
}

new sgl_template_loader;

