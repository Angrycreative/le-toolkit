<?php

class sgl_login_manager {

    public function __construct() {
        add_filter( 'parse_request', array( &$this, 'parse_request' ), 1, 3 );
        add_filter( 'site_url', array( &$this, 'site_url' ), 10, 3 );
    }

    public function parse_request(&$wp) {


        if ( isset( $wp->query_vars['sgl_user_action'] ) )
            $action = $wp->query_vars['sgl_user_action'];

        if ( isset( $wp->query_vars['action'] ) )
            $action = $wp->query_vars['action'];
        wp_die("HAI");
        // Let's be evil and 404 the entire wp-admin for non-admin / non-loggedin-users
        if(strpos($_SERVER["REQUEST_URI"], 'wp-admin')) {
            if(!is_user_logged_in() || !current_user_can('manage_options'))  {
                wp_die("DAFUQ?!");
                wp_safe_redirect('http://newsite.grapplingligan.se/404');
                exit;
            }
        }

        if ( is_admin() )
            return;

        if(strpos($_SERVER["REQUEST_URI"], 'wp-login.php')) {
            wp_redirect('http://newsite.grapplingligan.se/mina-sidor/logga-in');
            exit;
        }



    }

    /**
     * Rewrite anything with wp-login.php in the url
     */
    public function site_url($url, $path, $orig_scheme) {
        global $pagenow;

        if ('wp-login.php' != $pagenow && strpos($url, 'wp-login.php') !== false && !isset($_REQUEST['interim-login'])) {
            $parsed_url = parse_url($url);
            $url = "http://newsite.grapplingligan.se/mina-sidor/logga-in";
            if ( 'https' == strtolower( $orig_scheme ) )
                $url = preg_replace( '|^http://|', 'https://', $url );

            if ( isset( $parsed_url['query'] ) ) {
                wp_parse_str( $parsed_url['query'], $r );
                foreach ( $r as $k => $v ) {
                    if ( strpos($v, ' ') !== false )
                        $r[$k] = rawurlencode( $v );
                }
                $url = add_query_arg( $r, $url );
            }
        }
        return $url;
    }
}

new sgl_login_manager;
