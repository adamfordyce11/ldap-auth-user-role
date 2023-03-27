<?php
/*
Plugin Name: LDAP Auth User Role
Plugin URI: https://fordyce.space
Description: Authenticate users with LDAP and set their WordPress role based on their LDAP group.
Version: 1.0
Author: Adam Fordyce
Author URI: https://fordyce.space/
License: GPL2
*/

require_once plugin_dir_path(__FILE__) . '/ldap-auth-user-role/activate.php';
register_activation_hook(__FILE__, 'laur_activate');

require_once plugin_dir_path(__FILE__) . '/ldap-auth-user-role/log.php';

require_once plugin_dir_path(__FILE__) . '/ldap-auth-user-role/menu.php';
add_action('admin_menu', 'laur_create_menu');
add_action('laur_register_settings_fields', 'laur_settings_fields');

require_once plugin_dir_path(__FILE__) . '/ldap-auth-user-role/settings.php';

require_once plugin_dir_path(__FILE__) . '/ldap-auth-user-role/ldap.php';
add_action('wp_ajax_laur_add_ldap_group_mapping', 'laur_add_ldap_group_mapping_handler');

require_once plugin_dir_path(__FILE__) . '/ldap-auth-user-role/auth.php';
add_filter('authenticate', 'laur_authenticate', 10, 3);
add_action('wp_login', 'laur_set_user_role', 10, 2);
add_action('wp_logout', 'laur_log_user_logout');
add_action('wp_ajax_laur_truncate_log', 'laur_truncate_log_handler');
