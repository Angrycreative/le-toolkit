<?php

function sgl_register_query_vars($queries) {
        $raw_data = yaml_parse_file(ACP_SCHEMA_DIR . '/query_triggers.yaml');
        
        if(!empty($raw_data) && is_array($raw_data)) {
            foreach($raw_data as $var) {
                array_push($queries, $var);
            }
        }
	return $queries;
}

add_filter('query_vars', 'sgl_register_query_vars');