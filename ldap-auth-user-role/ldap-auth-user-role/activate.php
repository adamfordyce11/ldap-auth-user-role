<?php

// Include the required PHP LDAP library if it's not already included
if (!function_exists('ldap_connect')) {
  die("PHP LDAP extension is not installed.");
}

function laur_create_log_table()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'laur_auth_log';

  if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_login VARCHAR(255) NOT NULL,
          event VARCHAR(255) NOT NULL,
          message TEXT NOT NULL,
          timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
      ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }
}

// Create a plugin activate function
function laur_activate()
{
  laur_create_log_table();
  add_option('laur_ldap_group_mapping_activation', array());
}