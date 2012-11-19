<?php

class sgl_views {

    private static $_instance;

    private $_admin_views;
    private $_admin_post_hooks;
    private $_user_views;
    private $_user_post_hooks;

    private function __construct() {
        $this->_admin_views = array();
        $this->_admin_post_hooks = array();
        $this->_user_views = array();
        $this->_user_post_hooks = array();   
    }

    public static function get_instance() {
        if(!self::$_instance) {
            self::$_instance = new sgl_views();
        }

        return self::$_instance;
    }

    /**
     * (string) $template_name - the name of the template (ex sgl-user-archive)
     * (string) $admin_action - the action you care about (ex 'anvandare')
     * (string) (optional) $admin_view - the subview (Ex new)
     * (string) (optional) $callback - callback action you want to run before you view is displayed
     */
    public function register_admin_view($template_name, $admin_action, $admin_view, $callback) {

        if(empty($this->_admin_views[$admin_action])) {
            $this->_admin_views[$admin_action] = array();
        }

        if(!empty($admin_view)) {
            if(empty($this->_admin_views[$admin_action][$admin_view])) {
                $this->_admin_views[$admin_action][$admin_view] = array();
            }

            $this->_admin_views[$admin_action][$admin_view]['template'] = $template_name;
            if(!empty($callback)) {
                $this->_admin_views[$admin_action][$admin_view]['callback'] = (array)$callback;
            }
        } 

        return self::$_instance;
    }

    public function register_admin_posthooks($admin_action, $admin_view, $callbacks) {

        if(empty($this->_admin_post_hooks[$admin_action]))
            $this->_admin_post_hooks[$admin_action] = array();

        if(empty($this->_admin_post_hooks[$admin_action][$admin_view]))
            $this->_admin_post_hooks[$admin_action][$admin_view] = array();

        foreach((array)$callbacks as $callback) {
            if(!in_array($callback, $this->_admin_post_hooks[$admin_action][$admin_view]))
                $this->_admin_post_hooks[$admin_action][$admin_view][] = $callback;
        }

        return $this;
    }

    public function get_admin_template($admin_action, $admin_view = '') {

        if(empty($admin_view)) {
            if(empty($this->_admin_views[$admin_action]))
                return array();

            return $this->_admin_views[$admin_action]['template'];
        } else {
            if(empty($this->_admin_views[$admin_action][$admin_view]))
                return array();

            return $this->_admin_views[$admin_action][$admin_view]['template'];
        }
    }

    public function get_admin_callback($admin_action, $admin_view = '') {

        if(empty($admin_view)) {

            if(empty($this->_admin_views[$admin_action]))
                return array();

            return $this->_admin_views[$admin_action]['callback'];
        } else {
            if(empty($this->_admin_views[$admin_action][$admin_view]['callback']))
                return array();

            return $this->_admin_views[$admin_action][$admin_view]['callback'];
        }
    }

    public function get_admin_posthooks($admin_action, $admin_view) {
        if(empty($this->_admin_post_hooks[$admin_action][$admin_view]))
            return array();

        return $this->_admin_post_hooks[$admin_action][$admin_view];
    }

    public function register_user_view($template_name, $user_action, $user_view, $callback = '', $require_login = true) {
        if(empty($this->_user_views[$user_action]))
            $this->_user_views[$user_action] = array();

        $this->_user_views[$user_action][$user_view] = array(
            'template_name' => $template_name,
            'callbacks'     => empty($callback) ? '' : (array)$callback,
            'require_login' => $require_login
        );

        return $this;
    }

    public function get_user_view($user_action, $user_view) {
        if(empty($this->_user_views[$user_action][$user_view]))
            return array();

        return $this->_user_views[$user_action][$user_view];
    }

    public function register_user_posthooks($user_action, $user_view, $callbacks) {
        if(empty($this->_user_post_hooks[$user_action]))
            $this->_user_post_hooks[$user_action] = array();

        if(empty($this->_user_post_hooks[$user_action][$user_view]))
            $this->_user_post_hooks[$user_action][$user_view] = array();

        foreach((array)$callbacks as $callback) {
            if(!in_array($callback, $this->_user_post_hooks[$user_action][$user_view]))
                $this->_user_post_hooks[$user_action][$user_view][] = $callback;
        }

        return $this;
    }

    public function user_get_posthooks($user_action, $user_view) {
        if(empty($this->_user_post_hooks[$user_action][$user_view]))
            return array();

        return $this->_user_post_hooks[$user_action][$user_view];
    }
}

function sgl_views_user_get_posthooks($user_action, $user_view) {
    $views = sgl_views::get_instance();
    return $views->user_get_posthooks($user_action, $user_view);
}

function sgl_views_user_register_posthooks($user_action, $user_view, $callbacks) {
    $views = sgl_views::get_instance();
    return $views->register_user_posthooks($user_action, $user_view, $callbacks);
}

function sgl_views_admin_get_posthooks($user_action, $user_view) {
    $views = sgl_views::get_instance();
    return $views->get_admin_posthooks($user_action, $user_view);
}

function sgl_views_admin_register_posthooks($user_action, $user_view, $callbacks) {
    $views = sgl_views::get_instance();
    return $views->register_admin_posthooks($user_action, $user_view, $callbacks);
}

function sgl_views_register_admin_view($template_name, $admin_action, $admin_view = '', $callback = '')  {
    $views = sgl_views::get_instance();
    return $views->register_admin_view($template_name, $admin_action, $admin_view, $callback);
}

function sgl_views_get_admin_template($admin_action, $admin_view = '') {
    $views = sgl_views::get_instance();
    return $views->get_admin_template($admin_action, $admin_view);
}

function sgl_views_get_admin_callback($admin_action, $admin_view = '') {
    $views = sgl_views::get_instance();
    return $views->get_admin_callback($admin_action, $admin_view);
}

function sgl_views_register_user_view($template_name, $user_action, $user_view, $callbacks = '', $require_login = true) {
    $views = sgl_views::get_instance();
    return $views->register_user_view($template_name, $user_action, $user_view, $callbacks, $require_login);
}

function sgl_views_get_user_view($user_action, $user_view) {
    $views = sgl_views::get_instance();
    return $views->get_user_view($user_action, $user_view);
}


sgl_views::get_instance();


require('frontend/admin/admin.php');
require('frontend/user/user.php');


require('template_loader.php');
