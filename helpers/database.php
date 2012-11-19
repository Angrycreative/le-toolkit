<?php
// Helpers to do stuff with our custom tables
define('SGL_TAXONOMY_MEN', 263);
define('SGL_TAXONOMY_WOMEN', 271);


function sgl_event_add_user($event_id, $user_id, $added_by_admin = 0) {
    global $wpdb;
    $weight = $wpdb->get_row($wpdb->prepare("SELECT * FROM sgl_taxonomy_view WHERE `object_id` = %d AND `taxonomy` = '%s'", $user_id, 'weightdivisions'));
    $skill = $wpdb->get_row($wpdb->prepare("SELECT * FROM sgl_taxonomy_view WHERE `object_id` = %d AND `taxonomy` = '%s'", $user_id, 'skilldivisions'));
    // Use replace to prevent duplicates
    return $wpdb->query($wpdb->prepare("REPLACE INTO `wp-grapplingliga`.`grappling_event_users` (`event_id`, `user_id`, `weight_term_id`, `skill_term_id`, `added_by_admin`) VALUES (%d, %d, %d, %d, %d);", $event_id, $user_id, $weight->term_id, $skill->term_id, $added_by_admin));
}

function sgl_event_update_user($event_id, $user_id, $weight_term_id, $skill_term_id) {
    global $wpdb;
    $added_by_admin = $wpdb->get_var($wpdb->prepare("SELECT `added_by_admin` FROM `grappling_event_users` WHERE `event_id` = %d AND `user_id` = %d", $event_id, $user_id));
    $removed_by_admin = $wpdb->get_var($wpdb->prepare("SELECT `removed_by_admin` FROM `grappling_event_users` WHERE `event_id` = %d AND `user_id` = %d", $event_id, $user_id));
    return $wpdb->query($wpdb->prepare("REPLACE INTO `wp-grapplingliga`.`grappling_event_users` (`event_id`, `user_id`, `weight_term_id`, `skill_term_id`, `added_by_admin`, `removed_by_admin`) VALUES (%d, %d, %d, %d, %d, %d);", $event_id, $user_id, $weight_term_id, $skill_term_id, $added_by_admin, $removed_by_admin));
}

function sgl_event_delete_user($event_id, $user_id) {
    global $wpdb;
    return $wpdb->query($wpdb->prepare("DELETE FROM `wp-grapplingliga`.`grappling_event_users` WHERE `grappling_event_users`.`event_id` = %d AND `grappling_event_users`.`user_id` = %d", $event_id, $user_id));
}

function sgl_event_user_is_removed($event_id, $user_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SELECT `removed_by_admin` FROM `grappling_event_users` WHERE `event_id` = %d AND `user_id` = %d", $event_id, $user_id));
}

function sgl_event_remove_user($event_id, $user_id) {
    global $wpdb;
    return $wpdb->query($wpdb->prepare("UPDATE `wp-grapplingliga`.`grappling_event_users` SET `grappling_event_users`.`removed_by_admin` = %d WHERE `grappling_event_users`.`event_id` = %d AND `grappling_event_users`.`user_id` = %d", 1, $event_id, $user_id));
}

function sgl_event_unremove_user($event_id, $user_id) {
    global $wpdb;
    return $wpdb->query($wpdb->prepare("UPDATE `wp-grapplingliga`.`grappling_event_users` SET `grappling_event_users`.`removed_by_admin` = %d WHERE `grappling_event_users`.`event_id` = %d AND `grappling_event_users`.`user_id` = %d", 0, $event_id, $user_id));
}

function sgl_get_user_events($user_id, $more = false) {
    global $wpdb;
    if (!$more) {
        $r = $wpdb->get_col($wpdb->prepare("SELECT event_id FROM grappling_event_users WHERE user_id = %d", $user_id));
    } else {
        $r = $wpdb->get_results($wpdb->prepare("SELECT * FROM grappling_event_users WHERE user_id = %d", $user_id));
    }
    return $r;
}

function sgl_get_event_users($event_id, $more = false) {
    global $wpdb;
    if (!$more) {
        $r = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM grappling_event_users WHERE event_id = %d", $event_id));
    } else {
        $r = $wpdb->get_results($wpdb->prepare("SELECT * FROM grappling_event_users WHERE event_id = %d", $event_id));
    }
    return $r;
}

function sgl_get_club_events($club_tax) {
    global $wpdb;
    $r = $wpdb->get_results($wpdb->prepare("SELECT event_id FROM grappling_event_users AS geu LEFT JOIN grappling_term_relationships AS gtr ON geu.user_id=gtr.object_id LEFT JOIN grappling_term_taxonomy AS gtt ON gtr.term_taxonomy_id=gtt.term_taxonomy_id LEFT JOIN grappling_terms AS gt ON gtt.term_id=gt.term_id WHERE gtt.taxonomy='sgl_clubs' AND gt.slug='%s' GROUP BY geu.event_id", $club_tax));
    return $r;
}

function sgl_event_init_pools($event_id) {
    $event_users = sgl_get_event_users_sorted_by_class($event_id);
}

function sgl_get_event_users_sorted_by_class($event_id, $sort_key = "id") {
    global $wpdb;

    $event_users = sgl_get_event_users($event_id, true);

    $weightclass_ids_men = get_term_children(SGL_TAXONOMY_MEN, 'weightdivisions');
    $weightclass_ids_women = get_term_children(SGL_TAXONOMY_WOMEN, 'weightdivisions');
    $skillclass_ids = get_terms('skilldivisions', array('fields' => 'ids'));

    $men_by_weight_skill = array();
    $women_by_weight_skill = array();

    if(!empty($weightclass_ids_men) && is_array($weightclass_ids_men)) {
        
        foreach($weightclass_ids_men as $weightclass_id) {
            
            $weightclass_key = $weightclass_id;
            $weightclass_term = get_term_by('id', $weightclass_id, 'weightdivisions');
            if ($sort_key != 'id' && property_exists($weightclass_term, $sort_key)) {
                $weightclass_key = $weightclass_term->{$sort_key};
            }

            if(!array_key_exists($weightclass_key, $men_by_weight_skill) || !is_array($men_by_weight_skill[$weightclass_key])) {
                $men_by_weight_skill[$weightclass_key] = array();
            }

            if(!empty($skillclass_ids) && is_array($skillclass_ids)) {
                
                foreach($skillclass_ids as $skillclass_id) {
                    
                    $skillclass_key = $skillclass_id;
                    $skillclass_term = get_term_by('id', $skillclass_id, 'skilldivisions');
                    if ($sort_key != 'id' && property_exists($skillclass_term, $sort_key)) {
                        $skillclass_key = $skillclass_term->{$sort_key};
                    }

                    if(!array_key_exists($skillclass_key, $men_by_weight_skill[$weightclass_key]) || !is_array($men_by_weight_skill[$weightclass_key][$skillclass_key])) {
                        $men_by_weight_skill[$weightclass_key][$skillclass_key] = array();
                    }

                    foreach($event_users as $event_user) {
                        if ($event_user->weight_term_id == $weightclass_id && $event_user->skill_term_id == $skillclass_id) {
                            $men_by_weight_skill[$weightclass_key][$skillclass_key][] = get_user_by('id', $event_user->user_id);
                        }
                    }

                }
            }
            
        }
    }

    if(!empty($weightclass_ids_women) && is_array($weightclass_ids_women)) {
        
        foreach($weightclass_ids_women as $weightclass_id) {
            
            $weightclass_key = $weightclass_id;
            $weightclass_term = get_term_by('id', $weightclass_id, 'weightdivisions');
            if ($sort_key != 'id' && property_exists($weightclass_term, $sort_key)) {
                $weightclass_key = $weightclass_term->{$sort_key};
            }

            if(!array_key_exists($weightclass_key, $women_by_weight_skill) || !is_array($women_by_weight_skill[$weightclass_key])) {
                $women_by_weight_skill[$weightclass_key] = array();
            }

            if(!empty($skillclass_ids) && is_array($skillclass_ids)) {
                
                foreach($skillclass_ids as $skillclass_id) {
                    
                    $skillclass_key = $skillclass_id;
                    $skillclass_term = get_term_by('id', $skillclass_id, 'skilldivisions');
                    if ($sort_key != 'id' && property_exists($skillclass_term, $sort_key)) {
                        $skillclass_key = $skillclass_term->{$sort_key};
                    }

                    if(!array_key_exists($skillclass_key, $women_by_weight_skill[$weightclass_key]) || !is_array($women_by_weight_skill[$weightclass_key][$skillclass_key])) {
                        $women_by_weight_skill[$weightclass_key][$skillclass_key] = array();
                    }

                    foreach($event_users as $event_user) {
                        if ($event_user->weight_term_id == $weightclass_id && $event_user->skill_term_id == $skillclass_id) {
                            $women_by_weight_skill[$weightclass_key][$skillclass_key][] = get_user_by('id', $event_user->user_id);
                        }
                    }

                }
            }
            
        }
    }
    
    $men_key = SGL_TAXONOMY_MEN;
    $women_key = SGL_TAXONOMY_WOMEN;
    
    if ($sort_key == 'name' || $sort_key == 'slug') {
        $men_tax = get_term_by('id', SGL_TAXONOMY_MEN, 'weightdivisions');
        if (property_exists($men_tax, $sort_key)) {
            $men_key = $men_tax->{$sort_key};
        }
        $women_tax = get_term_by('id', SGL_TAXONOMY_WOMEN, 'weightdivisions');
        if (property_exists($women_tax, $sort_key)) {
            $women_key = $women_tax->{$sort_key};
        }
    }
    
    $sorted_users[$men_key] = $men_by_weight_skill;
    $sorted_users[$women_key] = $women_by_weight_skill;
    
    return $sorted_users;

}
