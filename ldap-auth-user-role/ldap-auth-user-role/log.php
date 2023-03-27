<?php

// Create a function to log LDAP events
function laur_log_event($user_login, $event, $message)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'laur_auth_log';

    $wpdb->insert(
        $table_name,
        array(
            'user_login' => $user_login,
            'event' => $event,
            'message' => $message
        ),
        array('%s', '%s', '%s')
    );
}

// Show the log page
function laur_show_log_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'laur_auth_log';

    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC");

    echo '<div class="wrap">';
    echo '<h1>LDAP Authentication and User Role Log</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>User Login</th><th>Event</th><th>Message</th><th>Timestamp</th></tr></thead>';
    echo '<tbody>';

    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . esc_html($log->user_login) . '</td>';
        echo '<td>' . esc_html($log->event) . '</td>';
        echo '<td>' . esc_html($log->message) . '</td>';
        echo '<td>' . esc_html($log->timestamp) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '<button class="button button-secondary" id="laur-truncate-log">Truncate Log</button>';
    echo '</div>';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#laur-truncate-log').on('click', function () {
                var data = {
                    'action': 'laur_truncate_log',
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

// Truncate the log entries
function laur_truncate_log_handler()
{
    check_ajax_referer('laur-ldap-group-mapping', 'security');
    global $wpdb;
    $table_name = $wpdb->prefix . 'laur_auth_log';
    $wpdb->query("TRUNCATE TABLE {$table_name}");
    wp_send_json_success();
}