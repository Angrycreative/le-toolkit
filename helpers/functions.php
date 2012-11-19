<?php

function sgl_static_file_uri($file) {
    return plugins_url('static/'. $file, SGL_PLUGIN_PHP);
}

function sgl_register_script($name, $file, $version, $deregister = false) {
    if($deregister === true)
        wp_deregister_script($name);

    wp_register_script($name, sgl_static_file_uri($file), false, $version, true);
    wp_enqueue_script($name);
}

function sgl_register_style($name, $file, $deregister = false) {
    if($deregister === true)
        wp_deregister_style($name);

    wp_register_style($name, sgl_static_file_uri($file), false);
    wp_enqueue_style($name);
}

function sgl_tprintf($template, $arguments) {
    return strstr($template, $arguments);
}


// Like array_merge but makes values uniqe.
// Works like the noraml array_merge
function array_unique_merge() {
    return array_unique(call_user_func_array('array_merge', func_get_args()));
}

function sgl_strpos($haystack, $needles=array(), $offset=0) {
    $chr = array();
    foreach($needles as $needle) {
        $res = strpos($haystack, $needle, $offset);
        if ($res !== false) $chr[$needle] = $res;
    }
    if(empty($chr)) return false;
    return min($chr);
}

function sgl_array_items_to_int($item) {
    return abs($item);
}


// Wrapper around get_query_template
function sgl_get_query_template($type, $templates = array()) {
    $type = preg_replace( '|[^a-z0-9-]+|', '', $type );

    if ( empty( $templates ) )
        $templates = array("{$type}.php");

    $template = get_query_template($type, $templates);

    // Ok we gave the theme developer a chances to implement our template,
    // Load the default instaed.
    if(empty($template))
        $template = sgl_locate_template($templates);

    return $template;
}

// Works as locate_template, looks in different directories
function sgl_locate_template($template_names, $load = false, $require_once = true ) {
    $located = '';
    foreach ( (array) $template_names as $template_name ) {
        if ( !$template_name )
            continue;
        if ( file_exists(SGL_USER_TEMPLATES . '/' . $template_name)) {
            $located = SGL_USER_TEMPLATES . '/' . $template_name;
            break;
        }
        if ( file_exists(SGL_ADMIN_TEMPLATES . '/' . $template_name)) {
            $located = SGL_ADMIN_TEMPLATES . '/' . $template_name;
            break;
        }
    }

    if ( $load && '' != $located )
        load_template( $located, $require_once );

    return $located;
}

function sgl_current_uri() {
    echo sgl_get_current_uri();
}

function sgl_get_current_uri() {
    return $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
}

function sgl_trigger_404() {
    global $wp_query;
    $wp_query->set_404();
}


function sgl_require_login() {
    if(!is_user_logged_in()) {
        wp_safe_redirect('http://newsite.grapplingligan.se/mina-sidor/logga-in');
        exit;
    }
}

function sgl_get_extra_user_info(&$users) {
    if(is_array($users)) {
        foreach($users as &$user) {

            unset($user->user_pass);
            unset($user->user_activation_key);
            $avatar_size = (!empty($_REQUEST['avatar_size'])) ? $_REQUEST['avatar_size'] : "";
            $user->avatar = get_avatar($user->ID, $avatar_size);
            $weightdiv = wp_get_object_terms($user->ID, 'weightdivisions');
            $user->weight = (empty($weightdiv)) ? '' : $weightdiv[0]->name;
            $club = wp_get_object_terms($user->ID, 'sgl_clubs');
            $user->club = (empty($club)) ? '' : $club[0]->name;
    
            if(!empty($club)) {
                $region = get_term(get_term_meta($club[0]->term_id, 'sgl_region', true), 'sgl_regions');
    
                $user->region = (empty($region) || is_wp_error($region)) ? '' : $region->name;
            } else {
                $user->region = '';
            }
    
            $skill = wp_get_object_terms($user->ID, 'skilldivisions');
            $user->skill = (empty($skill)) ? '' : $skill[0]->name;
    
            }
    } 
}

function sgl_array_match_all($needle, $haystack, $searchkey = "") {
    
    $matches = false;
    
    foreach($haystack as $key => $value) {
        
        if (empty($searchkey)) {
            if (is_array($value) || is_object($value)) {
                $sub_matches = sgl_array_match_all($needle, $value);
                if(is_array($sub_matches) && count($sub_matches) > 0)
                    $matches[] = $key;
            } else {
                if($needle == $value)
                    $matches[] = $key;
            }
        } else {
            if (is_array($value) || is_object($value)) {
                $sub_matches = sgl_array_match_all($needle, $value, $searchkey);
                if(is_array($sub_matches) && count($sub_matches) > 0)
                    $matches[] = $key;
            } else {
                if ($key == $searchkey && $value == $needle)
                    $matches[] = $key;
            }
            
        }
        
    }
    
    return $matches;

}
