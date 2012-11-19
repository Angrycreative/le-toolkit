<?php
/* Hej jag råkade visst skriva om hela din admintemplate hantering KTXBAI */
define('SGL_ADMIN_TEMPLATES', dirname(__FILE__). '/templates');

/** Yes, this will be this way. it's now a filter **/
function sgl_admin_view_load_user_object($template_data) {

    global $wp;

    $qv_user = empty($wp->query_vars['sgl_user_profile']) ? '' : $wp->query_vars['sgl_user_profile'];
    
   
    if(empty($qv_user)) {
        return $template_data;
    } elseif(is_numeric($qv_user)) {
        $user = get_user_by('login', $qv_user);
    } elseif(is_string($qv_user)) {
        $user = get_userdata($qv_user);
    } elseif(is_object($qv_user)) {
        $user = $qv_user;
    }

    if(empty($user) || is_wp_error($user)) {
        return $template_data;
    } else {

        $user_id = $user->ID;
        $weightdiv = wp_get_object_terms($user_id, 'weightdivisions');
        $clubs = wp_get_object_terms($user_id, 'sgl_clubs');
        $skills = wp_get_object_terms($user->ID, 'skilldivisions');

        unset($user->user_pass);
        unset($user->user_activation_key);

        $user->avatar = get_avatar($user_id, (!empty($_REQUEST['avatar_size'])) ? $_REQUEST['avatar_size'] : "");
        $user->weightclass = (empty($weightdiv) || !is_array($weightdiv) || empty($weightdiv[0]->name)) ? '' : $weightdiv[0]->name;
        $user->weight = get_the_author_meta('weight', $user_id);
        $user->club = (empty($clubs) || !is_array($clubs) || empty($clubs[0]->name)) ? '' : $clubs[0]->name;
        $user->skill = (empty($skills) || !is_array($skills) || empty($skills[0]->name)) ? '' : $skills[0]->name;
        $user->region = '';

        if(!empty($clubs) && is_array($clubs) && !empty($clubs[0]->name)) {
            $region = get_term(get_term_meta($clubs[0]->term_id, 'sgl_region', true), 'sgl_regions');
            $user->region = (empty($region) || is_wp_error($region)) ? '' : $region->name;
        }

        $template_data['user'] = $user;

    }

    return $template_data;
}

function sgl_admin_view_load_club_object($template_data) {
    global $wp;

    $get_query_club = function() use (&$get_query_club) {
        global $wp;

        $qv_club = empty($wp->query_vars['sgl_club']) ? '' : $wp->query_vars['sgl_club'];
        if(empty($qv_club)) {
            return '';
        } elseif(is_numeric($qv_club)) {
            return get_term_by('slug', $qv_club, 'sgl_clubs');
        } elseif(is_string($qv_club)) {
            return get_term_by('id', $qv_club, 'sgl_clubs');
        } elseif(is_object($qv_club)) {
            return $qv_club;
        }

        return '';
    };    

    $club = $get_query_club();



    $qv_club = empty($wp->query_vars['sgl_club']) ? '' : $wp->query_vars['sgl_club'];
    if(empty($qv_club)) {
        return $template_data;
    } elseif(is_numeric($qv_club)) {
        $club = get_term_by( 'slug', $qv_club, 'sgl_clubs' );
    } elseif(is_string($qv_club)) {
        $club = get_term_by( 'id', $qv_club, 'sgl_clubs' );
    } elseif(is_object($qv_club)) {
        $club = $qv_club;
    }

    if(empty($club) || is_wp_error($club)) {
        return $template_data; 
    } else {
        if(!empty($club) && !empty($club->term_id)) {
            $club->region = get_term(get_term_meta($club->term_id, 'sgl_region', true), 'sgl_regions');
            if(is_wp_error($club->region)) {
                $club->region = '';
            }
            $club->street = get_term_meta($club->term_id, 'club_street', true);
            $club->postal = get_term_meta($club->term_id, 'club_postal', true);
            $club->city = get_term_meta($club->term_id, 'club_city', true);
            $club->url = get_term_meta($club->term_id, 'club_url', true);
            $club->phone = get_term_meta($club->term_id, 'club_phone', true);
        }

        $club->term_image = '<img src="' . get_bloginfo('template_url') . '/images/mystery_club.png" alt="" />';


        $term_query_args = array(
            'post_type' => 'sgl_event',
            'tax_query' => array(
                array(
                    'taxonomy' => 'sgl_clubs',
                    'field' => 'slug',
                    'terms' => $club->slug
                )
            )
        );

        $events = get_posts($term_query_args); // <--- this will be changed, we use this to get some data.

        $users = sgl_get_users_by_term('slug', $club->slug, 'sgl_clubs');

        $sort_events_by_year = function($events) use (&$sort_events_by_year) {
            if(!is_array($events))
                return $events;

            $sorted = array();

            foreach($events as $event) {
                $this_year = date('Y', strtotime(get_metadata('post', $event->ID, 'sgl_metabox_dates_eventdate', true)));
                if(empty($sorted[$this_year]))
                    $sorted[$this_year] = array();

                $sorted[$this_year][] = $event;
            }

            ksort($sorted);

            return $sorted;
        };

        $club->users = $users;
        $club->user_count = count($club->users);

        $events = $sort_events_by_year($events);

        $club->events = $events;
        $template_data['club'] = $club;
    }

    return $template_data;
}


function sgl_admin_view_load_events_data($template_data) {

    $get_events_by_year_and_region = function() use (&$sort_events_by_year_and_region) {
        $events = query_posts(array('post_type' => 'sgl_event'));
        $sorted = array();

        foreach($events as $event) {

            $user_ids = sgl_get_event_users($event->ID);
            $event->users = array();
            if (is_array($user_ids) && count($user_ids) > 0) {
                $users = new WP_User_Query(array('include' => $user_ids));
                $event->users = $users->get_results();
            }
            
            $this_year = date('Y', strtotime(get_metadata('post', $event->ID, 'sgl_metabox_dates_eventdate', true)));
            if(empty($sorted[$this_year]))
                $sorted[$this_year] = array();

            $regions = wp_get_object_terms($event->ID, 'sgl_regions');   
            if(!empty($regions) && !is_wp_error($regions)) {
                $current_region = $regions[0]->name;
            } else {
                $current_region = "Riks";
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


function sgl_admin_view_handle_club_edit_form($template_data) {



    $buf = array(
        'club_id'		=> $template_data['club']->term_id,
        'club_name'		=> $template_data['club']->name,
        'club_street'	=> $template_data['club']->street,
        'club_postal'	=> $template_data['club']->postal,
        'club_city'		=> $template_data['club']->city,
        'club_phone'		=> $template_data['club']->phone,
        'club_url'		=> $template_data['club']->url,
        'sgl_regions'	=> empty($template_data['club']->region->term_id) ? '' : abs($template_data['club']->region->term_id)
    );

    $template_data['club_values'] = $buf;

    return $template_data;
}

function sgl_admin_view_create_user_posthook() {
    $errors = new WP_Error();

    $nonce_error = function() use (&$nonce_error) {
        echo "<h1>I'm sorry, Dave. I'm afraid I can't do that.</h1><br/>";
        echo '<iframe width="420" height="315" src="http://www.youtube.com/embed/9W5Am-a_xWw" frameborder="0" allowfullscreen></iframe>';
    };

    $nonce_valid = wp_verify_nonce($_POST['spam_and_bacon'], 'create-user');
    $nonce_extra_valid = wp_verify_nonce($_POST['spam_and_bacon_with_eggs'], 'hie2shieSi');

    if(($nonce_valid !== true || $nonce_extra_valid !== true))
        wp_die($nonce_error());

    if(empty($_POST['user_login']) && empty($_POST['user_id'])) {
        $errors->add('user_login', 'Du måste ange ett användarnamn');
    }

    if(!empty($_POST['user_login']) && empty($_POST['user_id']))  {
        $user = get_user_by('login', $_POST['user_login']);
        if(!empty($user))
            $errors->add('user_login', 'Användarnamnet är redan upptaget');

        if(filter_var($_POST['user_login'], FILTER_VALIDATE_EMAIL))
            $errors->add('user_login', 'Du får inte använda en epost som inloggning');

        if(strstr(trim($_POST['user_login']), ' '))
            $errors->add('user_login', 'Du får inte använda mellanslags i användarnamnet');

    }

    if(empty($_POST['first_name']))
        $errors->add('first_name', 'Du måste ange ditt förnamn');

    if(empty($_POST['last_name']))
        $errors->add('last_name', 'Du måste ange ditt efternamn');

    if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))
        $errors->add('email', 'Du måste ange en giltig E-post adress');

    if(!empty($_POST['user_id']))
        $_POST['email_1'] = $_POST['email'];

    if($_POST['email'] !== $_POST['email_1'])
        $errors->add('email', 'Du måste ange samma E-post adress två gånger');

    if(!empty($_POST['email']) && empty($_POST['user_id'])) {
        $user = get_user_by('email', $_POST['email']);
        if(!empty($user))
            $errors->add('email', 'En användare finns redan med den här epost adressen');
    }

    if(empty($_POST['pass1']) && empty($_POST['user_id']))
        $errors->add('password', 'Du måste ange ett lösenord');

    if($_POST['pass1'] !== $_POST['pass2'])
        $errors->add('password', 'Du måste ange samma lösenord två gånger');

    if(empty($_POST['sex']))
        $errors->add('sex', "Du måste ange vilket kön du har");

    $_POST['weight'] = abs($_POST['weight']);
    if(empty($_POST['weight']))
        $errors->add('weight', 'Du måste ange din vikt');

    if(empty($_POST['sgl_clubs']))
        $errors->add('sgl_clubs', "Du måste välja vilken klubb du tillhör.");

    if(empty($_POST['skilldivisions']))
        $errors->add('skilldivisions', 'Du måste ange din erfarenhetsnivå');

    $error_codes = $errors->get_error_codes();
    $values = $_POST;

    if(!empty($error_codes)) {
        $template = sgl_get_query_template('sgl-profile-new');

        if($template = apply_filters('template_include', $template)) {
            require($template);
            exit;
        }
    } else {
        $user_data = array(
            'user_nicename'	=> sprintf('%s %s', $values['first_name'], $values['last_name']),
            'user_url'	=> $values['url'],
            'user_email'	=> $values['email'],
            'display_name'	=> sprintf('%s %s', $values['first_name'], $values['last_name']),
            'first_name'	=> $values['first_name'],
            'last_name'	=> $values['last_name'],
            'description'	=> $values['description'],
            'weight'		=> $values['weight'],
            'sex'		=> $values['sex']
        );

        if(!empty($values['user_login'])) {
            $user_data['nickname'] = $values['user_login'];
            $user_data['user_login'] = strtolower($values['user_login']);
        }

        if(!empty($values['pass1'])) {
            $user_data['user_pass'] = $values['pass1'];
        }

        if(!empty($values['user_id']))
            $user_data['ID'] = $values['user_id'];

        foreach(_wp_get_user_contactmethods() as $name => $desc) {
            $user_data[$name] = $values[$name];
        }

        $user_id = wp_update_user($user_data);
        if(is_wp_error($user_id)) {

            $template = sgl_get_query_template('sgl-profile-new');

            if($template = apply_filters('template_include', $template)) {
                require($template);
                exit;
            }
        } else {
            update_user_meta($user_id, 'sex', $values['sex']);
            update_user_meta($user_id, 'weight', $values['weight']);
            set_sql_weightclass($user_id, $_POST['sex'], abs($_POST['weight']));


            if(!empty($_POST['skilldivisions'])) {

                // wp_set_object_terms is stupid.
                $reg = (array)$_POST['skilldivisions'];
                $reg = array_map('intval', $reg);
                $reg = array_unique( $reg );

                wp_set_object_terms($user_id, $reg, 'skilldivisions');
            }

            if(!empty($_POST['sgl_clubs'])) {

                // wp_set_object_terms is stupid.
                $reg = (array)$_POST['sgl_clubs'];
                $reg = array_map('intval', $reg);
                $reg = array_unique( $reg );

                wp_set_object_terms($user_id, $reg, 'sgl_clubs');
            }

            if(!empty($values['user_id'])) {
                wp_safe_redirect(bloginfo('url') . '/admin/anvandare/visa/'. $values['user_login']. "?saved=true");    
                exit;
            } else {
                wp_safe_redirect($_SERVER['REQUEST_URI'] . "?saved=true");
                exit;
            }
        }
    }
}

function sgl_views_admin_load_profile_data($username) {
    $user = get_user_by('login', $username);

    $get_current_club_id = function($user_id) use (&$get_current_club_id) {
        $clubs = wp_get_object_terms($user_id, 'sgl_clubs');
        if(!empty($clubs) && !is_wp_error($clubs)) 
            return $clubs[0]->term_id;
        else
            return '';
    };

    $get_current_skill_id = function($user_id) use (&$get_current_skill_id) {
        $clubs = wp_get_object_terms($user_id, 'skilldivisions');
        if(!empty($clubs) && !is_wp_error($clubs)) 
            return $clubs[0]->term_id;
        else
            return '';
    };

    $data = array(
        'user_id'		=> $user->ID,
        'user_login'		=> $user->user_login,
        'first_name'		=> $user->first_name,
        'last_name'		=> $user->last_name,
        'email'		=> $user->user_email,
        'url'			=> $user->user_url,
        'weight'		=> $user->weight,
        'sex'			=> $user->sex,
        'description'	=> $user->description,
        'sgl_clubs'		=> $get_current_club_id($user->ID),
        'skilldivisions'	=> $get_current_skill_id($user->ID),
        'weightclass'	=> get_sgl_weightclass2($user->ID)
    );

    foreach(_wp_get_user_contactmethods() as $name => $desc) {
        $data[$name] = $user->$name;
    }


    return $data;


}

function sgl_admin_view_handle_user_not_found($template_data) {
    if(empty($template_data['user']) ||
        !is_object($template_data['user']) ||
        is_wp_error($template_data['user']))
    {
        $template = sgl_get_query_template('sgl-user-notfound');

        if($template = apply_filters('template_include', $template)) {
            require($template);
            exit;
        }
    }

    return $template_data;
}

function sgl_admin_view_store_club_data() {

    $nonce_error = function() use (&$nonce_error) {
        echo "<h1>I'm sorry, Dave. I'm afraid I can't do that.</h1><br/>";
        echo '<iframe width="420" height="315" src="http://www.youtube.com/embed/9W5Am-a_xWw" frameborder="0" allowfullscreen></iframe>';
    };

    $nonce_valid = (bool)wp_verify_nonce($_POST['spam_and_bacon'], 'create-edit-club');
    $nonce_extra_valid = (bool)wp_verify_nonce($_POST['spam_and_bacon_with_eggs'], 'hie2shieSi');


    if(($nonce_valid !== true || $nonce_extra_valid !== true))
        wp_die($nonce_error());

    if(empty($_POST['club_id'])) {
        $term_id = wp_insert_term($_POST['club_name'], 'sgl_clubs');
    } else {
        $args = array(
            'name'	=> $_POST['club_name'],
            'slug'	=> sanitize_title($_POST['clubname'])
        );
        $term_id = wp_update_term($_POST['club_id'], 'sgl_clubs', $args);
    }


    if(!is_wp_error($term_id)) {
        update_term_meta($term_id['term_id'], 'sgl_region', $_POST['sgl_regions']);
        update_term_meta($term_id['term_id'], 'club_street', $_POST['club_street']);
        update_term_meta($term_id['term_id'], 'club_postal', $_POST['club_postal']);
        update_term_meta($term_id['term_id'], 'club_city', $_POST['club_city']);
        update_term_meta($term_id['term_id'], 'club_phone', $_POST['club_phone']);
        update_term_meta($term_id['term_id'], 'club_url', $_POST['club_url']);

        $term = get_term($term_id['term_id'], 'sgl_clubs');
        wp_safe_redirect(bloginfo('url') .'/admin/klubbar/visa/'. $term->slug ."?saved=true");
        exit;
    }

    wp_die(var_dump($term_id));
}

function sgl_dummy_post_handler() {
    echo "<pre>";
    var_dump($_POST);
    wp_die("SMURF!");
}

function sgl_admin_view_load_typeahead($template_data) {

    $gimme_names_only = function($item) use (&$gimme_names_only) {
        return '"'. $item->name .'"';
    };

    $clubs = get_terms('sgl_clubs');
    $clubs = array_map($gimme_names_only, $clubs);
    $clubs = '['. implode(",", $clubs) . ']';
    $template_data['clubs_typeahead'] = $clubs;

    $regions = get_terms('sgl_regions');
    $regions = array_map($gimme_names_only, $regions);
    $regions = "[". implode(',', $regions) ."]";
    $template_data['regions_typeahead'] = $regions;

    return $template_data;
}

function sgl_admin_store_new_event_posthook() {
    
    $nonce_error = function() use (&$nonce_error) {
        return "<h1>I'm sorry, Dave. I'm afraid I can't do that.</h1><br/>" .
        '<iframe width="420" height="315" src="http://www.youtube.com/embed/9W5Am-a_xWw" frameborder="0" allowfullscreen></iframe>';
    };

    $nonce_valid = (bool)wp_verify_nonce($_POST['spam_and_bacon'], 'create-edit-event');
    $nonce_extra_valid = (bool)wp_verify_nonce($_POST['spam_and_bacon_with_eggs'], 'hie2shieSi');
    
    if(($nonce_valid !== true || $nonce_extra_valid !== true))
        wp_die($nonce_error());

    $event = array(
        'post_type'     => 'sgl_event',
        'post_title'    => $_POST['event_name'],
        'post_status'   => 'publish',
        'tax_input'     => array('sgl_regions' => $_POST['sgl_regions'])
    );

    if(!empty($_POST['event_id'])) {
        $event['ID'] = intval($_POST['event_id']);
        $event_id = wp_update_post( $event, true );
    } else {
        $event_id = wp_insert_post( $event, true );
    }
    
    if(!is_wp_error($event_id)) {
        update_post_meta($event_id, "sgl_metabox_basicinfo_eventinfo", $_POST['description']);
        update_post_meta($event_id, "sgl_metabox_basicinfo_numparticipants", $_POST['num_participants']);
        update_post_meta($event_id, "sgl_metabox_extra_info_num_mats", $_POST['num_mats']);
        update_post_meta($event_id, "sgl_metabox_extra_info_registrationfee", $_POST['registrationfee']);
        update_post_meta($event_id, "sgl_metabox_extra_info_visitorfee", $_POST['visitorfee']);
        update_post_meta($event_id, "sgl_metabox_dates_eventdate", $_POST['event_date']);
        update_post_meta($event_id, "sgl_metabox_dates_regopendate", $_POST['event_reg_open_date']." ".$_POST['event_reg_open_time']);
        update_post_meta($event_id, "sgl_metabox_dates_regenddate", $_POST['event_reg_close_date']." ".$_POST['event_reg_close_time']);
    } else {
        wp_die(var_dump($event_id));
    }
    
    $event = get_post($event_id);
    wp_safe_redirect(bloginfo('url') .'/admin/tavlingar/visa/'. $event->post_name ."?saved=true");
    
}

function sgl_admin_view_load_event_object($template_data) {
    global $wp;

    if (!isset($wp->query_vars['sgl_event'])) {
        wp_die(var_dump($wp->query_vars));
    }
    
    $query_args = array(
	'post_type' => 'sgl_event',
	'post_status' => 'publish'
    );
    
    $qv_event = empty($wp->query_vars['sgl_event']) ? '' : $wp->query_vars['sgl_event'];
    
    if(empty($qv_event)) {
        return $template_data;
    } elseif(is_numeric($qv_event)) {
        $query_args['p'] = $qv_event;
    } elseif(is_string($qv_event)) {
        $query_args['name'] = $qv_event;
    } elseif(is_object($qv_event)) {
        $event = $qv_event;
    }
    
    
    
    if (!empty($query_args)) {
        $query = new WP_Query($query_args);
        $result = $query->get_posts();
    } else if (isset($event) && is_object($event)) {
        $result = array($event);
    } else {
        wp_die(var_dump($wp->query_vars));
    }
    
    $load_meta = function(&$event) use (&$load_meta) {
        
        $regions = wp_get_object_terms($event->ID, 'sgl_regions');
        if (!is_wp_error($regions) && is_array($regions) && !empty($regions[0])) {
            $event->region = $regions[0]->name;
            $event->region_slug = $regions[0]->slug;
        } else {
            $event->region = "Riks";
            $event->region_slug = "riks";
        }
    
        $pt = get_transient('sgl_posttypes');
        $meta_fields = $pt[$event->post_type]['metadata'];
        
        foreach($meta_fields as $k => $v ) {
            $key = sprintf('sgl_metabox_%s_%s', $v['metabox'], $k);
            $event->$k = get_post_meta($event->ID, $key, true);
        }
        
        $event->year = date('Y', strtotime(get_metadata('post', $event->ID, 'sgl_metabox_dates_eventdate', true)));
	
    };
    
    if (!is_array($result)) {
        wp_die(var_dump($result));
    }
    
    array_map($load_meta, $result);

    $template_data['event'] = $result[0];
    
    $today = strtotime(date("Y-m-d H:i"));
    $expiration_date = strtotime($template_data['event']->regenddate);
    
    if ($expiration_date > $today) {
        $template_data['event']->has_expired = false;
    } else {
        $template_data['event']->has_expired = true;
    }

    return $template_data;
}

function sgl_admin_view_load_event_users($template_data) {
    
    if (!isset($template_data['event']) || !is_array($template_data['event']) || count($template_data['event']) <= 0) {
        $template_data = array_merge($template_data, sgl_admin_view_load_event_object($template_data));
    }
    
    if (!$template_data['event']->ID) {
        echo "<pre>";
        wp_die(var_dump($template_data));
    }
    
    $user_ids = sgl_get_event_users($template_data['event']->ID);

    if(empty($user_ids)) {
        $template_data['event']->users = array();
        $template_data['event']->user_count = 0;
    } else {
        $users = array();
        foreach($user_ids as $user_id) {
                $users[] = $user_id;
        }
        $users = new WP_User_Query(array('include' => $users));
        $template_data['event']->users = $users->get_results();
        $template_data['event']->user_count = count($template_data['event']->users);
    }
    
    return $template_data;
}

function sgl_admin_view_load_sorted_event_users($template_data) {
    
    if (!isset($template_data['event']) || !is_array($template_data['event']) || count($template_data['event']) <= 0) {
        $template_data = array_merge($template_data, sgl_admin_view_load_event_object($template_data));
    }
    
    if (!$template_data['event']->ID) {
        echo "<pre>";
        wp_die(var_dump($template_data));
    }

    $template_data['event']->users = sgl_get_event_users_sorted_by_class($template_data['event']->ID, 'id');
    
    return $template_data;
}

sgl_views_register_admin_view('sgl-dashboard',              'admin', 'dashboard'    );
sgl_views_register_admin_view('sgl-user-archive',           'users', 'archive'      );
sgl_views_register_admin_view('sgl-profile-new',            'users', 'create_user'  );
sgl_views_register_admin_view('sgl-user-single',            'users', 'single',      array('sgl_admin_view_load_user_object', 'sgl_admin_view_handle_user_not_found'));
sgl_views_register_admin_view('sgl-profile-new',            'users', 'edit_user',   'sgl_admin_view_load_user_object');

sgl_views_register_admin_view('sgl-clubs-archive',          'clubs', 'archive'      );
sgl_views_register_admin_view('sgl-clubs-single',           'clubs', 'single',      'sgl_admin_view_load_club_object');
sgl_views_register_admin_view('sgl-clubs-new-edit',         'clubs', 'create_club'  );
sgl_views_register_admin_view('sgl-clubs-new-edit',         'clubs', 'edit_club',   array('sgl_admin_view_load_club_object', 'sgl_admin_view_handle_club_edit_form') );

sgl_views_register_admin_view('sgl-event-archive',          'event', 'archive', 'sgl_admin_view_load_events_data');
sgl_views_register_admin_view('sgl-event-single',           'event', 'single',  array('sgl_admin_view_load_event_object', 'sgl_admin_view_load_event_users'));
sgl_views_register_admin_view('sgl-event-new',              'event', 'new',     'sgl_admin_view_load_typeahead');

sgl_views_register_admin_view('sgl-event-registration',     'event', 'registration', array('sgl_admin_view_load_event_object', 'sgl_admin_view_load_event_users'));
sgl_views_register_admin_view('sgl-event-matchpools-new',   'event', 'matchpools', array('sgl_admin_view_load_event_object', 'sgl_admin_view_load_sorted_event_users'));
sgl_views_register_admin_view('sgl-event-participants',     'event', 'participants', array('sgl_admin_view_load_event_object', 'sgl_admin_view_load_sorted_event_users'));
sgl_views_register_admin_view('sgl-event-matchresults',     'event', 'matchresults', array('sgl_admin_view_load_event_object', 'sgl_admin_view_load_sorted_event_users'));

sgl_views_admin_register_posthooks('users',                 'create_user',  'sgl_admin_view_create_user_posthook');	
sgl_views_admin_register_posthooks('users',                 'edit_user',    'sgl_admin_view_create_user_posthook');
sgl_views_admin_register_posthooks('clubs',                 'create_club',  'sgl_admin_view_store_club_data');
sgl_views_admin_register_posthooks('clubs',                 'edit_club',    'sgl_admin_view_store_club_data');
sgl_views_admin_register_posthooks('event',                 'matchpools',   'sgl_dummy_post_handler');
sgl_views_admin_register_posthooks('event',                 'new',          'sgl_admin_store_new_event_posthook');