(function($) {
    'use strict';

    $(document).ready(function() {

        // Sync title input
        $('#cjrp-player-title-display').on('input', function() {
            $('#cjrp-player-title').val($(this).val());
        });

        // Tabs
        $('.cjrp-tab').on('click', function() {
            var tab = $(this).data('tab');
            $('.cjrp-tab').removeClass('active');
            $(this).addClass('active');
            $('.cjrp-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // Settings tabs
        $('.cjrp-settings-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            $('.cjrp-settings-tab').removeClass('active');
            $(this).addClass('active');
            $('.cjrp-settings-panel').removeClass('active');
            $('#settings-' + tab).addClass('active');
            $('#cjrp-active-tab').val(tab);
        });

        // Button group options
        $(document).on('click', '.cjrp-btn-option', function() {
            var $group = $(this).closest('.cjrp-btn-group') || $(this).parent();
            $group.find('.cjrp-btn-option').removeClass('active');
            $(this).addClass('active');
            var value = $(this).data('value');
            var $hidden = $(this).closest('.cjrp-control-row, .cjrp-source-type, .cjrp-field').find('input[type="hidden"]');
            if ($hidden.length) {
                $hidden.val(value);
            }
            // Swap input/textarea for embed source type
            if ($(this).closest('.cjrp-source-type').length) {
                var $field = $(this).closest('.cjrp-field');
                var $existing = $field.find('.cjrp-source-url');
                var name = $existing.attr('name');
                var val = $existing.val();
                if (value === 'embed') {
                    if (!$existing.is('textarea')) {
                        $existing.replaceWith('<textarea name="' + name + '" placeholder="Paste embed/iframe code here" class="cjrp-source-url cjrp-source-embed" rows="4" style="width:100%;font-family:monospace;font-size:12px;">' + $('<div>').text(val).html() + '</textarea>');
                    }
                } else {
                    if ($existing.is('textarea')) {
                        $existing.replaceWith('<input type="text" name="' + name + '" value="" placeholder="Enter the station stream URL" class="cjrp-source-url cjrp-source-input">');
                    }
                }
            }
        });

        // Range slider sync
        $(document).on('input', '.cjrp-range', function() {
            $(this).siblings('.cjrp-range-value').val($(this).val());
            // Also update the hidden name input
            $(this).closest('.cjrp-width-field, .cjrp-control-row').find('input[name]').first().val($(this).val());
        });

        $(document).on('input', '.cjrp-range-value', function() {
            $(this).siblings('.cjrp-range').val($(this).val());
            $(this).siblings('input[name]').val($(this).val());
        });

        // Reset buttons
        $(document).on('click', '.cjrp-btn-reset', function() {
            var def = $(this).data('default');
            $(this).siblings('.cjrp-range').val(def);
            $(this).siblings('.cjrp-range-value').val(def);
            $(this).siblings('input[name]').val(def);
        });

        // Width tabs (desktop/mobile)
        $(document).on('click', '.cjrp-width-tab', function() {
            var target = $(this).data('target');
            $(this).siblings().removeClass('active');
            $(this).addClass('active');
            $(this).closest('.cjrp-control-row').find('.cjrp-width-field').hide();
            $(this).closest('.cjrp-control-row').find('[data-device="' + target + '"]').show();
        });

        // Skin selection
        $(document).on('change', '.cjrp-skin-option input[type="radio"]', function() {
            $('.cjrp-skin-option').removeClass('selected');
            $(this).closest('.cjrp-skin-option').addClass('selected');
        });

        // Station toggle
        $(document).on('click', '.cjrp-station-header', function(e) {
            if ($(e.target).hasClass('cjrp-station-remove')) return;
            $(this).closest('.cjrp-station-row').find('.cjrp-station-body').slideToggle(200);
        });

        // Remove station
        $(document).on('click', '.cjrp-station-remove', function(e) {
            e.stopPropagation();
            if (confirm('Remove this station?')) {
                $(this).closest('.cjrp-station-row').fadeOut(200, function() {
                    $(this).remove();
                    reindexStations();
                });
            }
        });

        // Add station
        $('#cjrp-add-station').on('click', function() {
            var index = $('.cjrp-station-row').length;
            var html = getStationRowHtml(index);
            $('#cjrp-stations-list').append(html);
        });

        function getStationRowHtml(index) {
            return '<div class="cjrp-station-row" data-index="' + index + '">' +
                '<div class="cjrp-station-header">' +
                '<span class="cjrp-station-num">' + (index + 1) + '. Station Title</span>' +
                '<button type="button" class="cjrp-station-toggle">&#9660;</button>' +
                '<button type="button" class="cjrp-station-remove">&times;</button>' +
                '</div>' +
                '<div class="cjrp-station-body">' +
                '<div class="cjrp-field">' +
                '<label>Title</label>' +
                '<input type="text" name="stations[' + index + '][title]" placeholder="Station Title">' +
                '<p class="description">Enter the station title.</p>' +
                '</div>' +
                '<div class="cjrp-field">' +
                '<label>Source</label>' +
                '<div class="cjrp-source-type">' +
                '<span>Source Type</span>' +
                '<div class="cjrp-btn-group">' +
                '<button type="button" class="cjrp-btn-option active" data-value="stream_url">&#128279; Stream URL</button>' +
                '<button type="button" class="cjrp-btn-option" data-value="local_audio">&#127925; Local Audio</button>' +
                '<button type="button" class="cjrp-btn-option" data-value="youtube">&#9654; YouTube</button>' +
                '<button type="button" class="cjrp-btn-option" data-value="embed">&#128187; Embed</button>' +
                '</div>' +
                '<input type="hidden" name="stations[' + index + '][source_type]" value="stream_url" class="cjrp-source-type-input">' +
                '</div>' +
                '<input type="text" name="stations[' + index + '][source_url]" placeholder="Enter the station stream URL" class="cjrp-source-url cjrp-source-input">' +
                '<p class="description">Enter a playable live stream URL, local audio file URL, YouTube URL, or paste embed/iframe code.</p>' +
                '</div>' +
                '<div class="cjrp-field">' +
                '<label>Art</label>' +
                '<div class="cjrp-art-field">' +
                '<div class="cjrp-art-placeholder">&#127911;</div>' +
                '<input type="text" name="stations[' + index + '][art_url]" placeholder="Image URL" class="cjrp-art-url">' +
                '<button type="button" class="cjrp-btn cjrp-upload-btn">&#128228;</button>' +
                '<button type="button" class="cjrp-btn cjrp-btn-delete cjrp-remove-art">&#128465;</button>' +
                '</div>' +
                '<p class="description">Upload a station logo or image.</p>' +
                '</div>' +
                '</div>' +
                '</div>';
        }

        function reindexStations() {
            $('.cjrp-station-row').each(function(i) {
                $(this).attr('data-index', i);
                $(this).find('.cjrp-station-num').text((i + 1) + '. ' + ($(this).find('input[name$="[title]"]').val() || 'Station Title'));
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/stations\[\d+\]/, 'stations[' + i + ']'));
                    }
                });
            });
        }

        // Update station name in header when typing
        $(document).on('input', 'input[name$="[title]"]', function() {
            var $row = $(this).closest('.cjrp-station-row');
            var index = $row.data('index');
            $row.find('.cjrp-station-num').text((index + 1) + '. ' + ($(this).val() || 'Station Title'));
        });

        // Toggle player status via AJAX
        $(document).on('change', '.cjrp-toggle-status', function() {
            var id = $(this).data('id');
            var status = $(this).is(':checked') ? 1 : 0;
            $.post(cjrpAdmin.ajaxUrl, {
                action: 'cjrp_toggle_status',
                nonce: cjrpAdmin.nonce,
                player_id: id,
                status: status
            });
        });

        // Media upload
        $(document).on('click', '.cjrp-upload-btn', function(e) {
            e.preventDefault();
            var $input = $(this).siblings('input[type="text"]');
            var frame = wp.media({
                title: 'Select Image',
                button: { text: 'Use this image' },
                multiple: false
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                var $preview = $input.siblings('.cjrp-art-preview, img');
                if ($preview.length) {
                    $preview.attr('src', attachment.url);
                } else {
                    var $placeholder = $input.siblings('.cjrp-art-placeholder');
                    if ($placeholder.length) {
                        $placeholder.replaceWith('<img src="' + attachment.url + '" class="cjrp-art-preview" style="width:50px;height:50px;border-radius:50%;object-fit:cover;">');
                    }
                }
            });
            frame.open();
        });

        // Remove art
        $(document).on('click', '.cjrp-remove-art', function() {
            var $field = $(this).closest('.cjrp-art-field');
            $field.find('input').val('');
            var $img = $field.find('.cjrp-art-preview, img');
            if ($img.length) {
                $img.replaceWith('<div class="cjrp-art-placeholder">&#127911;</div>');
            }
        });

        // Color picker
        if ($.fn.wpColorPicker) {
            $('.cjrp-color-picker').wpColorPicker();
        }

        // Dropdown toggle
        $(document).on('click', '.cjrp-dropdown-toggle', function(e) {
            e.stopPropagation();
            var $dropdown = $(this).closest('.cjrp-dropdown');
            $('.cjrp-dropdown').not($dropdown).removeClass('open');
            $dropdown.toggleClass('open');
        });

        $(document).on('click', function() {
            $('.cjrp-dropdown').removeClass('open');
        });

        // Duplicate player
        $(document).on('click', '.cjrp-duplicate-player', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $form = $('<form method="post">');
            $form.append('<input type="hidden" name="cjrp_action" value="duplicate_player">');
            $form.append('<input type="hidden" name="player_id" value="' + id + '">');
            $form.append($('[name="cjrp_nonce_field"]').first().clone());
            $('body').append($form);
            $form.submit();
        });

        // Embed code modal
        if (!$('#cjrp-embed-modal').length) {
            $('body').append(
                '<div class="cjrp-modal-overlay" id="cjrp-embed-modal">' +
                '<div class="cjrp-modal">' +
                '<h3>&lt;&gt; Embed Code</h3>' +
                '<p>Copy this code to embed the radio player on any website:</p>' +
                '<textarea id="cjrp-embed-textarea" readonly></textarea>' +
                '<div class="cjrp-modal-footer">' +
                '<button type="button" class="cjrp-btn" id="cjrp-embed-close">Close</button>' +
                '<button type="button" class="cjrp-btn cjrp-btn-primary" id="cjrp-embed-copy">Copy Code</button>' +
                '</div>' +
                '</div>' +
                '</div>'
            );
        }

        $(document).on('click', '.cjrp-embed-code', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var url = $(this).data('url');
            var embedCode = '<iframe src="' + url + '?cjrp_embed=' + id + '" width="100%" height="400" frameborder="0" allow="autoplay" style="border:none;border-radius:10px;"></iframe>';
            $('#cjrp-embed-textarea').val(embedCode);
            $('#cjrp-embed-modal').addClass('visible');
            $('.cjrp-dropdown').removeClass('open');
        });

        $(document).on('click', '#cjrp-embed-close', function() {
            $('#cjrp-embed-modal').removeClass('visible');
        });

        $(document).on('click', '#cjrp-embed-copy', function() {
            var textarea = document.getElementById('cjrp-embed-textarea');
            textarea.select();
            document.execCommand('copy');
            $(this).text('Copied!');
            setTimeout(function() { $('#cjrp-embed-copy').text('Copy Code'); }, 2000);
        });

        $(document).on('click', '.cjrp-modal-overlay', function(e) {
            if ($(e.target).hasClass('cjrp-modal-overlay')) {
                $(this).removeClass('visible');
            }
        });
    });
})(jQuery);
