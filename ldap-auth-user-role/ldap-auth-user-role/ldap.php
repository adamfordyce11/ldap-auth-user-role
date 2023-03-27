<?php

function get_ldap_groups()
{
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    // Load the LDAP settings
    $laur_settings = get_option('laur_settings', array(
        'server_url' => '',
        'bind_dn' => '',
        'bind_password' => '',
        'base_dn' => '',
        'group_search_filter' => '(&(objectClass=group)(member=%s))',
        'group_name_attribute' => 'cn',
    )
    );

    // Check if the required LDAP settings are configured
    if (empty($laur_settings['server_url']) || empty($laur_settings['bind_dn']) || empty($laur_settings['bind_password']) || empty($laur_settings['base_dn'])) {
        laur_log_event($username, 'get_ldap_groups', 'Error: Failed to retrieve the configuration for LDAP');
        return array();
    }

    // Connect to the LDAP server
    $ldap_conn = ldap_connect($laur_settings['server_url']);
    if (!$ldap_conn) {
        laur_log_event($username, 'get_ldap_groups', 'Error: Failed to connect to server');
        return array();
    }

    // Set LDAP protocol version and options
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // Bind to the LDAP server
    $ldap_bind = @ldap_bind($ldap_conn, $laur_settings['bind_dn'], $laur_settings['bind_password']);
    if (!$ldap_bind) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'get_ldap_groups', 'Error: Failed to Bind to the LDAP server');
        return array();
    }

    // Search for the LDAP groups
    $filter = sprintf('(&(objectClass=top)(ou=*))');
    $search_result = ldap_search($ldap_conn, $laur_settings['base_dn'], $filter, array('cn'));
    if (!$search_result) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'get_ldap_groups', 'Error: Failed to search for LDAP groups "cn"');
        return array();
    }

    // Get the user entry and memberOf attribute
    $entries = ldap_get_entries($ldap_conn, $search_result);

    if ($entries['count'] == 0 || empty($entries)) {
        ldap_close($ldap_conn);
        laur_log_event($username, 'get_ldap_groups', 'Error: Failed to get the user entry and memberOf attribute from the LDAP server');
        return;
    }

    // The first iteration has the number of groups so is pretty useless
    $group_names = array();
    foreach ($entries as $group) {
        if (!isset($group['count']) || $group['count'] == 0) {
            continue;
        }
        if (!isset($group['cn']) || $group['cn']['count'] == 0) {
            continue;
        }

        $group_name = $group['cn']['0'];
        laur_log_event($username, 'get_ldap_groups', 'Adding group to list of LDAP groups ' . $group_name);
        array_push($group_names, $group_name);
    }

    // Close the LDAP connection
    ldap_close($ldap_conn);

    return $group_names;
}

// Render the settings field
function laur_ldap_group_mapping_page()
{
    $ldap_groups = get_ldap_groups();
    $wp_roles = wp_roles()->roles;
    $laur_ldap_group_mapping = get_option('laur_ldap_group_mapping', array());
    echo '<div class="wrap">';
    ?>
    <table class="widefat">
        <thead>
            <tr>
                <th>LDAP Group</th>
                <th>WordPress Role</th>
                <th>Number of Users</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($laur_ldap_group_mapping as $ldap_group => $wp_role):
                $user_count = 0; //count_users_of_ldap_group($ldap_group); ?>
                <tr>
                    <td>
                        <?php echo esc_html($ldap_group); ?>
                    </td>
                    <td>
                        <?php echo esc_html($wp_role); ?>
                    </td>
                    <td>
                        <?php echo esc_html($user_count); ?>
                    </td>
                    <td>
                        <button class="button button-primary">Edit</button>
                        <button class="button button-secondary">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td>
                    <select name="laur_ldap_group" id="laur-ldap-group">
                        <?php foreach ($ldap_groups as $ldap_group): ?>
                            <option value="<?php echo esc_attr($ldap_group); ?>"><?php echo esc_html($ldap_group); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="laur_wp_role" id="laur-wp-role">
                        <?php foreach ($wp_roles as $wp_role => $wp_role_data): ?>
                            <option value="<?php echo esc_attr($wp_role); ?>"><?php echo esc_html($wp_role); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="button button-primary" id="laur-add-group-role">Add Mapping</button>
                </td>
            </tr>
        </tbody>
    </table>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#laur-add-group-role').on('click', function () {
                var ldap_group = $('#laur-ldap-group').val();
                var wp_role = $('#laur-wp-role').val();
                var data = {
                    'action': 'laur_add_ldap_group_mapping',
                    'ldap_group': ldap_group,
                    'wp_role': wp_role,
                    'security': '<?php echo wp_create_nonce("laur-ldap-group-mapping"); ?>'
                };
                $.post(ajaxurl, data, function (response) {
                    location.reload();
                });
            });
        });
    </script>
    <?php
}

function laur_add_ldap_group_mapping_handler()
{
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    check_ajax_referer('laur-ldap-group-mapping', 'security');
    $ldap_group = $_POST['ldap_group'];
    $wp_role = $_POST['wp_role'];
    laur_log_event($username, 'add_ldap_group_mapping', sprintf('%s => %s', $ldap_group, $wp_role));
    $laur_ldap_group_mapping = get_option('laur_ldap_group_mapping', array());
    //error_log(print_r($laur_ldap_group_mapping));
    $laur_ldap_group_mapping[$ldap_group] = $wp_role;
    update_option('laur_ldap_group_mapping', $laur_ldap_group_mapping);
    wp_send_json_success();
}