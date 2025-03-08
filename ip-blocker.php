<?php

/**
 * Plugin Name: IP & Country Blocker Lite
 * Plugin URI: https://github.com/faqnurul/ip-blocker-lite
 * Description: Block unwanted specific IPs and countries from accessing your website.
 * Version: 1.0.0
 * Author: Nurul Islam
 * Author URI: https://profiles.wordpress.org/faqnurul
 * Text Domain:  ip-blocker-lite
 * License:  GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt

 */


if (!defined('ABSPATH')) {
    exit;
}

// Activation Hook
function ip_blocker_activate()
{
    if (!get_option('ip_blocked_list')) {
        update_option('ip_blocked_list', []);
    }
    if (!get_option('country_blocked_list')) {
        update_option('country_blocked_list', []);
    }
}
register_activation_hook(__FILE__, 'ip_blocker_activate');




// Get Country from IP
function get_user_country($ip)
{
    $response = wp_remote_get("http://ip-api.com/json/{$ip}");
    if (is_wp_error($response)) {
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return isset($data['country']) ? $data['country'] : false;
}

// Block IPs and Countries
function ip_blocker_check_access()
{
    $blocked_ips = get_option('ip_blocked_list', []);
    $blocked_countries = get_option('country_blocked_list', []);

    $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    $user_country = get_user_country($user_ip);

    if (!empty($blocked_ips[$user_ip])) {
        if ($blocked_ips[$user_ip]['expires'] == 0 || $blocked_ips[$user_ip]['expires'] > time()) {
            ip_blocker_show_block_page('Your IP has been blocked!');
        }
    }

    if (!empty($blocked_countries) && in_array($user_country, $blocked_countries)) {
        ip_blocker_show_block_page('Your country is restricted from accessing this website.');
    }
}
add_action('init', 'ip_blocker_check_access');

// Block Page Design
function ip_blocker_show_block_page($message)
{
?>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f8d7da;
            color: #721c24;
        }

        .block-container {
            max-width: 600px;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #dc3545;
        }
    </style>

    <div class="block-container">
        <h1>🚫 Access Denied</h1>
        <p><?php echo esc_html($message); ?></p>
        <p>If you think this is a mistake, contact the administrator.</p>
    </div>
<?php
    exit;
}

// Admin Menu Page
function ip_blocker_menu()
{
    add_menu_page('IP & Country Blocker', 'IP Blocker Lite', 'manage_options', 'ip-blocker', 'ip_blocker_admin_page', 'dashicons-lock', 75);
}
add_action('admin_menu', 'ip_blocker_menu');

// Admin Page UI
function ip_blocker_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $blocked_ips = get_option('ip_blocked_list', []);
    $blocked_countries = get_option('country_blocked_list', []);

    // Handle Form Submission
    if (!empty($_SERVER['REQUEST_METHOD']) == 'POST') {

        if (isset($_POST['block_ip'])) {
            // Check if nonce is valid
            if (!isset($_POST['block_ip_nonce']) || !check_admin_referer('block_ip_action', 'block_ip_nonce')) {
                // Nonce is invalid or not set, exit or display an error
                die('Security check failed');
            }

            // Now you can safely process the form data
            $new_ip = sanitize_text_field(wp_unslash($_POST['block_ip']));
            $duration = isset($_POST['block_duration']) ? intval(wp_unslash($_POST['block_duration'])) : 0;
            $expires = ($duration > 0) ? time() + ($duration * 60) : 0;
            $blocked_ips[$new_ip] = ['expires' => $expires];
            update_option('ip_blocked_list', $blocked_ips);
        }

     
        if (!empty($_POST['unblock_ip'])) {
            unset($blocked_ips[sanitize_text_field(wp_unslash($_POST['unblock_ip']))]);
            update_option('ip_blocked_list', $blocked_ips);
        }

        if (!empty($_POST['block_country'])) {
            $new_country = sanitize_text_field(wp_unslash($_POST['block_country']));
            if (!in_array($new_country, $blocked_countries)) {
                $blocked_countries[] = $new_country;
                update_option('country_blocked_list', $blocked_countries);
            }
        }

        if (!empty($_POST['unblock_country'])) {
            $blocked_countries = array_diff($blocked_countries, [sanitize_text_field(wp_unslash($_POST['unblock_country']))]);
            update_option('country_blocked_list', $blocked_countries);
        }
    }
?>

    <div class="wrap">
        <h1>🚫 IP & Country Blocker Lite</h1>

        <style>
            .ip-blocker-container {
                background: #fff;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            .ip-blocker-container input,
            .ip-blocker-container select {
                width: 100%;
                padding: 8px;
                margin: 5px 0;
            }

            .btn-primary {
                background: #0073aa;
                color: #fff;
                border: none;
                padding: 8px 15px;
                cursor: pointer;
            }

            .btn-danger {
                background: #dc3545;
                color: #fff;
                border: none;
                padding: 8px 15px;
                cursor: pointer;
            }

            .btn-primary:hover {
                background: #005f8d;
            }

            .btn-danger:hover {
                background: #a71c2c;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            th,
            td {
                padding: 10px;
                border: 1px solid #ddd;
                text-align: left;
            }
        </style>

        <div class="ip-blocker-container">
            <h2>🔒 Block IP Address</h2>
            <form method="post">
                <?php wp_nonce_field('block_ip_action', 'block_ip_nonce'); ?>
                <input type="text" name="block_ip" required placeholder="Enter IP Address">
                <input type="number" name="block_duration" min="0" placeholder="Block Duration (minutes, 0 = Permanent)">
                <input type="submit" class="btn-primary" value="Block IP">
            </form>
        </div>

        <div class="ip-blocker-container">
            <h2>🌍 Block Country</h2>
            <form method="post">
                <input type="text" name="block_country" required placeholder="Enter Country Name (e.g., United States)">
                <input type="submit" class="btn-primary" value="Block Country">
            </form>
        </div>

        <h3>📋 Blocked IPs</h3>
        <table>
            <tr>
                <th>IP Address</th>
                <th>Expires</th>
                <th>Action</th>
            </tr>
            <?php foreach ($blocked_ips as $ip => $data) : ?>
                <tr>
                    <td><?php echo esc_html($ip); ?></td>
                    <td><?php echo esc_html(($data['expires'] == 0) ? 'Never' : gmdate('Y-m-d H:i:s', $data['expires'])); ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="unblock_ip" value="<?php echo esc_attr($ip); ?>">
                            <input type="submit" class="btn-danger" value="Unblock">
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h3>🌍 Blocked Countries</h3>
        <table>
            <tr>
                <th>Country</th>
                <th>Action</th>
            </tr>
            <?php foreach ($blocked_countries as $country) : ?>
                <tr>
                    <td><?php echo esc_html($country); ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="unblock_country" value="<?php echo esc_attr($country); ?>">
                            <input type="submit" class="btn-danger" value="Unblock">
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php
}
