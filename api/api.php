<?php
class ApiDispatcher {

    public function __construct() {

        add_action('template_redirect', array(&$this, 'templateRedirect'));
    }

    public function templateRedirect() {

        $version = get_query_var('sgl_api_version');
        if(!empty($version)) {

            verifyApiKey();
            $path = dirname(__FILE__) . "/1";
            $file = $path . '/'. get_query_var('sgl_api_function') .'.php';
            if(file_exists($file)) {
                require_once($file);
                exit;
            } else {
                apiOutputData(array('error' => 'Function does not exist!'));
                exit;
            }
        }
    }
}

new ApiDispatcher;

function getApiKey() {
    return wp_create_nonce(API_KEY);
}


function apiOutputData($data, $die = false) {
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Wed, 4 Apr 1984 07:00:00 GMT');
    header('Content-type: application/json; charset=UTF-8');
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
    
    echo json_encode($data);

    if($die)
        die('');

    exit;
}



function verifyApiKey() {
    $nonce = empty($_REQUEST['api_key']) ? '' : $_REQUEST['api_key'];


    // Override used for debuggning.
    // Never, ever ever use this on a public page,
    // use getApiKey() in templates etc.

    if($nonce == "pb4zFphWJ9OwigLv4jh1LtI8WYjnrr") {
        return;
    }

    if(!wp_verify_nonce($nonce, API_KEY)) {
        apiOutputData(json_encode(array('error' => 'invalid api key')), true);
        exit;
    }
}
