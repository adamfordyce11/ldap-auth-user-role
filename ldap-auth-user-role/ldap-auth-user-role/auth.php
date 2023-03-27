<?php

// Function to log user logout
function laur_log_user_logout()
{
    // Get the current user's username
    $username = wp_get_current_user()->user_login;

    // Create a log entry for user logout
    laur_log_event($username, 'user_logout', 'User logged out');
}

function laur_authenticate($user, $username, $password)
{
    // If the user is already authenticated or the credentials are empty, return early
    if ($user instanceof WP_User || empty($username) || empty($password)) {
        return $user;
    }

    // Load the LDAP settings
    $laur_settings = get_option('laur_settings', array(
        'server_url' => '',
        'bind_dn' => '',
        'bind_password' => '',
        'base_dn' => ''
    )
    );

    // Check if the required LDAP settings are configured
    if (empty($laur_settings['server_url']) || empty($laur_settings['bind_dn']) || empty($laur_settings['bind_password']) || empty($laur_settings['base_dn'])) {
        return $user;
    }

    // Connect to the LDAP server
    $ldap_conn = ldap_connect($laur_settings['server_url']);
    if (!$ldap_conn) {
        return $user;
    }

    // Set LDAP protocol version and options
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // Bind to the LDAP server
    $ldap_bind = @ldap_bind($ldap_conn, $laur_settings['bind_dn'], $laur_settings['bind_password']);
    if (!$ldap_bind) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'authentication', 'Error: Failed to authenticate with LDAP check plugin settings');
        return $user;
    }
    laur_log_event($username, 'authentication', 'LDAP authentication successful.');


    // Search for the user entry in the LDAP directory
    $filter = sprintf('(&(uid=%s)(objectClass=inetOrgPerson))', ldap_escape($username, '', LDAP_ESCAPE_FILTER));
    $search_result = ldap_search($ldap_conn, $laur_settings['base_dn'], $filter);
    if (!$search_result) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'authentication', 'Error: Failed to search for the user entry in the LDAP directory');
        return $user;
    }

    // Get the user entry and DN
    $entries = ldap_get_entries($ldap_conn, $search_result);
    if ($entries['count'] == 0) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'authentication', 'Error: Failed to get the user entry and DN from the LDAP server');
        return $user;
    }
    $user_dn = $entries[0]['dn'];

    // Authenticate the user using the retrieved DN and password
    $user_bind = @ldap_bind($ldap_conn, $user_dn, $password);
    if (!$user_bind) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'authentication', 'Error: Failed to authenticate the user using the retrieved DN and password');
        return $user;
    }

    // User is successfully authenticated via LDAP, check if the user exists in WordPress
    $wp_user = get_user_by('login', $username);
    if (!$wp_user) {
        // Create a new WordPress user
        $user_data = array(
            'user_login' => $username,
            'user_pass' => wp_hash_password($password),
            'user_email' => $entries[0]['mail'][0],
            'first_name' => $entries[0]['givenname'][0],
            'last_name' => $entries[0]['sn'][0],
        );
        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            laur_log_event($username, 'authentication', 'Error: Failed to create a local WordPress account to map the LDAP user onto');
            ldap_close($ldap_conn);
            return $user;
        }

        laur_log_event($username, 'authentication', 'Info: Creating a new WordPress user that maps to the LDAP user');
        $wp_user = new WP_User($user_id);
    } else {
        // Update the existing WordPress user's password
        laur_log_event($username, 'authentication', 'Info: Updating existing WordPress user with the password from LDAP');
        wp_set_password($password, $wp_user->ID);
    }

    laur_set_user_role($wp_user);

    // Close the LDAP
    ldap_close($ldap_conn);
    return $wp_user;
}

function laur_set_user_role($user)
{
    // Load the LDAP settings
    $laur_settings = get_option('laur_settings', array(
        'server_url' => '',
        'bind_dn' => '',
        'bind_password' => '',
        'base_dn' => ''
    )
    );
    $username = $user->user_login;
    laur_log_event($username, 'user_role', 'DEBUG: Authenticated');

    // Check if the required LDAP settings are configured
    if (empty($laur_settings['server_url']) || empty($laur_settings['bind_dn']) || empty($laur_settings['bind_password']) || empty($laur_settings['base_dn'])) {
        laur_log_event($username, 'user_role', 'Error: The required LDAP settings are not populated please correct this before trying again');
        return;
    }

    laur_log_event($username, 'user_role', 'DEBUG: Configured');
    // Connect to the LDAP server
    $ldap_conn = ldap_connect($laur_settings['server_url']);
    if (!$ldap_conn) {
        laur_log_event($username, 'user_role', 'Error: Failed to connect to the LDAP server');
        return;
    }
    laur_log_event($username, 'user_role', 'DEBUG: Connected');

    // Set LDAP protocol version and options
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // Bind to the LDAP server
    $ldap_bind = @ldap_bind($ldap_conn, $laur_settings['bind_dn'], $laur_settings['bind_password']);
    if (!$ldap_bind) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'user_role', 'Error: Failed to bind with the LDAP server');
        return;
    }

    // Search for the user entry in the LDAP directory
    $filter = sprintf('(&(uid=%s)(objectClass=inetOrgPerson))', ldap_escape($user->user_login, '', LDAP_ESCAPE_FILTER));
    $search_result = ldap_search($ldap_conn, $laur_settings['base_dn'], $filter, array('memberof'));
    if (!$search_result) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'user_role', 'Error: Failed to locate the memberOf attribute in the LDAP server');
        return;
    }

    // Get the user entry and memberOf attribute
    $entries = ldap_get_entries($ldap_conn, $search_result);
    if ($entries['count'] == 0 || empty($entries[0]['memberof'])) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'user_role', 'Error: Failed to get the user entry and memberOf attribute from the LDAP server');
        return;
    }

    $ldap_user_groups = $entries[0]['memberof'];

    if (!isset($entries[0]['memberof'])) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'user_role', 'Error: Failed to get the memberOf attribute for the user from the LDAP server');
        return;
    }

    // Load the LDAP group to WordPress role mapping settings
    $laur_ldap_mapping_settings = get_option('laur_ldap_group_mapping', array());

    // Map the LDAP user groups to WordPress roles
    $ldap_groups_to_wp_roles = array();
    foreach ($laur_ldap_mapping_settings as $ldap_group => $wp_role) {
        $ldap_groups_to_wp_roles[$ldap_group] = $wp_role;
    }

    if (empty($ldap_groups_to_wp_roles)) {
        // Map the LDAP user groups to WordPress roles
        laur_log_event($username, 'wp_mapped_role', "Unable to find any WordPress role to LDAP group mapping, setting default ([LDAP Group]->[WP Role]): Administrators->administrator; Editors->editor; Authors->author; Subscribers->subscriber");
        $ldap_groups_to_wp_roles = array(
            'Administrators' => 'administrator',
            'Editors' => 'editor',
            'Authors' => 'author',
            'Subscribers' => 'subscriber'
        );

        // Update the LDAP mapping settings
        update_option('laur_ldap_group_mapping', $ldap_groups_to_wp_roles);
    }

    // Get the user's LDAP groups and map them to WordPress roles
    $wp_user_role = 'subscriber';

    // The first iteration has the number of groups so is pretty useless
    foreach (array_slice($ldap_user_groups, 1) as $group) {
        // Remove the LDAP group prefix
        $group_name = substr($group, strpos($group, '=') + 1, strpos($group, ',') - strpos($group, '=') - 1);
        if (array_key_exists($group_name, $ldap_groups_to_wp_roles)) {
            $mapped_wp_role = $ldap_groups_to_wp_roles[$group_name];
            $wp_user_role = $mapped_wp_role;
            laur_log_event($username, 'wp_mapped_role', sprintf("%s has role %s", $group_name, $mapped_wp_role));
            break; // Exit the loop after mapping to the first role
        }
    }

    // Set the WordPress user role
    laur_log_event($username, 'user_role', 'Info: setting the user role to: ' . $wp_user_role);
    $user->set_role($wp_user_role);

    // Close the LDAP connection
    ldap_close($ldap_conn);
}