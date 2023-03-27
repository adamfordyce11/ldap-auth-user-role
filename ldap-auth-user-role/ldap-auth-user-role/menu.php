<?php

// Create the LDAP User Sync Admin Menu
function laur_create_menu()
{
    // Include CSS styles
    wp_enqueue_style('laur-admin-style', plugin_dir_url(__FILE__) . 'css/admin-style.css', array(), '1.0.0', 'all');

    // Add top-level menu page
    add_menu_page(
        'LDAP User Sync',
        'LDAP User Sync',
        'manage_options',
        'laur-admin',
        'laur_admin_page',
        'dashicons-groups'
    );

    // Add submenus for the associated pages
    add_submenu_page(
        'laur-admin',
        'LDAP Group Mapping',
        'LDAP Group Mapping',
        'manage_options',
        'laur-ldap-group-mapping',
        'laur_ldap_group_mapping_page'
    );
    add_submenu_page(
        'laur-admin',
        'LDAP Authentication and User Role Log',
        'LDAP Auth Log',
        'manage_options',
        'laur-log',
        'laur_show_log_page'
    );
    add_submenu_page(
        'laur-admin',
        'LDAP Authentication and User Role Settings',
        'LDAP Auth & User Role',
        'manage_options',
        'laur-settings',
        'laur_settings_page'
    );
    remove_submenu_page('laur-admin', 'laur-admin');

}