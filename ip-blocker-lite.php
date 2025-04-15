<?php

/**
 * Plugin Name: IP & Country Blocker Lite
 * Description: Block unwanted specific IPs and countries from accessing your website.
 * Version: 1.0.0
 * Requires PHP: 7.0
 * Author: Nurul Islam
 * Author URI: https://profiles.wordpress.org/faqnurul
 * Text Domain:  ip-blocker-lite
 * License:  GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt

 */


if (!defined('ABSPATH')) {
    exit;
}

// Include Necessary Files
require_once plugin_dir_path(__FILE__) . 'inc/functions.php';

// Activation Hook
function faqnurul_ipcbl_ip_blocker_activate()
{
    if (!get_option('faqnurul_ipcbl_ip_blocked_list')) {
        update_option('faqnurul_ipcbl_ip_blocked_list', []);
    }
    if (!get_option('faqnurul_ipcbl_country_blocked_list')) {
        update_option('faqnurul_ipcbl_country_blocked_list', []);
    }
}
register_activation_hook(__FILE__, 'faqnurul_ipcbl_ip_blocker_activate');




// Get Country from IP
function faqnurul_ipcbl_get_user_country($ip)
{
    $response = wp_remote_get("http://ip-api.com/json/{$ip}");
    if (is_wp_error($response)) {
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return isset($data['country']) ? $data['country'] : false;
}

// Block IPs and Countries
function faqnurul_ipcbl_ip_blocker_check_access()
{
    $blocked_ips = get_option('faqnurul_ipcbl_ip_blocked_list', []);
    $blocked_countries = get_option('faqnurul_ipcbl_country_blocked_list', []);

    $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    $user_country = faqnurul_ipcbl_get_user_country($user_ip);

    if (!empty($blocked_ips[$user_ip])) {
        if ($blocked_ips[$user_ip]['expires'] == 0 || $blocked_ips[$user_ip]['expires'] > time()) {
            faqnurul_ipcbl_ip_blocker_show_block_page('Your IP has been blocked!');
        }
    }

    if (!empty($blocked_countries) && in_array($user_country, $blocked_countries)) {
        faqnurul_ipcbl_ip_blocker_show_block_page('Your country is restricted from accessing this website.');
    }
}
add_action('init', 'faqnurul_ipcbl_ip_blocker_check_access');

function faqnurul_ipcbl_ip_blocker_show_block_page($message)
{
    $css = '
        <style>
        body { font-family: Arial, sans-serif; background: #f8d7da; color: #721c24; text-align: center; padding: 40px; }
        .block-container { max-width: 600px; margin: auto; background: #fff; border: 1px solid #f5c6cb; padding: 30px; border-radius: 8px; }
        h1 { font-size: 2em; margin-bottom: 20px; }
        p { font-size: 1.1em; }
        </style>
    ';

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<title>Access Denied</title>';
    echo wp_kses($css, [
        'style' => [],
    ]);
    echo '</head><body>';
    echo '<div class="block-container">';
    echo '<h1>🚫 Access Denied</h1>';
    echo '<p>' . esc_html($message) . '</p>';
    echo '<p>If you think this is a mistake, contact the administrator.</p>';
    echo '</div></body></html>';

    exit;
}


// Admin Menu Page
function faqnurul_ipcbl_ip_blocker_menu()
{
    add_menu_page(
        'IP & Country Blocker',
        'IP Blocker Lite',
        'manage_options',
        'ip-blocker-lite',
        'faqnurul_ipcbl_ip_blocker_admin_page',
        'dashicons-lock',
        75
    );
}
add_action('admin_menu', 'faqnurul_ipcbl_ip_blocker_menu');



// Admin Page UI
function faqnurul_ipcbl_ip_blocker_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $blocked_ips = get_option('faqnurul_ipcbl_ip_blocked_list', []);
    $blocked_countries = get_option('faqnurul_ipcbl_country_blocked_list', []);

    // Handle Form Submission
    if (!empty($_SERVER['REQUEST_METHOD']) == 'POST') {
        // 🔐 Check user capabilities first
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('You do not have sufficient permissions to perform this action.', 'ip-blocker-lite')));
        }

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
            if (filter_var($new_ip, FILTER_VALIDATE_IP)) {
                $blocked_ips[$new_ip] = ['expires' => $expires];
                update_option('faqnurul_ipcbl_ip_blocked_list', $blocked_ips);
            }
        }


        if (!empty($_POST['unblock_ip'])) {
            unset($blocked_ips[sanitize_text_field(wp_unslash($_POST['unblock_ip']))]);
            update_option('faqnurul_ipcbl_ip_blocked_list', $blocked_ips);
        }

        if (!empty($_POST['block_country'])) {
            $new_country = sanitize_text_field(wp_unslash($_POST['block_country']));
            if (!in_array($new_country, $blocked_countries)) {
                $blocked_countries[] = $new_country;
                update_option('faqnurul_ipcbl_country_blocked_list', $blocked_countries);
            }
        }

        if (!empty($_POST['unblock_country'])) {
            $blocked_countries = array_diff($blocked_countries, [sanitize_text_field(wp_unslash($_POST['unblock_country']))]);
            update_option('faqnurul_ipcbl_country_blocked_list', $blocked_countries);
        }
    }
?>



    <div class="wrap">
        <h1><?php echo esc_html(__('🚫 IP & Country Blocker Lite', 'ip-blocker-lite')); ?></h1>
        <!-- Shortcut Help Menu -->
        <div class="ip-blocker-container mt-4">
            <h2><?php echo esc_html__('🛠 Shortcut Help', 'ip-blocker-lite'); ?></h2>
            <div class="accordion" id="shortcutHelpAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingShortcuts">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseShortcuts" aria-expanded="true" aria-controls="collapseShortcuts">
                            <?php echo esc_html__('How to Use Plugin Features', 'ip-blocker-lite'); ?>
                        </button>
                    </h2>
                    <div id="collapseShortcuts" class="accordion-collapse collapse" aria-labelledby="headingShortcuts" data-bs-parent="#shortcutHelpAccordion">
                        <div class="accordion-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong><?php echo esc_html__('🔄Block IP:', 'ip-blocker-lite'); ?></strong> <?php echo esc_html__('Enter the IP and optional duration in minutes. Leave duration empty or 0 for permanent block.', 'ip-blocker-lite'); ?></li>
                                <li class="list-group-item"><strong><?php echo esc_html__('🔄Block Country:', 'ip-blocker-lite'); ?></strong> <?php echo esc_html__('Select country name (e.g., "United States").', 'ip-blocker-lite'); ?></li>
                                <li class="list-group-item"><strong><?php echo esc_html__('🔄Unblock:', 'ip-blocker-lite'); ?></strong><?php echo esc_html__(' Click the "Unblock" button next to a listed IP or country.', 'ip-blocker-lite'); ?> </li>
                                <li class="list-group-item"><strong><?php echo esc_html__('🔄 Duration:', 'ip-blocker-lite'); ?></strong> <?php echo esc_html__('You can block IPs temporarily—duration is in minutes.', 'ip-blocker-lite'); ?></li>
                                <li class="list-group-item"><strong><?php echo esc_html__('💡 Tip:', 'ip-blocker-lite'); ?></strong> <?php echo esc_html__('Use Ctrl + F to quickly search blocked IPs or countries in lists.', 'ip-blocker-lite'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>



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
            <h2><?php echo esc_html__('🌍 Block Country', 'ip-blocker-lite'); ?></h2>
            <form method="post">
                <select name="block_country" required>
                    <option value="">Select a Country</option>
                    <?php foreach (faqnurul_ipcbl_get_all_countries() as $country_name) : ?>
                        <option value="<?php echo esc_attr($country_name); ?>">
                            <?php echo esc_html($country_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="btn-primary" value="Block Country">
            </form>
        </div>

        <h3><?php echo esc_html__('📋 Blocked IPs', 'ip-blocker-lite'); ?></h3>
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

        <h3><?php echo esc_html__('🌍 Blocked Countries', 'ip-blocker-lite'); ?></h3>

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


// Enqueue Admin CSS
add_action('admin_enqueue_scripts', 'faqnurul_ipcbl_admin_styles');

function faqnurul_ipcbl_admin_styles($hook)
{
    if ($hook !== 'toplevel_page_ip-blocker-lite') {
        return;
    }

    // Local Bootstrap CSS
    wp_enqueue_style(
        'bootstrap-css',
        plugin_dir_url(__FILE__) . 'assets/bootstrap.min.css',
        array(),
        '5.3.2'
    );

    // Admin CSS with proper versioning (you can also use filemtime() if desired)
    wp_enqueue_style(
        'ip-blocker-admin-style',
        plugin_dir_url(__FILE__) . 'assets/admin-style.css',
        array(),
        '1.0.0',
        'all'
    );

    // Local Bootstrap JS
    wp_enqueue_script(
        'bootstrap-js',
        plugin_dir_url(__FILE__) . 'assets/bootstrap.bundle.min.js',
        array(),
        '5.3.2',
        true
    );
}

// Added Setting Links on plugin page
function faqnurul_ipcbl_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=ip-blocker-lite') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'faqnurul_ipcbl_plugin_action_links');
