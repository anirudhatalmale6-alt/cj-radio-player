<?php
if (!defined('ABSPATH')) exit;

class CJRP_Database {

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $players_table = $wpdb->prefix . 'cjrp_players';
        $stations_table = $wpdb->prefix . 'cjrp_stations';

        $sql_players = "CREATE TABLE $players_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            status tinyint(1) NOT NULL DEFAULT 1,
            skin varchar(50) NOT NULL DEFAULT 'skin-1',
            controls longtext NOT NULL,
            appearance longtext NOT NULL,
            schedules longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        $sql_stations = "CREATE TABLE $stations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            player_id mediumint(9) NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            source_type varchar(20) NOT NULL DEFAULT 'stream_url',
            source_url text NOT NULL,
            art_url text NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY player_id (player_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_players);
        dbDelta($sql_stations);

        $defaults = array(
            'cjrp_sticky_enabled' => '0',
            'cjrp_sticky_player_id' => '',
            'cjrp_sticky_style' => 'fullwidth',
            'cjrp_sticky_position' => 'bottom',
            'cjrp_sticky_mobile' => '1',
            'cjrp_sticky_exclude_pages' => '',
            'cjrp_sticky_exclude_all' => '0',
            'cjrp_sticky_except_pages' => '',
            'cjrp_sticky_minimized_image' => '',
            'cjrp_sticky_width' => 'fullscreen',
            'cjrp_popup_always' => '0',
            'cjrp_popup_custom_size' => '0',
            'cjrp_popup_width' => '400',
            'cjrp_popup_height' => '500',
            'cjrp_popup_header' => '',
            'cjrp_popup_footer' => '',
            'cjrp_playlist_opened' => '1',
            'cjrp_playlist_height' => 'full',
            'cjrp_playlist_custom_height' => '300',
            'cjrp_custom_css' => '',
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    public static function get_players() {
        global $wpdb;
        $table = $wpdb->prefix . 'cjrp_players';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
    }

    public static function get_player($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cjrp_players';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    public static function insert_player($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cjrp_players';
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function update_player($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cjrp_players';
        return $wpdb->update($table, $data, array('id' => $id));
    }

    public static function delete_player($id) {
        global $wpdb;
        $players_table = $wpdb->prefix . 'cjrp_players';
        $stations_table = $wpdb->prefix . 'cjrp_stations';
        $wpdb->delete($stations_table, array('player_id' => $id));
        return $wpdb->delete($players_table, array('id' => $id));
    }

    public static function get_stations($player_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cjrp_stations';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE player_id = %d ORDER BY sort_order ASC, id ASC",
            $player_id
        ));
    }

    public static function save_stations($player_id, $stations) {
        global $wpdb;
        $table = $wpdb->prefix . 'cjrp_stations';
        $wpdb->delete($table, array('player_id' => $player_id));

        foreach ($stations as $i => $station) {
            $wpdb->insert($table, array(
                'player_id'   => $player_id,
                'title'       => sanitize_text_field($station['title']),
                'source_type' => sanitize_text_field($station['source_type']),
                'source_url'  => esc_url_raw($station['source_url']),
                'art_url'     => esc_url_raw($station['art_url']),
                'sort_order'  => $i,
            ));
        }
    }

    public static function get_default_controls() {
        return array(
            'autoplay'          => '0',
            'progressbar'       => '1',
            'popup_icon'        => '1',
            'stations_playlist' => '1',
            'volume_control'    => '1',
            'player_status'     => '1',
            'show_track_title'  => '1',
            'fallback_title'    => '',
            'show_artist_name'  => '1',
            'fallback_artist'   => '',
            'artwork_image'     => '1',
        );
    }

    public static function get_default_appearance() {
        return array(
            'player_width_desktop' => '350',
            'player_width_mobile'  => '100',
            'bg_type'              => 'color',
            'bg_color_type'        => 'solid',
            'bg_color'             => '#1e293b',
            'bg_gradient'          => 'linear-gradient(135deg, #1e293b, #334155)',
            'bg_image'             => '',
            'text_button_color'    => '#ffffff',
            'border_radius'        => '10',
            'box_shadow'           => '1',
        );
    }
}
