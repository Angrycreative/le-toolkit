<?php

add_action('ev_delete_expired_db_transients', 'delete_expired_db_transients');

function cleanup_transients_activation() {
    if ( !wp_next_scheduled( 'ev_delete_expired_db_transients' ) ) {
        wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'ev_delete_expired_db_transients');
    }
}
add_action('wp', 'cleanup_transients_activation');

function delete_expired_db_transients() {

    global $wpdb, $_wp_using_ext_object_cache;

    if( $_wp_using_ext_object_cache )
        return;

    $time = isset ( $_SERVER['REQUEST_TIME'] ) ? (int)$_SERVER['REQUEST_TIME'] : time() ;
    $expired = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout%' AND option_value < {$time};" );

    foreach( $expired as $transient ) {

        $key = str_replace('_transient_timeout_', '', $transient);
        delete_transient($key);
    }
}
