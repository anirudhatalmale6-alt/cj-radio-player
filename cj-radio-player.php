<?php
/**
 * Plugin Name: CJ Radio Player
 * Description: Professional radio player with sticky bar, popup player, multiple skins, and shortcode support.
 * Version: 1.0.0
 * Author: ChatJovenes
 * Text Domain: cj-radio-player
 */

if (!defined('ABSPATH')) exit;

define('CJRP_VERSION', '1.0.0');
define('CJRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CJRP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CJRP_PLUGIN_FILE', __FILE__);

require_once CJRP_PLUGIN_DIR . 'includes/class-database.php';
require_once CJRP_PLUGIN_DIR . 'includes/class-admin.php';
require_once CJRP_PLUGIN_DIR . 'includes/class-frontend.php';
require_once CJRP_PLUGIN_DIR . 'includes/class-ajax.php';

register_activation_hook(__FILE__, array('CJRP_Database', 'create_tables'));

add_action('init', function() {
    if (is_admin()) {
        new CJRP_Admin();
    }
    new CJRP_Frontend();
    new CJRP_Ajax();
});
