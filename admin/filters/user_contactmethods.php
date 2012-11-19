<?php
/*
 * This file will clean upp the userprofile form,
 * and add more relevent contact methods for Sweden.
 */
function gl_contactmethods_extra($cm) {
    // Remove fields we don't want
    unset($cm['aim']);
    unset($cm['yim']);
    unset($cm['jabber']);
    // Add new fields.
    $cm['phone_daytime'] = __('Telefon Dagtid');
    $cm['phone_evening'] = __('Telefon Kvällstid');
    $cm['phone_cell'] = __('Mobiltelefon');
    $cm['facebook'] = __('Facebook');
    $cm['twitter'] = __('Twitter');
    $cm['skype'] = __('Skype');
    $cm['live'] = __('Microsoft Live Messenger');
    return $cm;
}

add_filter('user_contactmethods','gl_contactmethods_extra',10,1);
