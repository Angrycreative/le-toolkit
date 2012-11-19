<?php
define('SGL_USER_TEMPLATES', dirname(__FILE__). '/templates');

$sgl_login_callbacks = array(
    'sgl_views_user_handle_logout',
    'sgl_views_user_handle_isloggedin',
    'sgl_views_user_handle_loginactions'
);

sgl_views_register_user_view('sgl-login', 'user', 'login-page', $sgl_login_callbacks, false);
sgl_views_register_user_view('sgl-profile', 'user', 'profile');
sgl_views_register_user_view('sgl-ranking', 'user', 'ranking', 'sgl_admin_view_load_events_data', false);
sgl_views_register_user_view('sgl-register', 'user', 'register', '', false);
sgl_views_user_register_posthooks('user', 'login-page', 'sgl_views_user_post_handler_login');
sgl_views_user_register_posthooks('user', 'profile', 'sgl_views_user_handle_profilesave');


function sgl_user_view_load_ranking_data($template_data) {



    $get_events_by_year_and_region = function() use (&$sort_events_by_year_and_region) {
        $events = query_posts(array('post_type' => 'sgl_event'));
        $sorted = array();

        foreach($events as $event) {

            $this_year = date('Y', strtotime(get_metadata('post', $event->ID, 'sgl_metabox_dates_eventdate', true)));
            if(empty($sorted[$this_year]))
                $sorted[$this_year] = array();

            $regions = wp_get_object_terms($event->ID, 'sgl_regions');   
            if(!empty($regions) && !is_wp_error($regions)) {
                $current_region = $regions[0]->name;
            } else {
                $current_region = "Okänd";
            }

            if(empty($sorted[$this_year][$current_region]))
                $sorted[$this_year][$current_region] = array();

            $sorted[$this_year][$current_region][] = $event;


        }

        $ksort_array = function(&$array) use(&$ksort_array) {
            krsort($array);
            foreach($array as &$item) {
                ksort($item);   
            }
        };

        $ksort_array($sorted);
        return $sorted;
    };

    $template_data['events'] = $get_events_by_year_and_region();

    return $template_data;
}


function sgl_views_user_handle_profilesave() {
    global $userdata; get_currentuserinfo();
    $user_ID = $_REQUEST['user_id'];

    require_once(ABSPATH . 'wp-admin/includes/user.php');
    require_once(ABSPATH . WPINC . '/registration.php');

    // Hack to prevent disabling wp-adminbar for admins.
    if(current_user_can('manage_options')) {
        $_POST['admin_bar_front'] = 1;
    }
    check_admin_referer('update-user_' . $user_ID);


    $errors = edit_user($user_ID);

    if ( is_wp_error( $errors ) ) {
        foreach( $errors->get_error_messages() as $message )
            $errmsg = "$message";
    }

    if($errmsg == '')
    {
        do_action('personal_options_update',$user_ID);
        $d_url = $_POST['dashboard_url'];
        wp_redirect( site_url() .'/mina-sidor/profil?saved=true' );
    }
    else{

        $template = sgl_get_query_template('sgl-profile', array('sgl-profile.php'));

        if ( $template = apply_filters( 'template_include', $template ) ) {
            include( $template );
            exit;
        }
    }

    exit; 
}

// Give other plugins a chance to do stuff with logins.
function sgl_views_user_handle_loginactions($template_data) {
    $action = get_query_var('sgl_user_action');
    do_action( 'login_init' );
    do_action( 'login_form_' . $action );

    return $template_data;
}


// This is a special case, and thus, we hook it this way.
function sgl_views_user_handle_isloggedin($template_data) {
    if(is_user_logged_in()) {
        wp_safe_redirect(site_url() .'/mina-sidor/profil');
        exit;
    }

    return $template_data;
}

function sgl_views_user_handle_logout($template_data) {

    if(empty($_REQUEST['action']))
        return $template_data;

    if($_REQUEST['action'] !== 'logout')
        return $template_data;    

    check_admin_referer('log-out');
    $user = wp_get_current_user();
    wp_logout();
    $redirect_to = apply_filters( 'logout_redirect', site_url( 'wp-login.php?loggedout=true' ), isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '', $user );
    wp_safe_redirect( $redirect_to );
    exit;
}

function sgl_views_user_post_handler_login() {
    $secure_cookie = '';
    $interim_login = isset($_REQUEST['interim-login']);
    $redirect_to = '';
    $reauth = false;
    $http_post = ('POST' == $_SERVER['REQUEST_METHOD']);
    $errors = new WP_Error();

    if(!$secure_cookie && is_ssl() && force_ssl_login && !forcec_ssl_admin() && (0 !== strpos($redirect_to, 'https')) && (0 === strpos($redirect_to, 'http')))
        $secure_cookie = false;

    if($http_post && isset($_POST['log'])) {
        setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);

        if(SITECOOKIEPATH != COOKIEPATH)
            setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);

        $user = wp_signon('', $secure_cookie);
        $redirect_to = apply_filters('login_redirect', $redirect_to, isset($_REQUEST['reirect_to']) ? $_REQUEST['redirect_to'] : '', $user);

        if(!is_wp_error($user) && !$reauth) {
            if(( empty($redirect_to) || $redirect_to = 'wp-admin/' || $redirect_to == admin_url() )) {
                if ( is_multisite() && !get_active_blog_for_user( $user->ID ) && !is_super_admin( $user->ID ) )
                    $redirect_to = user_admin_url();
                elseif ( is_multisite() && !$user->has_cap( 'read' ) )
                    $redirect_to = get_dashboard_url( $user->ID );
                elseif ( !$user->has_cap( 'edit_posts' ) )
                    $redirect_to = admin_url( 'profile.php' );
            }
            wp_safe_redirect(site_url('/mina-sidor/logga-in'));
            exit;
        }
        $errors = $user;
    }

    if ( !empty( $_GET['loggedout'] ) || $reauth )
        $errors = new WP_Error();

    if ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[TEST_COOKIE] ) )
        $errors->add( 'test_cookie', __( '<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href="http://www.google.com/cookies.html">enable cookies</a> to use WordPress.', 'theme-my-login' ) );

    if	( isset( $_GET['loggedout'] ) && true == $_GET['loggedout'] )
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

    $template = sgl_get_query_template('sgl-login');
    if($template = apply_filters('template_include', $template)) {
        require($template);
        exit;
    }

}

