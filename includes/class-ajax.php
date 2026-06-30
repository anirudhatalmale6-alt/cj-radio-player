<?php
if (!defined('ABSPATH')) exit;

class CJRP_Ajax {

    public function __construct() {
        add_action('wp_ajax_cjrp_toggle_status', array($this, 'toggle_status'));
    }

    public function toggle_status() {
        check_ajax_referer('cjrp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $id = intval($_POST['player_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);

        if ($id) {
            CJRP_Database::update_player($id, array('status' => $status));
            wp_send_json_success();
        }

        wp_send_json_error('Invalid player ID');
    }
}
