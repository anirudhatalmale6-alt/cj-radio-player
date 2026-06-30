<?php
if (!defined('ABSPATH')) exit;

class CJRP_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    public function add_menu() {
        add_menu_page(
            'Radio Player',
            'Radio Player',
            'manage_options',
            'cjrp-players',
            array($this, 'players_page'),
            'dashicons-controls-volumeon',
            30
        );

        add_submenu_page(
            'cjrp-players',
            'All Players',
            'All Players',
            'manage_options',
            'cjrp-players',
            array($this, 'players_page')
        );

        add_submenu_page(
            'cjrp-players',
            'Add New Player',
            'Add New Player',
            'manage_options',
            'cjrp-add-player',
            array($this, 'edit_player_page')
        );

        add_submenu_page(
            'cjrp-players',
            'Settings',
            'Settings',
            'manage_options',
            'cjrp-settings',
            array($this, 'settings_page')
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'cjrp') === false) return;

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_style('cjrp-admin', CJRP_PLUGIN_URL . 'assets/css/admin.css', array(), CJRP_VERSION);
        wp_enqueue_script('cjrp-admin', CJRP_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-color-picker'), CJRP_VERSION, true);
        wp_localize_script('cjrp-admin', 'cjrpAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cjrp_admin'),
        ));
    }

    public function handle_actions() {
        if (!isset($_POST['cjrp_action']) || !current_user_can('manage_options')) return;

        check_admin_referer('cjrp_nonce', 'cjrp_nonce_field');

        $action = sanitize_text_field($_POST['cjrp_action']);

        if ($action === 'save_player') {
            $this->save_player();
        } elseif ($action === 'save_settings') {
            $this->save_settings();
        } elseif ($action === 'duplicate_player') {
            $source_id = intval($_POST['player_id']);
            $source = CJRP_Database::get_player($source_id);
            if ($source) {
                $new_id = CJRP_Database::insert_player(array(
                    'title'      => $source->title . ' (Copy)',
                    'status'     => $source->status,
                    'skin'       => $source->skin,
                    'controls'   => $source->controls,
                    'appearance' => $source->appearance,
                    'schedules'  => $source->schedules,
                ));
                $stations = CJRP_Database::get_stations($source_id);
                $st_arr = array();
                foreach ($stations as $st) {
                    $st_arr[] = array(
                        'title'       => $st->title,
                        'source_type' => $st->source_type,
                        'source_url'  => $st->source_url,
                        'art_url'     => $st->art_url,
                    );
                }
                CJRP_Database::save_stations($new_id, $st_arr);
            }
            wp_redirect(admin_url('admin.php?page=cjrp-players&duplicated=1'));
            exit;
        } elseif ($action === 'delete_player') {
            $id = intval($_POST['player_id']);
            CJRP_Database::delete_player($id);
            wp_redirect(admin_url('admin.php?page=cjrp-players&deleted=1'));
            exit;
        }
    }

    private function save_player() {
        $id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;

        $controls = array();
        $defaults_c = CJRP_Database::get_default_controls();
        foreach ($defaults_c as $key => $default) {
            if (in_array($key, array('fallback_title', 'fallback_artist', 'popup_url'))) {
                $controls[$key] = sanitize_text_field($_POST['controls'][$key] ?? $default);
            } else {
                $controls[$key] = isset($_POST['controls'][$key]) ? '1' : '0';
            }
        }

        $appearance = array();
        $defaults_a = CJRP_Database::get_default_appearance();
        foreach ($defaults_a as $key => $default) {
            $appearance[$key] = sanitize_text_field($_POST['appearance'][$key] ?? $default);
        }

        $data = array(
            'title'      => sanitize_text_field($_POST['player_title'] ?? ''),
            'status'     => isset($_POST['player_status']) ? 1 : 0,
            'skin'       => sanitize_text_field($_POST['skin'] ?? 'skin-1'),
            'controls'   => wp_json_encode($controls),
            'appearance' => wp_json_encode($appearance),
            'schedules'  => wp_json_encode(array()),
        );

        if ($id > 0) {
            CJRP_Database::update_player($id, $data);
        } else {
            $id = CJRP_Database::insert_player($data);
        }

        $stations = array();
        if (!empty($_POST['stations']) && is_array($_POST['stations'])) {
            foreach ($_POST['stations'] as $st) {
                if (empty($st['title']) && empty($st['source_url'])) continue;
                $st_type = sanitize_text_field($st['source_type'] ?? 'stream_url');
                $stations[] = array(
                    'title'       => sanitize_text_field($st['title'] ?? ''),
                    'source_type' => $st_type,
                    'source_url'  => $st_type === 'embed' ? ($st['source_url'] ?? '') : esc_url_raw($st['source_url'] ?? ''),
                    'art_url'     => esc_url_raw($st['art_url'] ?? ''),
                );
            }
        }
        CJRP_Database::save_stations($id, $stations);

        wp_redirect(admin_url('admin.php?page=cjrp-add-player&id=' . $id . '&saved=1'));
        exit;
    }

    private function save_settings() {
        $settings = array(
            'cjrp_sticky_enabled',
            'cjrp_sticky_player_id',
            'cjrp_sticky_style',
            'cjrp_sticky_position',
            'cjrp_sticky_mobile',
            'cjrp_sticky_exclude_all',
            'cjrp_popup_always',
            'cjrp_popup_custom_size',
            'cjrp_playlist_opened',
        );

        $checkboxes = array(
            'cjrp_sticky_enabled',
            'cjrp_sticky_mobile',
            'cjrp_sticky_exclude_all',
            'cjrp_popup_always',
            'cjrp_popup_custom_size',
            'cjrp_playlist_opened',
        );

        foreach ($checkboxes as $cb) {
            update_option($cb, isset($_POST[$cb]) ? '1' : '0');
        }

        $text_fields = array(
            'cjrp_sticky_player_id',
            'cjrp_sticky_style',
            'cjrp_sticky_position',
            'cjrp_sticky_exclude_pages',
            'cjrp_sticky_except_pages',
            'cjrp_sticky_minimized_image',
            'cjrp_sticky_width',
            'cjrp_sticky_height',
            'cjrp_popup_width',
            'cjrp_popup_height',
            'cjrp_playlist_height',
            'cjrp_playlist_custom_height',
        );

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }

        if (isset($_POST['cjrp_popup_header'])) {
            update_option('cjrp_popup_header', wp_kses_post($_POST['cjrp_popup_header']));
        }
        if (isset($_POST['cjrp_popup_footer'])) {
            update_option('cjrp_popup_footer', wp_kses_post($_POST['cjrp_popup_footer']));
        }
        if (isset($_POST['cjrp_custom_css'])) {
            update_option('cjrp_custom_css', wp_strip_all_tags($_POST['cjrp_custom_css']));
        }

        wp_redirect(admin_url('admin.php?page=cjrp-settings&saved=1&tab=' . sanitize_text_field($_POST['active_tab'] ?? 'sticky')));
        exit;
    }

    public function players_page() {
        $players = CJRP_Database::get_players();
        ?>
        <div class="wrap cjrp-wrap">
            <div class="cjrp-header">
                <h1><span class="dashicons dashicons-controls-volumeon"></span> Radio Player</h1>
                <a href="<?php echo admin_url('admin.php?page=cjrp-add-player'); ?>" class="cjrp-btn cjrp-btn-primary">+ Add New Player</a>
            </div>

            <?php if (isset($_GET['deleted'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Player deleted.</p></div>
            <?php endif; ?>

            <div class="cjrp-card">
                <h2>All Players (<?php echo count($players); ?> Items)</h2>
                <table class="cjrp-table">
                    <thead>
                        <tr>
                            <th width="50">ID</th>
                            <th>Title</th>
                            <th width="100">Status</th>
                            <th width="250">Shortcode</th>
                            <th width="150">Created at</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($players)) : ?>
                            <tr><td colspan="6" style="text-align:center;padding:40px;">No players yet. Click "Add New Player" to create one.</td></tr>
                        <?php else : ?>
                            <?php foreach ($players as $player) : ?>
                            <tr>
                                <td><?php echo $player->id; ?></td>
                                <td><strong><?php echo esc_html($player->title); ?></strong></td>
                                <td>
                                    <label class="cjrp-toggle">
                                        <input type="checkbox" class="cjrp-toggle-status" data-id="<?php echo $player->id; ?>" <?php checked($player->status, 1); ?>>
                                        <span class="cjrp-toggle-slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <code class="cjrp-shortcode" onclick="navigator.clipboard.writeText(this.textContent)">[radio_player id="<?php echo $player->id; ?>"]</code>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($player->created_at)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cjrp-add-player&id=' . $player->id); ?>" class="cjrp-btn cjrp-btn-edit">Edit</a>
                                    <div class="cjrp-dropdown">
                                        <button type="button" class="cjrp-dropdown-toggle">&#8942;</button>
                                        <div class="cjrp-dropdown-menu">
                                            <a href="<?php echo admin_url('admin.php?page=cjrp-add-player&id=' . $player->id); ?>" class="cjrp-dropdown-item">&#9998; Edit</a>
                                            <a href="<?php echo home_url('/?cjrp_preview=' . $player->id); ?>" target="_blank" class="cjrp-dropdown-item">&#128065; View</a>
                                            <a href="#" class="cjrp-dropdown-item cjrp-duplicate-player" data-id="<?php echo $player->id; ?>">&#128203; Duplicate</a>
                                            <a href="#" class="cjrp-dropdown-item cjrp-embed-code" data-id="<?php echo $player->id; ?>" data-url="<?php echo esc_attr(home_url('/')); ?>">&#10094;&#10095; Embed Code</a>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Delete this player?');">
                                                <?php wp_nonce_field('cjrp_nonce', 'cjrp_nonce_field'); ?>
                                                <input type="hidden" name="cjrp_action" value="delete_player">
                                                <input type="hidden" name="player_id" value="<?php echo $player->id; ?>">
                                                <button type="submit" class="cjrp-dropdown-item cjrp-dropdown-delete">&#128465; Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function edit_player_page() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $player = $id > 0 ? CJRP_Database::get_player($id) : null;
        $stations = $id > 0 ? CJRP_Database::get_stations($id) : array();
        $controls = $player ? json_decode($player->controls, true) : CJRP_Database::get_default_controls();
        $appearance = $player ? json_decode($player->appearance, true) : CJRP_Database::get_default_appearance();
        $controls = wp_parse_args($controls, CJRP_Database::get_default_controls());
        $appearance = wp_parse_args($appearance, CJRP_Database::get_default_appearance());
        $skin = $player ? $player->skin : 'skin-1';
        $title = $player ? $player->title : '';
        ?>
        <div class="wrap cjrp-wrap">
            <div class="cjrp-header">
                <a href="<?php echo admin_url('admin.php?page=cjrp-players'); ?>" class="cjrp-back">&laquo; Back</a>
                <h1><span class="dashicons dashicons-controls-volumeon"></span> <?php echo $id > 0 ? 'Edit Player' : 'Add New Player'; ?></h1>
                <input type="text" id="cjrp-player-title-display" value="<?php echo esc_attr($title); ?>" placeholder="Player Title" class="cjrp-title-input">
                <?php if ($id > 0) : ?>
                <div class="cjrp-header-actions">
                    <a href="<?php echo home_url('/?cjrp_preview=' . $id); ?>" target="_blank" class="cjrp-btn cjrp-btn-header">&#128065; View</a>
                    <button type="button" class="cjrp-btn cjrp-btn-header cjrp-embed-code" data-id="<?php echo $id; ?>" data-url="<?php echo esc_attr(home_url('/')); ?>">&#10094;&#10095; Embed</button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Player saved.</p></div>
            <?php endif; ?>

            <form method="post" id="cjrp-player-form">
                <?php wp_nonce_field('cjrp_nonce', 'cjrp_nonce_field'); ?>
                <input type="hidden" name="cjrp_action" value="save_player">
                <input type="hidden" name="player_id" value="<?php echo $id; ?>">
                <input type="hidden" name="player_title" id="cjrp-player-title" value="<?php echo esc_attr($title); ?>">
                <input type="hidden" name="player_status" value="1">

                <div class="cjrp-editor-layout">
                    <div class="cjrp-editor-main">
                        <div class="cjrp-tabs">
                            <button type="button" class="cjrp-tab active" data-tab="stations">&#127897; Stations</button>
                            <button type="button" class="cjrp-tab" data-tab="skins">&#127912; Skins</button>
                            <button type="button" class="cjrp-tab" data-tab="controls">&#9881; Controls</button>
                            <button type="button" class="cjrp-tab" data-tab="appearance">&#127912; Appearance</button>
                        </div>

                        <!-- STATIONS TAB -->
                        <div class="cjrp-tab-content active" id="tab-stations">
                            <h3>Radio Stations</h3>
                            <p>Add your radio stations here. You can add multiple radio stations with title, stream and logo.</p>

                            <div id="cjrp-stations-list">
                                <?php if (empty($stations)) : ?>
                                    <?php $this->render_station_row(0, array()); ?>
                                <?php else : ?>
                                    <?php foreach ($stations as $i => $station) : ?>
                                        <?php $this->render_station_row($i, (array)$station); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="cjrp-btn cjrp-btn-primary" id="cjrp-add-station">+ Add New Station</button>
                        </div>

                        <!-- SKINS TAB -->
                        <div class="cjrp-tab-content" id="tab-skins">
                            <h3>Choose Player Skin</h3>
                            <p>Skins will be applied to the shortcode and popup players.</p>

                            <div class="cjrp-skins-grid">
                                <?php
                                $skins = array(
                                    'skin-1' => 'Skin 1',
                                    'skin-2' => 'Skin 2',
                                    'skin-3' => 'Skin 3',
                                );
                                foreach ($skins as $skin_id => $skin_name) :
                                ?>
                                <label class="cjrp-skin-option <?php echo $skin === $skin_id ? 'selected' : ''; ?>">
                                    <input type="radio" name="skin" value="<?php echo $skin_id; ?>" <?php checked($skin, $skin_id); ?>>
                                    <div class="cjrp-skin-preview cjrp-skin-preview-<?php echo $skin_id; ?>">
                                        <div class="cjrp-skin-demo">
                                            <div class="cjrp-demo-art">&#127911;</div>
                                            <div class="cjrp-demo-info">
                                                <span class="cjrp-demo-title">Station Title</span>
                                                <span class="cjrp-demo-status">LIVE &#9679;</span>
                                            </div>
                                            <div class="cjrp-demo-controls">
                                                &#9664; &#9654; &#9654;&#9654; &#128266; &#128279; &#9776;
                                            </div>
                                        </div>
                                    </div>
                                    <span class="cjrp-skin-name"><?php echo $skin_name; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- CONTROLS TAB -->
                        <div class="cjrp-tab-content" id="tab-controls">
                            <h3>Player Controls</h3>
                            <p>Customize the different controls and elements of the player.</p>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#128260; Autoplay</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[autoplay]" value="1" <?php checked($controls['autoplay'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Enable to start playing the radio automatically when the page loads.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#9654; Progressbar</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[progressbar]" value="1" <?php checked($controls['progressbar'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the playback progress bar. Only available for local files.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#128194; Popup Icon (Full Player)</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[popup_icon]" value="1" <?php checked($controls['popup_icon'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the Full Player popup icon.</p>
                                <div class="cjrp-sub-field">
                                    <label>Popup URL (optional)</label>
                                    <input type="text" name="controls[popup_url]" value="<?php echo esc_attr($controls['popup_url'] ?? ''); ?>" placeholder="https://example.com/my-radio-page">
                                    <p class="description">Custom URL to open when clicking the Full Player icon. Leave empty to open a popup window with the player.</p>
                                </div>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#127925; Stations Playlist</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[stations_playlist]" value="1" <?php checked($controls['stations_playlist'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the stations playlist icon.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#128266; Volume Control</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[volume_control]" value="1" <?php checked($controls['volume_control'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the volume control button.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#9898; Player Status</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[player_status]" value="1" <?php checked($controls['player_status'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the player online/offline status.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">T Show Track Title</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[show_track_title]" value="1" <?php checked($controls['show_track_title'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the currently playing track title.</p>
                                <div class="cjrp-sub-field">
                                    <label>Fallback Title</label>
                                    <input type="text" name="controls[fallback_title]" value="<?php echo esc_attr($controls['fallback_title']); ?>" placeholder="Enter a default title">
                                    <p class="description">Enter a default title when the stream does not provide metadata.</p>
                                </div>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#128100; Show Artist Name</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[show_artist_name]" value="1" <?php checked($controls['show_artist_name'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the currently playing track artist name.</p>
                                <div class="cjrp-sub-field">
                                    <label>Fallback Artist</label>
                                    <input type="text" name="controls[fallback_artist]" value="<?php echo esc_attr($controls['fallback_artist']); ?>" placeholder="Enter a default artist">
                                    <p class="description">Enter a default artist when the stream does not provide metadata.</p>
                                </div>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">&#128247; Artwork Image</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="controls[artwork_image]" value="1" <?php checked($controls['artwork_image'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Show/hide the currently playing track artwork image.</p>
                            </div>
                        </div>

                        <!-- APPEARANCE TAB -->
                        <div class="cjrp-tab-content" id="tab-appearance">
                            <h3>Player Appearance</h3>
                            <p>Customize the player appearance.</p>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Player Width</div>
                                <div class="cjrp-width-tabs">
                                    <button type="button" class="cjrp-width-tab active" data-target="desktop">Desktop</button>
                                    <button type="button" class="cjrp-width-tab" data-target="mobile">Mobile</button>
                                </div>
                                <div class="cjrp-width-field" data-device="desktop">
                                    <input type="range" name="appearance[player_width_desktop]" min="200" max="800" value="<?php echo esc_attr($appearance['player_width_desktop']); ?>" class="cjrp-range">
                                    <input type="number" value="<?php echo esc_attr($appearance['player_width_desktop']); ?>" class="cjrp-range-value" min="200" max="800">
                                    <button type="button" class="cjrp-btn-reset" data-default="350">Reset</button>
                                    <p class="description">Set the player width on desktop</p>
                                </div>
                                <div class="cjrp-width-field" data-device="mobile" style="display:none;">
                                    <input type="range" name="appearance[player_width_mobile]" min="50" max="100" value="<?php echo esc_attr($appearance['player_width_mobile']); ?>" class="cjrp-range">
                                    <input type="number" value="<?php echo esc_attr($appearance['player_width_mobile']); ?>" class="cjrp-range-value" min="50" max="100">
                                    <span>%</span>
                                    <button type="button" class="cjrp-btn-reset" data-default="100">Reset</button>
                                    <p class="description">Set the player width on mobile (%)</p>
                                </div>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Background Type</div>
                                <div class="cjrp-btn-group">
                                    <button type="button" class="cjrp-btn-option <?php echo $appearance['bg_type'] === 'color' ? 'active' : ''; ?>" data-value="color">&#127912; Color</button>
                                    <button type="button" class="cjrp-btn-option <?php echo $appearance['bg_type'] === 'image' ? 'active' : ''; ?>" data-value="image">&#128247; Image</button>
                                    <button type="button" class="cjrp-btn-option <?php echo $appearance['bg_type'] === 'blur' ? 'active' : ''; ?>" data-value="blur">&#128247; Blur</button>
                                </div>
                                <input type="hidden" name="appearance[bg_type]" value="<?php echo esc_attr($appearance['bg_type']); ?>">
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Background Color</div>
                                <div class="cjrp-btn-group">
                                    <button type="button" class="cjrp-btn-option <?php echo $appearance['bg_color_type'] === 'solid' ? 'active' : ''; ?>" data-value="solid">Solid</button>
                                    <button type="button" class="cjrp-btn-option <?php echo $appearance['bg_color_type'] === 'gradient' ? 'active' : ''; ?>" data-value="gradient">Gradient</button>
                                </div>
                                <input type="hidden" name="appearance[bg_color_type]" value="<?php echo esc_attr($appearance['bg_color_type']); ?>">
                                <div class="cjrp-color-field">
                                    <input type="text" name="appearance[bg_color]" value="<?php echo esc_attr($appearance['bg_color']); ?>" class="cjrp-color-picker">
                                </div>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Text & Button Color</div>
                                <div class="cjrp-color-field">
                                    <input type="text" name="appearance[text_button_color]" value="<?php echo esc_attr($appearance['text_button_color']); ?>" class="cjrp-color-picker">
                                </div>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Border Radius</div>
                                <input type="range" name="appearance[border_radius]" min="0" max="50" value="<?php echo esc_attr($appearance['border_radius']); ?>" class="cjrp-range">
                                <input type="number" value="<?php echo esc_attr($appearance['border_radius']); ?>" class="cjrp-range-value" min="0" max="50">
                                <button type="button" class="cjrp-btn-reset" data-default="10">Reset</button>
                                <p class="description">Set border radius in pixels.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Box Shadow</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="appearance[box_shadow]" value="1" <?php checked($appearance['box_shadow'], '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Set Player Box Shadow.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Background Image</div>
                                <input type="text" name="appearance[bg_image]" value="<?php echo esc_attr($appearance['bg_image']); ?>" class="cjrp-image-url" placeholder="Image URL">
                                <button type="button" class="cjrp-btn cjrp-upload-btn">Upload</button>
                            </div>
                        </div>

                        <div class="cjrp-form-footer">
                            <button type="submit" class="cjrp-btn cjrp-btn-primary cjrp-btn-lg">Save Player</button>
                        </div>
                    </div>

                    <!-- LIVE PREVIEW -->
                    <div class="cjrp-editor-preview">
                        <div class="cjrp-preview-window">
                            <div class="cjrp-preview-dots"><span></span><span></span><span></span></div>
                            <div class="cjrp-preview-label">&#128065; Live Preview</div>
                            <div class="cjrp-preview-icons">&#128187; &#128241;</div>
                        </div>
                        <div class="cjrp-preview-player" id="cjrp-preview">
                            <div class="cjrp-preview-art">&#127911;</div>
                            <div class="cjrp-preview-info">
                                <div class="cjrp-preview-title">Station Title</div>
                                <div class="cjrp-preview-status"><span class="cjrp-badge-offline">OFFLINE</span> &#9679;</div>
                            </div>
                            <div class="cjrp-preview-btns">
                                <span class="cjrp-preview-play">&#9654;</span>
                                <span class="cjrp-preview-vol">&#128266;</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_station_row($index, $data) {
        $title = $data['title'] ?? '';
        $source_type = $data['source_type'] ?? 'stream_url';
        $source_url = $data['source_url'] ?? '';
        $art_url = $data['art_url'] ?? '';
        ?>
        <div class="cjrp-station-row" data-index="<?php echo $index; ?>">
            <div class="cjrp-station-header">
                <span class="cjrp-station-num"><?php echo $index + 1; ?>. <?php echo esc_html($title ?: 'Station Title'); ?></span>
                <button type="button" class="cjrp-station-toggle">&#9660;</button>
                <button type="button" class="cjrp-station-remove" title="Remove">&times;</button>
            </div>
            <div class="cjrp-station-body">
                <div class="cjrp-field">
                    <label>Title</label>
                    <input type="text" name="stations[<?php echo $index; ?>][title]" value="<?php echo esc_attr($title); ?>" placeholder="Station Title">
                    <p class="description">Enter the station title.</p>
                </div>
                <div class="cjrp-field">
                    <label>Source</label>
                    <div class="cjrp-source-type">
                        <span>Source Type</span>
                        <div class="cjrp-btn-group">
                            <button type="button" class="cjrp-btn-option <?php echo $source_type === 'stream_url' ? 'active' : ''; ?>" data-value="stream_url">&#128279; Stream URL</button>
                            <button type="button" class="cjrp-btn-option <?php echo $source_type === 'local_audio' ? 'active' : ''; ?>" data-value="local_audio">&#127925; Local Audio</button>
                            <button type="button" class="cjrp-btn-option <?php echo $source_type === 'youtube' ? 'active' : ''; ?>" data-value="youtube">&#9654; YouTube</button>
                            <button type="button" class="cjrp-btn-option <?php echo $source_type === 'embed' ? 'active' : ''; ?>" data-value="embed">&#128187; Embed</button>
                        </div>
                        <input type="hidden" name="stations[<?php echo $index; ?>][source_type]" value="<?php echo esc_attr($source_type); ?>" class="cjrp-source-type-input">
                    </div>
                    <?php if ($source_type === 'embed') : ?>
                        <textarea name="stations[<?php echo $index; ?>][source_url]" placeholder="Paste embed/iframe code here" class="cjrp-source-url cjrp-source-embed" rows="4" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea($source_url); ?></textarea>
                    <?php else : ?>
                        <input type="text" name="stations[<?php echo $index; ?>][source_url]" value="<?php echo esc_attr($source_url); ?>" placeholder="Enter the station stream URL" class="cjrp-source-url cjrp-source-input">
                    <?php endif; ?>
                    <p class="description">Enter a playable live stream URL, local audio file URL, YouTube URL, or paste embed/iframe code.</p>
                </div>
                <div class="cjrp-field">
                    <label>Art</label>
                    <div class="cjrp-art-field">
                        <?php if ($art_url) : ?>
                            <img src="<?php echo esc_url($art_url); ?>" class="cjrp-art-preview" style="width:50px;height:50px;border-radius:50%;object-fit:cover;">
                        <?php else : ?>
                            <div class="cjrp-art-placeholder">&#127911;</div>
                        <?php endif; ?>
                        <input type="text" name="stations[<?php echo $index; ?>][art_url]" value="<?php echo esc_attr($art_url); ?>" placeholder="Image URL" class="cjrp-art-url">
                        <button type="button" class="cjrp-btn cjrp-upload-btn">&#128228;</button>
                        <button type="button" class="cjrp-btn cjrp-btn-delete cjrp-remove-art">&#128465;</button>
                    </div>
                    <p class="description">Upload a station logo or image.</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function settings_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'sticky';
        $players = CJRP_Database::get_players();
        ?>
        <div class="wrap cjrp-wrap">
            <div class="cjrp-header">
                <h1><span class="dashicons dashicons-controls-volumeon"></span> Radio Player Settings</h1>
                <button type="submit" form="cjrp-settings-form" class="cjrp-btn cjrp-btn-primary">&#10004; Save Changes</button>
            </div>

            <?php if (isset($_GET['saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <form method="post" id="cjrp-settings-form">
                <?php wp_nonce_field('cjrp_nonce', 'cjrp_nonce_field'); ?>
                <input type="hidden" name="cjrp_action" value="save_settings">
                <input type="hidden" name="active_tab" value="<?php echo esc_attr($tab); ?>" id="cjrp-active-tab">

                <div class="cjrp-settings-layout">
                    <div class="cjrp-settings-nav">
                        <a href="#" class="cjrp-settings-tab <?php echo $tab === 'sticky' ? 'active' : ''; ?>" data-tab="sticky">&#128204; Sticky Player Settings</a>
                        <a href="#" class="cjrp-settings-tab <?php echo $tab === 'popup' ? 'active' : ''; ?>" data-tab="popup">&#128194; Popup Player Settings</a>
                        <a href="#" class="cjrp-settings-tab <?php echo $tab === 'playlist' ? 'active' : ''; ?>" data-tab="playlist">&#127925; Playlist Settings</a>
                        <a href="#" class="cjrp-settings-tab <?php echo $tab === 'css' ? 'active' : ''; ?>" data-tab="css">&#128187; Custom CSS</a>
                    </div>

                    <div class="cjrp-settings-content">
                        <!-- STICKY SETTINGS -->
                        <div class="cjrp-settings-panel <?php echo $tab === 'sticky' ? 'active' : ''; ?>" id="settings-sticky">
                            <h2>&#128204; Sticky Player Settings</h2>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Always Play in Sticky</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="cjrp_sticky_enabled" value="1" <?php checked(get_option('cjrp_sticky_enabled'), '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Enable this option to play all streams using the sticky player automatically.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Default Sticky Player</div>
                                <select name="cjrp_sticky_player_id">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($players as $p) : ?>
                                        <option value="<?php echo $p->id; ?>" <?php selected(get_option('cjrp_sticky_player_id'), $p->id); ?>><?php echo esc_html($p->title ?: 'Player #' . $p->id); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select the default sticky player to use.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Sticky Player Style</div>
                                <div class="cjrp-btn-group">
                                    <?php
                                    $sticky_style = get_option('cjrp_sticky_style', 'fullwidth');
                                    $styles = array('fullwidth' => 'Fullwidth', 'mini' => 'Mini Fullwidth', 'floating' => 'Floating');
                                    foreach ($styles as $val => $label) :
                                    ?>
                                    <button type="button" class="cjrp-btn-option <?php echo $sticky_style === $val ? 'active' : ''; ?>" data-value="<?php echo $val; ?>"><?php echo $label; ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="cjrp_sticky_style" value="<?php echo esc_attr($sticky_style); ?>">
                                <p class="description">Select the sticky player style.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Sticky Player Width</div>
                                <div class="cjrp-btn-group">
                                    <?php $sticky_width = get_option('cjrp_sticky_width', 'fullscreen'); ?>
                                    <button type="button" class="cjrp-btn-option <?php echo $sticky_width === 'fullscreen' ? 'active' : ''; ?>" data-value="fullscreen">Full Screen</button>
                                    <button type="button" class="cjrp-btn-option <?php echo $sticky_width === 'content' ? 'active' : ''; ?>" data-value="content">Content Width</button>
                                </div>
                                <input type="hidden" name="cjrp_sticky_width" value="<?php echo esc_attr($sticky_width); ?>">
                                <p class="description">Full Screen = edge to edge. Content Width = matches the website content area.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Sticky Player Height</div>
                                <input type="range" name="cjrp_sticky_height" min="40" max="120" value="<?php echo esc_attr(get_option('cjrp_sticky_height', '55')); ?>" class="cjrp-range">
                                <input type="number" value="<?php echo esc_attr(get_option('cjrp_sticky_height', '55')); ?>" class="cjrp-range-value" min="40" max="120">
                                <button type="button" class="cjrp-btn-reset" data-default="55">Reset</button>
                                <p class="description">Set the sticky player height in pixels.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Sticky Player Position</div>
                                <div class="cjrp-btn-group">
                                    <?php $sticky_pos = get_option('cjrp_sticky_position', 'bottom'); ?>
                                    <button type="button" class="cjrp-btn-option <?php echo $sticky_pos === 'top' ? 'active' : ''; ?>" data-value="top">Top</button>
                                    <button type="button" class="cjrp-btn-option <?php echo $sticky_pos === 'bottom' ? 'active' : ''; ?>" data-value="bottom">Bottom</button>
                                </div>
                                <input type="hidden" name="cjrp_sticky_position" value="<?php echo esc_attr($sticky_pos); ?>">
                                <p class="description">Set the fullwidth sticky player position.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Display On Mobile</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="cjrp_sticky_mobile" value="1" <?php checked(get_option('cjrp_sticky_mobile', '1'), '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Display the sticky player on mobile devices.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Exclude Pages</div>
                                <input type="text" name="cjrp_sticky_exclude_pages" value="<?php echo esc_attr(get_option('cjrp_sticky_exclude_pages', '')); ?>" placeholder="Page IDs separated by commas">
                                <p class="description">Enter page/post IDs where the sticky player will be hidden (comma separated).</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Exclude All</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="cjrp_sticky_exclude_all" value="1" <?php checked(get_option('cjrp_sticky_exclude_all'), '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <span style="margin-left:15px;font-weight:600;">Except:</span>
                                <input type="text" name="cjrp_sticky_except_pages" value="<?php echo esc_attr(get_option('cjrp_sticky_except_pages', '')); ?>" placeholder="Page IDs" style="width:200px;margin-left:10px;">
                                <p class="description">When Excluded All, the sticky player will only show on the exception posts/pages.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Minimized Player Image</div>
                                <div class="cjrp-art-field">
                                    <?php $min_img = get_option('cjrp_sticky_minimized_image', ''); ?>
                                    <?php if ($min_img) : ?>
                                        <img src="<?php echo esc_url($min_img); ?>" class="cjrp-art-preview" style="width:50px;height:50px;border-radius:50%;object-fit:cover;">
                                    <?php endif; ?>
                                    <input type="text" name="cjrp_sticky_minimized_image" value="<?php echo esc_attr($min_img); ?>" placeholder="Image URL" class="cjrp-image-url">
                                    <button type="button" class="cjrp-btn cjrp-upload-btn">&#128228;</button>
                                </div>
                                <p class="description">Show the image when the sticky player is in minimized state.</p>
                            </div>
                        </div>

                        <!-- POPUP SETTINGS -->
                        <div class="cjrp-settings-panel <?php echo $tab === 'popup' ? 'active' : ''; ?>" id="settings-popup">
                            <h2>&#128194; Popup Settings</h2>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Always Popup</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="cjrp_popup_always" value="1" <?php checked(get_option('cjrp_popup_always'), '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Turn it ON to play the player always in the popup window when the user clicks on the play button.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Customize Popup Player Size</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="cjrp_popup_custom_size" value="1" <?php checked(get_option('cjrp_popup_custom_size'), '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Turn ON to customize the popup player width and height.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Popup Width</div>
                                <input type="number" name="cjrp_popup_width" value="<?php echo esc_attr(get_option('cjrp_popup_width', '400')); ?>" min="200" max="800"> px
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Popup Height</div>
                                <input type="number" name="cjrp_popup_height" value="<?php echo esc_attr(get_option('cjrp_popup_height', '500')); ?>" min="200" max="800"> px
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Popup Header Content</div>
                                <p class="description">Add any custom content to the popup header. You can use HTML tags.</p>
                                <?php wp_editor(get_option('cjrp_popup_header', ''), 'cjrp_popup_header', array('textarea_rows' => 6, 'media_buttons' => true)); ?>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Popup Footer Content</div>
                                <p class="description">Add any custom content to the popup footer. You can use HTML tags.</p>
                                <?php wp_editor(get_option('cjrp_popup_footer', ''), 'cjrp_popup_footer', array('textarea_rows' => 6, 'media_buttons' => true)); ?>
                            </div>
                        </div>

                        <!-- PLAYLIST SETTINGS -->
                        <div class="cjrp-settings-panel <?php echo $tab === 'playlist' ? 'active' : ''; ?>" id="settings-playlist">
                            <h2>&#127925; Playlist Settings</h2>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Opened Playlist</div>
                                <label class="cjrp-toggle">
                                    <input type="checkbox" name="cjrp_playlist_opened" value="1" <?php checked(get_option('cjrp_playlist_opened', '1'), '1'); ?>>
                                    <span class="cjrp-toggle-slider"></span>
                                </label>
                                <p class="description">Enable to keep open the station playlist by default.</p>
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Playlist Height</div>
                                <div class="cjrp-btn-group">
                                    <?php $pl_height = get_option('cjrp_playlist_height', 'full'); ?>
                                    <button type="button" class="cjrp-btn-option <?php echo $pl_height === 'full' ? 'active' : ''; ?>" data-value="full">Full</button>
                                    <button type="button" class="cjrp-btn-option <?php echo $pl_height === 'custom' ? 'active' : ''; ?>" data-value="custom">Custom</button>
                                </div>
                                <input type="hidden" name="cjrp_playlist_height" value="<?php echo esc_attr($pl_height); ?>">
                            </div>

                            <div class="cjrp-control-row">
                                <div class="cjrp-control-label">Playlist Custom Height</div>
                                <input type="range" name="cjrp_playlist_custom_height" min="100" max="600" value="<?php echo esc_attr(get_option('cjrp_playlist_custom_height', '300')); ?>" class="cjrp-range">
                                <input type="number" value="<?php echo esc_attr(get_option('cjrp_playlist_custom_height', '300')); ?>" class="cjrp-range-value" min="100" max="600">
                                <button type="button" class="cjrp-btn-reset" data-default="300">Reset</button>
                                <p class="description">Set the custom height of the playlist in px.</p>
                            </div>
                        </div>

                        <!-- CUSTOM CSS -->
                        <div class="cjrp-settings-panel <?php echo $tab === 'css' ? 'active' : ''; ?>" id="settings-css">
                            <h2>&#128187; Custom CSS</h2>
                            <div class="cjrp-control-row">
                                <textarea name="cjrp_custom_css" rows="15" style="width:100%;font-family:monospace;font-size:13px;"><?php echo esc_textarea(get_option('cjrp_custom_css', '')); ?></textarea>
                                <p class="description">Add custom CSS to override the player styles.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}
