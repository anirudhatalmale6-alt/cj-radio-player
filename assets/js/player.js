(function() {
    'use strict';

    var audio = null;
    var currentPlayerId = null;
    var currentStationIndex = 0;
    var isPlaying = false;
    var ytPlayer = null;

    function init() {
        audio = new Audio();
        audio.volume = 0.8;
        audio.crossOrigin = 'anonymous';

        audio.addEventListener('playing', function() {
            isPlaying = true;
            updateAllPlayButtons(true);
            updateStatus(true);
        });

        audio.addEventListener('pause', function() {
            isPlaying = false;
            updateAllPlayButtons(false);
        });

        audio.addEventListener('ended', function() {
            isPlaying = false;
            updateAllPlayButtons(false);
        });

        audio.addEventListener('error', function() {
            isPlaying = false;
            updateAllPlayButtons(false);
            updateStatus(false);
        });

        audio.addEventListener('timeupdate', function() {
            if (audio.duration && isFinite(audio.duration)) {
                var pct = (audio.currentTime / audio.duration) * 100;
                var bars = document.querySelectorAll('.cjrp-progress-bar');
                bars.forEach(function(bar) { bar.style.width = pct + '%'; });
            }
        });

        bindShortcodePlayers();
        bindStickyPlayer();
        handleAutoplay();
    }

    function bindShortcodePlayers() {
        var players = document.querySelectorAll('.cjrp-player');
        players.forEach(function(el) {
            var playerId = el.dataset.playerId;

            // Play button
            var playBtn = el.querySelector('.cjrp-btn-play');
            if (playBtn) {
                playBtn.addEventListener('click', function() {
                    togglePlay(playerId, el);
                });
            }

            // Prev/Next
            var prevBtn = el.querySelector('.cjrp-btn-prev');
            var nextBtn = el.querySelector('.cjrp-btn-next');
            if (prevBtn) prevBtn.addEventListener('click', function() { changeStation(playerId, -1, el); });
            if (nextBtn) nextBtn.addEventListener('click', function() { changeStation(playerId, 1, el); });

            // Volume toggle (click = show slider, long press / double = mute)
            var volBtn = el.querySelector('.cjrp-btn-volume');
            if (volBtn) {
                volBtn.addEventListener('click', function() {
                    var slider = el.querySelector('.cjrp-volume-slider');
                    if (slider) {
                        slider.classList.toggle('visible');
                    } else {
                        toggleMute();
                    }
                });
            }

            // Volume slider
            var volRange = el.querySelector('.cjrp-vol-range');
            if (volRange) {
                volRange.addEventListener('input', function() {
                    setVolume(this.value);
                });
            }

            // Playlist toggle
            var plBtn = el.querySelector('.cjrp-btn-playlist');
            if (plBtn) {
                plBtn.addEventListener('click', function() {
                    var pl = el.querySelector('.cjrp-playlist');
                    if (pl) pl.style.display = pl.style.display === 'none' ? 'block' : 'none';
                });
            }

            // Playlist items
            var plItems = el.querySelectorAll('.cjrp-playlist-item');
            plItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    var idx = parseInt(this.dataset.index);
                    playStation(playerId, idx, el);
                });
            });

            // Popup / Full Player
            var popupBtn = el.querySelector('.cjrp-btn-popup');
            if (popupBtn) {
                popupBtn.addEventListener('click', function() {
                    var customUrl = this.getAttribute('data-popup-url');
                    if (customUrl) {
                        window.open(customUrl, '_blank');
                    } else {
                        openPopup(playerId);
                    }
                });
            }

            // Progress click
            var progress = el.querySelector('.cjrp-progress');
            if (progress) {
                progress.addEventListener('click', function(e) {
                    if (audio.duration && isFinite(audio.duration)) {
                        var rect = this.getBoundingClientRect();
                        var pct = (e.clientX - rect.left) / rect.width;
                        audio.currentTime = pct * audio.duration;
                    }
                });
            }
        });
    }

    function bindStickyPlayer() {
        var sticky = document.querySelector('.cjrp-sticky');
        if (!sticky) return;

        var playerId = sticky.dataset.playerId;

        // Play
        var playBtn = sticky.querySelector('.cjrp-sticky-play');
        if (playBtn) {
            playBtn.addEventListener('click', function() {
                togglePlay(playerId, sticky);
            });
        }

        // Prev/Next
        var prevBtn = sticky.querySelector('.cjrp-sticky-prev');
        var nextBtn = sticky.querySelector('.cjrp-sticky-next');
        if (prevBtn) prevBtn.addEventListener('click', function() { changeStation(playerId, -1, sticky); });
        if (nextBtn) nextBtn.addEventListener('click', function() { changeStation(playerId, 1, sticky); });

        // Volume
        var volBtn = sticky.querySelector('.cjrp-sticky-volume');
        if (volBtn) {
            volBtn.addEventListener('click', function() {
                var slider = sticky.querySelector('.cjrp-sticky-vol-slider');
                if (slider) {
                    slider.classList.toggle('visible');
                } else {
                    toggleMute();
                }
            });
        }

        var volRange = sticky.querySelector('.cjrp-vol-range');
        if (volRange) {
            volRange.addEventListener('input', function() {
                setVolume(this.value);
            });
        }

        // Add body class for sticky position and dynamic padding
        var stickyPos = sticky.classList.contains('cjrp-sticky-top') ? 'top' : 'bottom';
        document.body.classList.add('cjrp-has-sticky-' + stickyPos);
        var stickyH = sticky.offsetHeight || 60;
        document.body.style['padding' + (stickyPos === 'top' ? 'Top' : 'Bottom')] = (stickyH + 5) + 'px';

        // Close (minimize)
        var closeBtn = sticky.querySelector('.cjrp-sticky-close');
        var minimizedBtn = document.querySelector('.cjrp-minimized-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                sticky.classList.add('cjrp-sticky-hidden');
                document.body.classList.remove('cjrp-has-sticky-' + stickyPos);
                document.body.style['padding' + (stickyPos === 'top' ? 'Top' : 'Bottom')] = '';
                if (minimizedBtn) {
                    minimizedBtn.style.display = 'flex';
                }
            });
        }

        // Minimized button click (maximize)
        if (minimizedBtn) {
            minimizedBtn.addEventListener('click', function() {
                sticky.classList.remove('cjrp-sticky-hidden');
                document.body.classList.add('cjrp-has-sticky-' + stickyPos);
                var sH = sticky.offsetHeight || 60;
                document.body.style['padding' + (stickyPos === 'top' ? 'Top' : 'Bottom')] = (sH + 5) + 'px';
                minimizedBtn.style.display = 'none';
                if (!isPlaying && currentPlayerId == playerId) {
                    audio.play().catch(function(){});
                } else if (!currentPlayerId) {
                    playStation(playerId, 0, sticky);
                }
            });
        }

        // Playlist
        var plBtn = sticky.querySelector('.cjrp-sticky-playlist-btn');
        if (plBtn) {
            plBtn.addEventListener('click', function() {
                var pl = sticky.querySelector('.cjrp-sticky-playlist');
                if (pl) pl.style.display = pl.style.display === 'none' ? 'block' : 'none';
            });
        }

        var plItems = sticky.querySelectorAll('.cjrp-playlist-item');
        plItems.forEach(function(item) {
            item.addEventListener('click', function() {
                var idx = parseInt(this.dataset.index);
                playStation(playerId, idx, sticky);
            });
        });

        // Popup / Full Player
        var popupBtn = sticky.querySelector('.cjrp-sticky-popup');
        if (popupBtn) {
            popupBtn.addEventListener('click', function() {
                var customUrl = this.getAttribute('data-popup-url');
                if (customUrl) {
                    window.open(customUrl, '_blank');
                } else {
                    openPopup(playerId);
                }
            });
        }

        // Autoplay sticky
        if (cjrpData.settings.stickyEnabled === '1') {
            var playerData = cjrpData.players[playerId];
            if (playerData && playerData.controls && playerData.controls.autoplay === '1') {
                setTimeout(function() {
                    playStation(playerId, 0, sticky);
                }, 500);
            }
        }
    }

    function togglePlay(playerId, context) {
        if (currentPlayerId == playerId && isPlaying) {
            audio.pause();
        } else if (currentPlayerId == playerId && !isPlaying) {
            audio.play().catch(function(){});
        } else {
            playStation(playerId, 0, context);
        }
    }

    function playStation(playerId, index, context) {
        var playerData = cjrpData.players[playerId];
        if (!playerData || !playerData.stations || !playerData.stations[index]) return;

        var station = playerData.stations[index];
        currentPlayerId = playerId;
        currentStationIndex = index;

        // Handle embed code
        if (station.sourceType === 'embed') {
            playEmbed(station.sourceUrl);
            updatePlayerUI(station, context);
            isPlaying = true;
            updateAllPlayButtons(true);
            updateStatus(true);
            return;
        }

        // Handle YouTube
        if (station.sourceType === 'youtube') {
            var videoId = extractYouTubeId(station.sourceUrl);
            if (videoId) {
                playYouTube(videoId);
                updatePlayerUI(station, context);
                return;
            }
        }

        // Standard audio
        audio.src = station.sourceUrl;
        audio.load();
        audio.play().catch(function(e) {
            console.log('Autoplay blocked:', e);
        });

        updatePlayerUI(station, context);
    }

    function updatePlayerUI(station, context) {
        // Update titles everywhere
        var titles = document.querySelectorAll('.cjrp-title, .cjrp-sticky-title');
        titles.forEach(function(el) { el.textContent = station.title; });

        // Update sticky text
        var stickyTexts = document.querySelectorAll('.cjrp-sticky-text');
        stickyTexts.forEach(function(el) {
            el.innerHTML = 'Escuchando <strong class="cjrp-sticky-title">' + escapeHtml(station.title) + '</strong>';
        });

        // Update art
        if (station.artUrl) {
            var arts = document.querySelectorAll('.cjrp-art img, .cjrp-sticky-art');
            arts.forEach(function(el) {
                if (el.tagName === 'IMG') el.src = station.artUrl;
            });
        }

        // Update playlist active state
        var playlistItems = document.querySelectorAll('.cjrp-playlist-item');
        playlistItems.forEach(function(item) {
            item.classList.toggle('active', parseInt(item.dataset.index) === currentStationIndex);
        });

        // Show sticky if hidden
        var sticky = document.querySelector('.cjrp-sticky');
        if (sticky) {
            sticky.classList.remove('cjrp-sticky-hidden');
        }
    }

    function changeStation(playerId, direction, context) {
        var playerData = cjrpData.players[playerId];
        if (!playerData || !playerData.stations) return;

        var total = playerData.stations.length;
        if (total <= 1) return;

        var newIndex = currentPlayerId == playerId ? currentStationIndex + direction : 0;
        if (newIndex >= total) newIndex = 0;
        if (newIndex < 0) newIndex = total - 1;

        playStation(playerId, newIndex, context);
    }

    function updateAllPlayButtons(playing) {
        var btns = document.querySelectorAll('.cjrp-btn-play, .cjrp-sticky-play');
        btns.forEach(function(btn) {
            if (playing) {
                btn.innerHTML = '&#10074;&#10074;';
                btn.classList.add('playing');
            } else {
                btn.innerHTML = '&#9654;';
                btn.classList.remove('playing');
            }
        });
    }

    function updateStatus(live) {
        var badges = document.querySelectorAll('.cjrp-badge');
        badges.forEach(function(badge) {
            if (live) {
                badge.textContent = 'LIVE';
                badge.className = 'cjrp-badge cjrp-badge-live';
            } else {
                badge.textContent = 'OFFLINE';
                badge.className = 'cjrp-badge cjrp-badge-offline';
            }
        });

        var dots = document.querySelectorAll('.cjrp-status-dot');
        dots.forEach(function(dot) {
            dot.classList.toggle('cjrp-dot-live', live);
        });
    }

    var savedVolume = 0.8;

    function setVolume(val) {
        var vol = val / 100;
        savedVolume = vol;
        try { audio.volume = vol; } catch(e) {}
        syncVolumeSliders(val);
        updateVolumeIcons(vol > 0);
    }

    function toggleMute() {
        if (audio.volume > 0) {
            savedVolume = audio.volume;
            try { audio.volume = 0; } catch(e) {}
            audio.muted = true;
            syncVolumeSliders(0);
            updateVolumeIcons(false);
        } else {
            try { audio.volume = savedVolume || 0.8; } catch(e) {}
            audio.muted = false;
            syncVolumeSliders((savedVolume || 0.8) * 100);
            updateVolumeIcons(true);
        }
    }

    function updateVolumeIcons(hasVolume) {
        var btns = document.querySelectorAll('.cjrp-btn-volume, .cjrp-sticky-volume');
        btns.forEach(function(btn) {
            btn.innerHTML = hasVolume ? '&#128266;' : '&#128263;';
        });
    }

    function syncVolumeSliders(val) {
        var sliders = document.querySelectorAll('.cjrp-vol-range');
        sliders.forEach(function(s) { s.value = val; });
    }

    function openPopup(playerId) {
        var w = cjrpData.settings.popupWidth || 400;
        var h = cjrpData.settings.popupHeight || 500;
        var left = (screen.width - w) / 2;
        var top = (screen.height - h) / 2;
        window.open(
            window.location.href + (window.location.href.indexOf('?') > -1 ? '&' : '?') + 'cjrp_popup=' + playerId,
            'cjrp_popup',
            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',toolbar=no,menubar=no,scrollbars=yes'
        );
    }

    function extractYouTubeId(url) {
        var match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([^&?#]+)/);
        return match ? match[1] : null;
    }

    function playEmbed(embedCode) {
        var existingEmbed = document.getElementById('cjrp-embed-frame');
        if (existingEmbed) existingEmbed.remove();

        var container = document.createElement('div');
        container.id = 'cjrp-embed-frame';
        container.style.cssText = 'position:fixed;left:-9999px;width:1px;height:1px;overflow:hidden;';
        container.innerHTML = embedCode;
        document.body.appendChild(container);
    }

    function playYouTube(videoId) {
        // Use hidden iframe for audio-only YouTube playback
        var existingFrame = document.getElementById('cjrp-yt-frame');
        if (existingFrame) existingFrame.remove();

        var iframe = document.createElement('iframe');
        iframe.id = 'cjrp-yt-frame';
        iframe.src = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&enablejsapi=1';
        iframe.style.cssText = 'position:fixed;left:-9999px;width:1px;height:1px;';
        iframe.allow = 'autoplay';
        document.body.appendChild(iframe);

        isPlaying = true;
        updateAllPlayButtons(true);
        updateStatus(true);
    }

    function handleAutoplay() {
        // Check shortcode players for autoplay
        var players = document.querySelectorAll('.cjrp-player');
        players.forEach(function(el) {
            var playerId = el.dataset.playerId;
            var playerData = cjrpData.players[playerId];
            if (playerData && playerData.controls && playerData.controls.autoplay === '1') {
                setTimeout(function() {
                    playStation(playerId, 0, el);
                }, 300);
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
