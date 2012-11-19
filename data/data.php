<?php
define('ACP_SCHEMA_DIR', dirname(__FILE__) .'/schemas');
require('helpers/register_query_vars.php');

class AngryCustomPosttypes {

    private $_postTypes;
    private $_taxonomies;

    private $_userHaxx;

    public function __construct() {
        global $acp_tax_needs_user_haxx;
        $this->_postTypes = array();
        $this->_taxonomies = array();


        $this->_loadSchemas();
        add_action('init', array(&$this, 'registerTaxonomies'));
        add_action('init', array(&$this, 'registerPosttypes'));

        add_action('admin_init', array(&$this, 'registerMetaBoxes'));

        add_action('save_post', array(&$this, 'savePostMeta'));

        add_filter('post_type_link', array(&$this, 'fixPostTypeLink'), 1, 3);
        add_filter('generate_rewrite_rules', array(&$this, 'registerRewriteRules'));
        add_filter('init', array(&$this, 'flushRewriteRules'));

        // if($this->_havePostTypes) {
        //add_action('init', array(&$this, 'registerPosttypes'));
        //add_action('save_post', array(&$this, 'savePostMeta'));
        //}

    }

    public function fixPosttypeLink($post_link, $post = 0, $leave_name = false) {
        // Sanity checks
        if(is_object($post)) {
            $post_id = $post->ID;
        } else {
            $post_id = $post;
            $post = get_post($post_id);
        }

        if(!is_object($post)) {
            return $post_link;
        }

        // Do we care?
        if(!array_key_exists($post->post_type, $this->_postTypes))
            return $post_link; // No, we don't, give the link back

        if(strpos($post_link, '%sgl_region%') !== false) {
            $term = wp_get_post_terms($post_id, 'sgl_regions');
            $region = '';
            if(!empty($term))
                $region = $term[0]->slug;

            $post_link = str_replace('%sgl_region%', $region, $post_link);
        }

        return $post_link;
    }

    public function flushRewriteRules() {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }

    public function registerRewriteRules($wp_rewrite) {
        $new_rules = array();

        $rules = yaml_parse_file(ACP_SCHEMA_DIR . '/rewrites.yaml');
        
        
        foreach($rules as $key => $value) {
            $new_rules[$key] = $value;
        }

        $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

        return $wp_rewrite->rules;
    }

    public function registerMetaBoxes() {
        $uri = acp_parse_admin_uri();

        sgl_register_script('anytime', 'js/anytime.js', '1.0', false);
        sgl_register_style('anytime', 'css/anytime.css', false);

        switch($uri['pageName']) {
        case 'post.php':
            $post = get_post($uri['queryVars']['post']);
            if(!empty($post))
                $this->_loadMetaBoxes($post->post_type);
            break;
        case 'post-new.php':
            $this->_loadMetaBoxes($uri['queryVars']['post_type']);
            break;
        }

    }

    public function registerPosttypes() {
        if(empty($this->_postTypes))
            return;

        foreach($this->_postTypes as $key => $value) {
            register_post_type($key, $value['posttype']);
        }
    }

    public function registerTaxonomies() {
        if(empty($this->_taxonomies))
            return;

        foreach($this->_taxonomies as $tax_key => $tax_item) {
            $args = $tax_item['arguments'];
            $args['labels'] = $tax_item['labels'];

            if(!empty($tax_item['object_type'])) {

                if($tax_item['object_type'] == 'user') {
                    $args['update_count_callback'] = 'acp_update_user_taxonomy_count';

                    // Register this action, once.
                    if(!defined('GL_NEEDS_USER_WORKAROUND')) {
                        define('GL_NEEDS_USER_WORKAROUND', true);
                        add_action('admin_menu', array(&$this, 'AddUserTaxonomyAdminPages'));
                    }   
                }
                register_taxonomy($tax_key, $tax_item['object_type'], $args);

            } else {
                register_taxonomy($tax_key, 'post', $args);
            }

            if(!empty($tax_item['default_items'])) {
                $this->_registerTerms($tax_item['default_items'], $tax_key);
            }
        }
    }

    public function renderMetaBox($post, $metabox) {
        $fields = $this->_postTypes[$post->post_type]['metadata'];

        dbgx_trace_var($this->_postTypes[$post->post_type]);
        echo '<table class="form-table">';
        foreach($fields as $k => $v) {
            if(empty($v['metabox']))
                continue;

            if($metabox['args'][1] != $v['metabox'])
                continue;

            $prefix = sprintf("sgl_metabox_%s_", $v['metabox']);
            $dateformat = empty($v['widget']['dateformat']) ? '' : $v['widget']['dateformat'];

            $vars = array(
                '%id%'          => sprintf("%s%s", $prefix, $k),
                '%name%'        => $v['widget']['label'],
                '%type%'        => $v['widget']['type'],
                '%class%'       => '',
                '%desc%'        => $v['widget']['placeholder'],
                '%dateformat%'  => $dateformat,
                '%value%'       => get_post_meta($post->ID, sprintf("%s%s", $prefix, $k), true)
            );


            switch($v['widget']['type']) {
            default:
            case 'text':
                echo strtr('
                    <tr>
                    <th scope="row" style="width: 140px">
                    <label for="%id%">%name%</label>
                    </th>
                    <td><input
                    id="%id%"
                    type="%type%"
                    name="%id%"
                    class="text large-text %class%"
                        value="%value%" />
                        <span class="description">%desc%</span>
                        </td>
                        </tr>
                        ', $vars);
                break;
            case 'datepicker':
                echo strtr('
                    <tr>
                    <th scope="row" style="width: 140px">
                    <label for="%id%">%name%</label>
                    </th>
                    <td><input
                    id="%id%"
                    type="text"
                    name="%id%"
                    class="text large-text %class%"
                        value="%value%" />
                        <span class="description">%desc%</span>
                        <script type="text/javascript">jQuery(function() { AnyTime.picker("%id%", { format: "%dateformat%", placement: "popup" }); });</script>
</td>
</tr>
', $vars);
break;
case 'textbox':
case 'textarea':
    echo strtr('
        <tr>
        <th scope="row" style="width: 140px">
        <label for="%id%">%name%</label>
        </th>
        <td><textarea
        id="%id%"
        name="%id%"
        class="large-text %class%">%value%</textarea>
            <span class="description">%desc%</span>
            </td>
            </tr>', $vars);
    break;
case 'taxonomydropdown':
    $current_term = wp_get_object_terms($post->ID, $v['widget']['taxonomy']);
    dbgx_trace_var($v['widget']['taxonomy'], "mjew");
    if(!empty($current_term) && is_array($current_term)) {
        $current_term = $current_term[0]->term_id;
    } else {
        $current_term = '';
    }

    $terms = get_terms($v['widget']['taxonomy'], 'hide_empty=0');
    $items = '';
    foreach($terms as $term) {
        if($current_term == $term->term_id) {
            $items .= sprintf('<option value="%s" selected="selected">%s</option>', $term->term_id, $term->name);
        } else {
            $items .= sprintf('<option value="%s">%s</option>', $term->slug, $term->name);
        }
    }
    echo strtr('
        <tr>
        <th scope="row" style="width: 140px">
        <label for="%id%">%name%</label>
        </th>
        <td><select
        id="%id%" 
        name="%id%"
        class="%class%">
            '. $items .'
            </select>
            <span class="description">%desc%</span>
            </td>
            </tr>
            ', $vars);
    break;
            }
        }

        echo '</table>';
    }

    private function _loadMetaBoxes($post_type) {
        if(empty($post_type))
            return;

        if(!array_key_exists($post_type, $this->_postTypes))
            return;

        if(empty($this->_postTypes[$post_type]['metaboxes']))
            return;

        if(empty($this->_postTypes[$post_type]['metadata']))
            return;

        foreach($this->_postTypes[$post_type]['metaboxes'] as $k => $v) {
            $name = sprintf('cpt_%s_meta_%s', $post_type, $k);

            add_meta_box(
                $name,
                $v['name'],
                array(&$this, 'renderMetaBox'),
                $post_type,
                'normal',
                'low',
                array('post_type' => $post_type, 'metabox_key', $k)
            );
        }

    }

    private function _registerTerms(&$items, &$taxonomy, &$parent = NULL) {

        if(is_array($items)) {
            foreach($items as $k => $v) {
                if(!is_array($v)) {
                    if(empty($parent)) {
                        if(!term_exists($v, $taxonomy))
                            wp_insert_term($v, $taxonomy);
                    } else {
                        if(!term_exists($v, $taxonomy, $parent))
                            wp_insert_term($v, $taxonomy, array('parent' => $parent));
                    }
                } else {
                    if(!term_exists($k, $taxonomy)) {
                        $p = wp_insert_term($k, $taxonomy);
                    } else {
                        $p = term_exists($k, $taxonomy);
                    }
                    if(!$p == 0)
                        $this->_registerTerms($v, $taxonomy, $p['term_id']);
                }
            }
        }
    }


    /**
     * Creates the admin page for the 'profession' taxonomy under the 'Users' menu.  It works the same as any
     * other taxonomy page in the admin.  However, this is kind of hacky and is meant as a quick solution.  When
     * clicking on the menu item in the admin, WordPress' menu system thinks you're viewing something under 'Posts'
     * instead of 'Users'.  We really need WP core support for this.
     */
    public function AddUserTaxonomyAdminPages() {
        foreach($this->_taxonomies as $tax_key => $tax_item) {
            if(!empty($tax_item['object_type'])) {
                if($tax_item['object_type'] == 'user') {
                    $tax = get_taxonomy($tax_key);

                    add_users_page(
                        esc_attr( $tax->labels->menu_name ),
                        esc_attr( $tax->labels->menu_name ),
                        $tax->cap->manage_terms,
                        'edit-tags.php?taxonomy=' . $tax->name
                    );
                }
            }
        }
    }

    public function savePostMeta($post_id) {
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;
        if(empty($_POST['post_type'])) {
            return;
        } if('page' == $_POST['post_type']) {
            if(!current_user_can('edit_page', $post_id))
                return;
        } else if(!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Fail if this isn't our posttype.
        if(!array_key_exists($_POST['post_type'], $this->_postTypes))
            return;

        $metadata = $this->_postTypes[$_POST['post_type']]['metadata'];


        foreach($metadata as $k => $v) {
            $post_key = sprintf('sgl_metabox_%s_%s', $v['metabox'], $k);
            $post_value = $_POST[$post_key];

            switch($v['widget']['type']) {
            case 'taxonomydropdown':
                if(false !== ($term = term_exists($post_value)))
                    wp_set_post_terms($post_id, $term, $v['widget']['taxonomy'], false);

                break;
            case 'taxonomycheckbox':
                $taxonomy = $v['widget']['taxonomy'];
                $items = array_keys($post_value);
                wp_set_post_terms($post_id, $items, $taxonomy, false);

                break;
            default:
                if(!empty($post_value)) {
                    update_post_meta($post_id, $post_key, $post_value);
                } else {
                    delete_post_meta($post_id, $post_key);
                }
                break;
            }
        }


    }

    private function _loadSchemas() {
        if(false === ($this->_taxonomies = get_transient('sgl_taxonomies'))) {
            if($schema_dir = opendir(ACP_SCHEMA_DIR .'/taxonomies')) {
                while(false !== ($entry = readdir($schema_dir))) {
                    if($entry != '.' && $entry != '..' && !is_dir($entry)) {
                        $raw_data = yaml_parse_file(ACP_SCHEMA_DIR .'/taxonomies/'. $entry);
                        if(is_array($raw_data)) {
                            $key = array_keys($raw_data);

                            $this->_taxonomies[$key[0]] = $raw_data[$key[0]];
                        }
                    }
                }
                closedir($schema_dir);

                if(!empty($this->_taxonomies))
                    set_transient('sgl_taxonomies', $this->_taxonomies, 43200);
            }
        }

        if(false === ($this->_postTypes = get_transient('sgl_posttypes'))) {
            if($schema_dir = opendir(ACP_SCHEMA_DIR .'/posttypes')) {
                while(false !== ($entry = readdir($schema_dir))) {
                    if($entry != '.' && $entry != '..' && !is_dir($entry)) {
                        $raw_data = yaml_parse_file(ACP_SCHEMA_DIR .'/posttypes/'. $entry);
                        if(is_array($raw_data)) {
                            $key = array_keys($raw_data);

                            $this->_postTypes[$key[0]] = $raw_data[$key[0]];
                        }
                    }
                }
                closedir($schema_dir);

                if(!empty($this->_postTypes))
                    set_transient('sgl_posttypes', $this->_postTypes, 43200);
            }
        }
    }


}
new AngryCustomPosttypes;

function acp_update_user_taxonomy_count($terms, $taxonomy) {
    global $wpdb;

    foreach ( (array) $terms as $term ) {

        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );

        do_action( 'edit_term_taxonomy', $term, $taxonomy );
        $wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
        do_action( 'edited_term_taxonomy', $term, $taxonomy );
    }
}


/**
 * Now this is an elegant hack.
 * Let's make our custom user taxonomies work in the admin menu!
 */
function acp_user_haxx_add_filter() {
    if(!empty($_GET['post_type']))
        return;

    if(!empty($_GET['taxonomy'])) {

        $taxonomies = get_transient('sgl_taxonomies');

        foreach($taxonomies as $tax_key => $tax_item) {
            if(!empty($tax_item['object_type'])) {
                if($tax_item['object_type'] == 'user' && $tax_key == $_GET['taxonomy']) {
                    add_filter('parent_file', 'acp_user_haxx_change_parent_to',999);
                    /* Fix columns */
                    add_filter( 'manage_edit-'. $tax_key .'_columns', 'acp_user_haxx_fix_tax_columns');
                    add_action( 'manage_'. $tax_key .'_custom_column', 'acp_user_haxx_fix_tax_columns_count', 10, 3 );
                }
            }
        }
    }
}

function acp_user_haxx_fix_tax_columns_count( $display, $column, $term_id ) {
    if(!empty($_GET['post_type']))
        return;

    if ( 'users' === $column ) {
        $term = get_term( $term_id, $_GET['taxonomy']);
        echo $term->count;
    }
}

function acp_user_haxx_fix_tax_columns($columns) {
    if(!empty($_GET['post_type']))
        return;

    unset( $columns['posts'] );
    $columns['users'] = __( 'Users' );
    return $columns;
}

function acp_user_haxx_change_parent_to() { return 'users.php'; }

function acp_parse_admin_uri() {
    $uri = explode("/", $_SERVER['REQUEST_URI']);
    $uri = explode("?", end($uri));
    $qvars = array();

    if(!empty($uri[1]))
        $qvarbuf = explode("&", $uri[1]); 

    if(!empty($qvarbuf)) {
        foreach($qvarbuf as $qv) {
            $qv = explode('=', $qv);

            if(!empty($qv) && is_array($qv)) {
                if(!empty($qv[1]))
                    $qvars[$qv[0]] = $qv[1];
            }
        }    
    }

    return array('pageName' => $uri[0], 'queryVars' => $qvars);
}


acp_user_haxx_add_filter();

