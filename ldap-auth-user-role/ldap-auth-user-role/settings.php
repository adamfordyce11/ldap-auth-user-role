<?php

// Render the LDAP settings page
function laur_settings_page() {
  // Check if the user has the required capability to manage options
  if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
  }

  // Handle form submission and update options
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['laur_settings'])) {
      check_admin_referer('laur_settings_nonce');
      update_option('laur_settings', $_POST['laur_settings']);
      echo '<div id="message" class="updated fade"><p><strong>' . __('Settings saved.') . '</strong></p></div>';
  }

  // Load the current settings or set defaults if not found
  $laur_settings = get_option('laur_settings', array(
      'server_url' => '',
      'bind_dn' => '',
      'bind_password' => '',
      'base_dn' => ''
  ));

  // Render the settings form
  echo '<div class="wrap">';
  echo '<h2>' . __('LDAP Authentication and User Role Settings') . '</h2>';
  echo '<form method="post" action="">';
  echo '<table class="form-table">';
  echo '<tbody>';

  echo '<tr>';
  echo '<th scope="row">' . __('LDAP Server URL') . '</th>';
  echo '<td><input type="text" name="laur_settings[server_url]" value="' . esc_attr($laur_settings['server_url']) . '" class="regular-text" /></td>';
  echo '</tr>';

  echo '<tr>';
  echo '<th scope="row">' . __('Bind DN') . '</th>';
  echo '<td><input type="text" name="laur_settings[bind_dn]" value="' . esc_attr($laur_settings['bind_dn']) . '" class="regular-text" /></td>';
  echo '</tr>';

  echo '<tr>';
  echo '<th scope="row">' . __('Bind Password') . '</th>';
  echo '<td><input type="password" name="laur_settings[bind_password]" value="' . esc_attr($laur_settings['bind_password']) . '" class="regular-text" /></td>';
  echo '</tr>';

  echo '<tr>';
  echo '<th scope="row">' . __('Base DN') . '</th>';
  echo '<td><input type="text" name="laur_settings[base_dn]" value="' . esc_attr($laur_settings['base_dn']) . '" class="regular-text" /></td>';
  echo '</tr>';

  echo '</tbody>';
  echo '</table>';

  wp_nonce_field('laur_settings_nonce');
  submit_button();

  echo '</form>';
  echo '</div>';
}