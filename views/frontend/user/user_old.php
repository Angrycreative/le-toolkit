<?php
define('SGL_USER_TEMPLATES', dirname(__FILE__). '/templates');


class sgl_user_manager {
    
    public function __construct() {
        add_action('template_redirect', array(&$this, 'template_redirect'));
    }
    
    /* Dispatcher for templates */
    /* We get the request before wordpress, and injects our own template handlers! */
    public function template_redirect() {
        
        $action = get_query_var('sgl_user_action');
        
        // Let the request pass if we don't care
        if(empty($action))
            return;
        


        // We did care!
        // Let's figure out what we want.
        
        // Figure out if we want to render a page or handle a post.
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch($method) {
            case 'GET':
                // We want to render a template.
                $this->template_loader();
                break;
            case 'POST':
                // We want to handle some post data.
                $this->post_handler();
                break;
            default:
                wp_die('Only HTTP-POST and HTTP-GET is allowed here.');
        }
        
    }
    
    public function post_handler() {
        $action = get_query_var('sgl_user_action');
        switch($action) {
            case 'logga-in':
                $this->handle_login();
                break;
            case 'profil':
                $this->handle_profile_save();
        }
        
    }
    

    public function handle_login() {
        $secure_cookie = '';
        $interim_login = isset( $_REQUEST['interim-login'] );
        $redirect_to = '';
        $reauth = false;
        $http_post = ( 'POST' == $_SERVER['REQUEST_METHOD'] );
        $secure_cookie = '';
        $errors = new WP_Error();
        
        if ( !$secure_cookie && is_ssl() && force_ssl_login() && !force_ssl_admin() && ( 0 !== strpos( $redirect_to, 'https' ) ) && ( 0 === strpos( $redirect_to, 'http' ) ) )
            $secure_cookie = false;
        
        if ( $http_post && isset( $_POST['log'] ) ) {
            setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN );
            if ( SITECOOKIEPATH != COOKIEPATH )
                setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN );
                
            $user = wp_signon( '', $secure_cookie );
            
            $redirect_to = apply_filters( 'login_redirect', $redirect_to, isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '', $user );
            
            if ( !is_wp_error( $user ) && !$reauth ) {
                if ( ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) ) {
                    if ( is_multisite() && !get_active_blog_for_user( $user->ID ) && !is_super_admin( $user->ID ) )
                        $redirect_to = user_admin_url();
                    elseif ( is_multisite() && !$user->has_cap( 'read' ) )
                        $redirect_to = get_dashboard_url( $user->ID );
                    elseif ( !$user->has_cap( 'edit_posts' ) )
                        $redirect_to = admin_url( 'profile.php' );
                        
                }
                wp_safe_redirect('http://newsite.grapplingligan.se/mina-sidor/logga-in');
                exit();
            }
            
            $errors = $user;
        }
        
        if ( !empty( $_GET['loggedout'] ) || $reauth )
            $errors = new WP_Error();
            
        if ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[TEST_COOKIE] ) )
            $errors->add( 'test_cookie', __( '<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href="http://www.google.com/cookies.html">enable cookies</a> to use WordPress.', 'theme-my-login' ) );
        
        if		( isset( $_GET['loggedout'] ) && true == $_GET['loggedout'] )
            $errors->add( 'loggedout', __( 'You are now logged out.', 'theme-my-login' ), 'message' );
        elseif	( isset( $_GET['registration'] ) && 'disabled' == $_GET['registration'] )
            $errors->add( 'registerdisabled', __( 'User registration is currently not allowed.', 'theme-my-login' ) );
        elseif	( isset( $_GET['checkemail'] ) && 'confirm' == $_GET['checkemail'] )
            $errors->add( 'confirm', __( 'Check your e-mail for the confirmation link.', 'theme-my-login' ), 'message' );
        elseif ( isset( $_GET['resetpass'] ) && 'complete' == $_GET['resetpass'] )
            $errors->add( 'password_reset', __( 'Your password has been reset.', 'theme-my-login' ), 'message' );
        elseif	( isset( $_GET['checkemail'] ) && 'registered' == $_GET['checkemail'] )
            $errors->add( 'registered', __( 'Registration complete. Please check your e-mail.', 'theme-my-login' ), 'message' );
        elseif	( $interim_login )
            $errors->add( 'expired', __( 'Your session has expired. Please log-in again.', 'theme-my-login' ), 'message' );
        elseif	( $reauth )
            $errors->add( 'reauth', __( 'Please log in to continue.', 'theme-my-login' ), 'message' );
            
        if ( $reauth )
            wp_clear_auth_cookie();
        
        
    }
    
    public function template_loader() {
        $action = get_query_var('sgl_user_action');
        $sub_action = empty($_REQUEST['action']) ? '' : $_REQUEST['action'];
        
        switch($action) {
            case 'profil':
                if(!is_user_logged_in())
                    wp_safe_redirect('http://newsite.grapplingligan.se/mina-sidor/logga-in');
                
                remove_action( 'wp_head', 'feed_links', 2 );
                remove_action( 'wp_head', 'feed_links_extra', 3 );
                remove_action( 'wp_head', 'rsd_link' );
                remove_action( 'wp_head', 'wlwmanifest_link' );
                remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );
                remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );
                remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
                remove_action( 'wp_head', 'rel_canonical' );    
                global $wp_version;
                // Don't index any of these forms
                if ( version_compare( $wp_version, '3.3', '<' ) ) {
                    add_filter( 'pre_option_blog_public', '__return_zero' );
                    add_action( 'login_head', 'noindex' );
		    } else {
                    add_action( 'login_head', 'wp_no_robots' );
		    }    
                $template = sgl_get_query_template('sgl-profile', array('sgl-profile.php'));
                break;
            case 'logga-in':
                
                if(!empty($sub_action)) {
                    switch($sub_action) {
                        case 'logout':
                            check_admin_referer( 'log-out' );
                            $user = wp_get_current_user();

                            wp_logout();

                            $redirect_to = apply_filters( 'logout_redirect', site_url( 'wp-login.php?loggedout=true' ), isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '', $user );
                            wp_safe_redirect( $redirect_to );
                            exit();
                    }
                }
                
                if(is_user_logged_in())
                    wp_safe_redirect('http://newsite.grapplingligan.se/mina-sidor/profil');
                
                remove_action( 'wp_head', 'feed_links', 2 );
                remove_action( 'wp_head', 'feed_links_extra', 3 );
                remove_action( 'wp_head', 'rsd_link' );
                remove_action( 'wp_head', 'wlwmanifest_link' );
                remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );
                remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );
                remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
                remove_action( 'wp_head', 'rel_canonical' );    
                global $wp_version;
                // Don't index any of these forms
                if ( version_compare( $wp_version, '3.3', '<' ) ) {
                    add_filter( 'pre_option_blog_public', '__return_zero' );
                    add_action( 'login_head', 'noindex' );
		    } else {
                    add_action( 'login_head', 'wp_no_robots' );
		    }

                $template = sgl_get_query_template('sgl-login', array('sgl-login.php'));
                break;
            default:
                $template = sgl_get_query_template('404');
                break;
        }

        if ( $template = apply_filters( 'template_include', $template ) ) {
            include( $template );
            exit;
        }
    }
    
}

new sgl_user_manager;