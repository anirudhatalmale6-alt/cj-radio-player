<?php
if (!defined('ABSPATH')) exit;

class CJRP_Frontend {

    public function __construct() {
        add_shortcode('radio_player', array($this, 'shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_sticky_player'));
        add_action('wp_head', array($this, 'custom_css'));
    }

    public function enqueue_assets() {
        wp_enqueue_style('cjrp-player', CJRP_PLUGIN_URL . 'assets/css/player.css', array(), CJRP_VERSION);
        wp_enqueue_script('cjrp-player', CJRP_PLUGIN_URL . 'assets/js/player.js', array(), CJRP_VERSION, true);

        $players = CJRP_Database::get_players();
        $players_data = array();
        foreach ($players as $player) {
            $stations = CJRP_Database::get_stations($player->id);
            $stations_arr = array();
            foreach ($stations as $st) {
                $stations_arr[] = array(
                    'id'         => $st->id,
                    'title'      => $st->title,
                    'sourceType' => $st->source_type,
                    'sourceUrl'  => $st->source_url,
                    'artUrl'     => $st->art_url,
                );
            }
            $players_data[$player->id] = array(
                'id'       => $player->id,
                'title'    => $player->title,
                'skin'     => $player->skin,
                'controls' => json_decode($player->controls, true),
                'appearance' => json_decode($player->appearance, true),
                'stations' => $stations_arr,
            );
        }

        wp_localize_script('cjrp-player', 'cjrpData', array(
            'players'  => $players_data,
            'settings' => array(
                'stickyEnabled'   => get_option('cjrp_sticky_enabled', '0'),
                'stickyPlayerId'  => get_option('cjrp_sticky_player_id', ''),
                'stickyStyle'     => get_option('cjrp_sticky_style', 'fullwidth'),
                'stickyPosition'  => get_option('cjrp_sticky_position', 'bottom'),
                'stickyMobile'    => get_option('cjrp_sticky_mobile', '1'),
                'stickyWidth'     => get_option('cjrp_sticky_width', 'fullscreen'),
                'popupAlways'     => get_option('cjrp_popup_always', '0'),
                'popupCustomSize' => get_option('cjrp_popup_custom_size', '0'),
                'popupWidth'      => get_option('cjrp_popup_width', '400'),
                'popupHeight'     => get_option('cjrp_popup_height', '500'),
                'playlistOpened'  => get_option('cjrp_playlist_opened', '1'),
                'playlistHeight'  => get_option('cjrp_playlist_height', 'full'),
                'playlistCustomH' => get_option('cjrp_playlist_custom_height', '300'),
            ),
            'pluginUrl' => CJRP_PLUGIN_URL,
        ));
    }

    public function custom_css() {
        $css = get_option('cjrp_custom_css', '');
        if ($css) {
            echo '<style id="cjrp-custom-css">' . wp_strip_all_tags($css) . '</style>';
        }
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        $id = intval($atts['id']);
        if (!$id) return '';

        $player = CJRP_Database::get_player($id);
        if (!$player || !$player->status) return '';

        $stations = CJRP_Database::get_stations($id);
        $controls = json_decode($player->controls, true);
        $appearance = json_decode($player->appearance, true);
        $controls = wp_parse_args($controls, CJRP_Database::get_default_controls());
        $appearance = wp_parse_args($appearance, CJRP_Database::get_default_appearance());

        $first_station = !empty($stations) ? $stations[0] : null;

        $bg_style = $this->get_bg_style($appearance);
        $text_color = esc_attr($appearance['text_button_color']);
        $radius = intval($appearance['border_radius']);
        $shadow = $appearance['box_shadow'] ? 'box-shadow:0 4px 20px rgba(0,0,0,0.3);' : '';
        $width = intval($appearance['player_width_desktop']);

        ob_start();
        ?>
        <div class="cjrp-player cjrp-<?php echo esc_attr($player->skin); ?>"
             data-player-id="<?php echo $id; ?>"
             style="<?php echo $bg_style; ?> color:<?php echo $text_color; ?>; border-radius:<?php echo $radius; ?>px; <?php echo $shadow; ?> max-width:<?php echo $width; ?>px;">

            <?php if ($first_station) : ?>
            <div class="cjrp-player-inner">
                <?php if ($controls['artwork_image']) : ?>
                <div class="cjrp-art">
                    <?php if ($first_station->art_url) : ?>
                        <img src="<?php echo esc_url($first_station->art_url); ?>" alt="<?php echo esc_attr($first_station->title); ?>">
                    <?php else : ?>
                        <div class="cjrp-art-default">&#127911;</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="cjrp-info">
                    <?php if ($controls['show_track_title']) : ?>
                    <div class="cjrp-title" data-fallback="<?php echo esc_attr($controls['fallback_title'] ?: $first_station->title); ?>">
                        <?php echo esc_html($first_station->title); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($controls['show_artist_name']) : ?>
                    <div class="cjrp-artist" data-fallback="<?php echo esc_attr($controls['fallback_artist']); ?>"></div>
                    <?php endif; ?>

                    <?php if ($controls['player_status']) : ?>
                    <div class="cjrp-status">
                        <span class="cjrp-badge cjrp-badge-offline">OFFLINE</span>
                        <span class="cjrp-status-dot">&#9679;</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="cjrp-controls">
                    <button class="cjrp-btn-prev" title="Previous" style="color:<?php echo $text_color; ?>">&#9664;&#9664;</button>
                    <button class="cjrp-btn-play" title="Play" style="color:<?php echo $text_color; ?>">&#9654;</button>
                    <button class="cjrp-btn-next" title="Next" style="color:<?php echo $text_color; ?>">&#9654;&#9654;</button>

                    <?php if ($controls['volume_control']) : ?>
                    <button class="cjrp-btn-volume" title="Volume" style="color:<?php echo $text_color; ?>">&#128266;</button>
                    <div class="cjrp-volume-slider">
                        <input type="range" min="0" max="100" value="80" class="cjrp-vol-range">
                    </div>
                    <?php endif; ?>

                    <?php if ($controls['popup_icon']) : ?>
                    <button class="cjrp-btn-popup" title="Full Player" style="color:<?php echo $text_color; ?>" data-popup-url="<?php echo esc_attr($controls['popup_url'] ?? ''); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    </button>
                    <?php endif; ?>

                    <?php if ($controls['stations_playlist'] && count($stations) > 1) : ?>
                    <button class="cjrp-btn-playlist" title="Playlist" style="color:<?php echo $text_color; ?>">&#9776;</button>
                    <?php endif; ?>
                </div>

                <?php if ($controls['progressbar']) : ?>
                <div class="cjrp-progress">
                    <div class="cjrp-progress-bar"></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($controls['stations_playlist'] && count($stations) > 1) : ?>
            <div class="cjrp-playlist" style="display:none;">
                <?php foreach ($stations as $i => $st) : ?>
                <div class="cjrp-playlist-item <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>">
                    <?php if ($st->art_url) : ?>
                        <img src="<?php echo esc_url($st->art_url); ?>" class="cjrp-playlist-art" alt="">
                    <?php else : ?>
                        <span class="cjrp-playlist-art-default">&#127925;</span>
                    <?php endif; ?>
                    <span class="cjrp-playlist-title"><?php echo esc_html($st->title); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_sticky_player() {
        if (get_option('cjrp_sticky_enabled') !== '1') return;

        $player_id = get_option('cjrp_sticky_player_id');
        if (!$player_id) return;

        $player = CJRP_Database::get_player($player_id);
        if (!$player || !$player->status) return;

        if (!$this->should_show_sticky()) return;

        $stations = CJRP_Database::get_stations($player_id);
        if (empty($stations)) return;

        $controls = json_decode($player->controls, true);
        $appearance = json_decode($player->appearance, true);
        $controls = wp_parse_args($controls, CJRP_Database::get_default_controls());
        $appearance = wp_parse_args($appearance, CJRP_Database::get_default_appearance());

        $first_station = $stations[0];
        $position = get_option('cjrp_sticky_position', 'bottom');
        $style = get_option('cjrp_sticky_style', 'fullwidth');
        $sticky_width = get_option('cjrp_sticky_width', 'fullscreen');
        $minimized_img = get_option('cjrp_sticky_minimized_image', '');
        $bg_style = $this->get_bg_style($appearance);
        $text_color = esc_attr($appearance['text_button_color']);
        ?>
        <div class="cjrp-sticky cjrp-sticky-<?php echo esc_attr($style); ?> cjrp-sticky-<?php echo esc_attr($position); ?> cjrp-sticky-w-<?php echo esc_attr($sticky_width); ?>"
             data-player-id="<?php echo intval($player_id); ?>"
             style="<?php echo $bg_style; ?> color:<?php echo $text_color; ?>;">

            <div class="cjrp-sticky-inner">
                <?php if ($first_station->art_url) : ?>
                    <img src="<?php echo esc_url($first_station->art_url); ?>" class="cjrp-sticky-art" alt="">
                <?php elseif ($minimized_img) : ?>
                    <img src="<?php echo esc_url($minimized_img); ?>" class="cjrp-sticky-art" alt="">
                <?php endif; ?>

                <div class="cjrp-sticky-info">
                    <span class="cjrp-sticky-text">Escuchando <strong class="cjrp-sticky-title"><?php echo esc_html($first_station->title); ?></strong></span>
                    <span class="cjrp-badge cjrp-badge-live">LIVE</span>
                    <span class="cjrp-status-dot cjrp-dot-live">&#9679;</span>
                </div>

                <div class="cjrp-sticky-controls">
                    <button class="cjrp-sticky-prev" style="color:<?php echo $text_color; ?>">&#9664;&#9664;</button>
                    <button class="cjrp-sticky-play" style="color:<?php echo $text_color; ?>">&#9654;</button>
                    <button class="cjrp-sticky-next" style="color:<?php echo $text_color; ?>">&#9654;&#9654;</button>

                    <?php if ($controls['volume_control']) : ?>
                    <button class="cjrp-sticky-volume" style="color:<?php echo $text_color; ?>">&#128266;</button>
                    <div class="cjrp-sticky-vol-slider">
                        <input type="range" min="0" max="100" value="80" class="cjrp-vol-range">
                    </div>
                    <?php endif; ?>

                    <?php if ($controls['popup_icon']) : ?>
                    <button class="cjrp-sticky-popup" title="Full Player" style="color:<?php echo $text_color; ?>" data-popup-url="<?php echo esc_attr($controls['popup_url'] ?? ''); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    </button>
                    <?php endif; ?>

                    <?php if (count($stations) > 1) : ?>
                    <button class="cjrp-sticky-playlist-btn" style="color:<?php echo $text_color; ?>">&#9776;</button>
                    <?php endif; ?>
                </div>

                <button class="cjrp-sticky-close" style="color:<?php echo $text_color; ?>">&times;</button>
            </div>

            <?php if (count($stations) > 1) : ?>
            <div class="cjrp-sticky-playlist" style="display:none;">
                <?php foreach ($stations as $i => $st) : ?>
                <div class="cjrp-playlist-item <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>">
                    <?php if ($st->art_url) : ?>
                        <img src="<?php echo esc_url($st->art_url); ?>" class="cjrp-playlist-art" alt="">
                    <?php else : ?>
                        <span class="cjrp-playlist-art-default">&#127925;</span>
                    <?php endif; ?>
                    <span class="cjrp-playlist-title"><?php echo esc_html($st->title); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php
        $min_btn_img = $minimized_img ?: ($first_station->art_url ?: '');
        $min_position_class = $position === 'top' ? 'cjrp-minimized-top' : 'cjrp-minimized-bottom';
        ?>
        <div class="cjrp-minimized-btn <?php echo $min_position_class; ?>" style="display:none;" title="Abrir Radio">
            <?php if ($min_btn_img) : ?>
                <img src="<?php echo esc_url($min_btn_img); ?>" alt="Radio">
            <?php else : ?>
                <span class="cjrp-minimized-icon">&#9654;</span>
            <?php endif; ?>
            <span class="cjrp-minimized-live">LIVE</span>
        </div>
        <?php
    }

    private function should_show_sticky() {
        $exclude_all = get_option('cjrp_sticky_exclude_all', '0');
        $current_id = get_queried_object_id();

        if ($exclude_all === '1') {
            $except = get_option('cjrp_sticky_except_pages', '');
            if (!$except) return false;
            $except_ids = array_map('intval', array_filter(explode(',', $except)));
            return in_array($current_id, $except_ids);
        }

        $exclude = get_option('cjrp_sticky_exclude_pages', '');
        if ($exclude) {
            $exclude_ids = array_map('intval', array_filter(explode(',', $exclude)));
            if (in_array($current_id, $exclude_ids)) return false;
        }

        $mobile = get_option('cjrp_sticky_mobile', '1');
        if ($mobile !== '1' && wp_is_mobile()) return false;

        return true;
    }

    private function get_bg_style($appearance) {
        $bg_type = $appearance['bg_type'] ?? 'color';
        if ($bg_type === 'image' && !empty($appearance['bg_image'])) {
            return 'background:url(' . esc_url($appearance['bg_image']) . ') center/cover no-repeat;';
        }
        $color_type = $appearance['bg_color_type'] ?? 'solid';
        if ($color_type === 'gradient' && !empty($appearance['bg_gradient'])) {
            return 'background:' . esc_attr($appearance['bg_gradient']) . ';';
        }
        return 'background:' . esc_attr($appearance['bg_color'] ?? '#1e293b') . ';';
    }
}
