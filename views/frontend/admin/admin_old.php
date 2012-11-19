<?php
define('SGL_ADMIN_TEMPLATES', dirname(__FILE__). '/templates');





class sgl_admin_manager {
    
    public function __construct() {
        add_action('template_redirect', array(&$this, 'template_redirect'));
    }
    
    /* Dispatcher for templates */
    /* We get the request before wordpress, and injects our own template handlers! */
    public function template_redirect() {
        
        $action = get_query_var('sgl_admin_action');
        
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
                wp_die('POST=!=!=!=!=!=!');
                $this->post_handler();
                break;
            default:
                wp_die('Only HTTP-POST and HTTP-GET is allowed here.');
        }
        
    }
    
    public function template_loader() {
	 /*** HEJ JAG HAR SKRVIT OM DIN TEMPLATE LOADER KTXBAI ***/
	 /*** Du har helt enkelt gjort fel, du lägger inte if-satser som du har gjort gör rewrites ordentligt ***/
	 
	 /*
        $action = explode("/", get_query_var('sgl_admin_action'));
        
        if (!current_user_can('manage_options')) {
            die("Sorry, you're not authorized to do this.");
        }
        
        switch($action[0]) {
            case "anvandare":
                if (empty($action[1])) {
                    $template = sgl_get_query_template('sgl-user-archive', array('sgl-user-archive.php'));
                } else {
                    $curauth = sgl_admin_load_user_object($action[1]);
                    $template = sgl_get_query_template('sgl-user-single', array('sgl-user-single.php'));
                }
                break;
            case "klubbar":
                if (empty($action[1])) {
                    $template = sgl_get_query_template('sgl-clubs-archive', array('sgl-clubs-archive.php'));
                } else {
                    $term = sgl_admin_load_club_object($action[1]);
                    $template = sgl_get_query_template('sgl-clubs-single', array('sgl-clubs-single.php'));
                }
                break;
            case "tavlingar":
                if (empty($action[1])) {
                    $template = sgl_get_query_template('sgl-event-archive', array('sgl-event-archive.php'));
                } else {
                    query_posts(array('post_type' => 'sgl_event', 'post_name' => $action[1]));
                    $template = sgl_get_query_template('sgl-event-single', array('sgl-event-single.php'));
                }
                break;
            default:
		   /* Nej vi fixar med rewrite istället *
                if (empty($action[0])) {
                   $template = sgl_get_query_template('sgl-dashboard', array('sgl-dashboard.php'));
                } else {
		   
		   $template = get_404_template();
                
                break;
        }
	  */
        if ( $template = apply_filters( 'template_include', $template ) ) {
            include( $template );
            exit;
        }
    }
    
}

function sgl_admin_load_user_object($user) {
        if (is_numeric($user)) {
            $user = get_userdata($user);
        } else if (is_string($user)) {
            $user = get_user_by('slug', $user);
        } if (!is_object($user)) {
            return false;
        }
        
	unset($user->user_pass);
	unset($user->user_activation_key);
	
	$avatar_size = (!empty($_REQUEST['avatar_size'])) ? $_REQUEST['avatar_size'] : "";
	$user->avatar = get_avatar($user->ID, $avatar_size);
	
	$weightdiv = wp_get_object_terms($user->ID, 'weightdivisions');
	
	$user->weightclass = (empty($weightdiv)) ? '' : $weightdiv[0]->name;
        $user->weight = get_the_author_meta( 'weight', $user->ID );
	
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

	return $user;
}

function sgl_admin_load_club_object($club) {
        if (is_numeric($club)) {
            $club = get_term_by( 'id', $club, 'sgl_clubs' );
        } else if (is_string($club)) {
            $club = get_term_by( 'slug', $club, 'sgl_clubs' );
        } if (!is_object($club)) {
            return false;
        }
        if(!empty($club)) {
		$region = get_term(get_term_meta($club->term_id, 'sgl_region', true), 'sgl_regions');
		$club->region = (empty($region)) ? '' : $region->name;
	} else {
		$club->region = '';
	}
        $club->term_image = '<img src="' . get_bloginfo('template_url') . '/images/mystery_club.png" alt="" />';
        return $club;
}

new sgl_admin_manager;