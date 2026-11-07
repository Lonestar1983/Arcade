/**
 * Initialise the Arcade Ruffle player and route legacy game requests.
 *
 * Reads player settings from the arcade-player element's data-player attribute.
 * Loads Ruffle when needed and rewrites supported legacy asset and score URLs.
 *
 * @see https://ruffle.rs/js-docs/master/
 */
(function () {
    'use strict';

    /**
     * Create the player, load its SWF and register the fullscreen control.
     *
     * Returns immediately when the player container is absent. Errors during
     * Ruffle setup or game loading display the configured fallback message.
     * Configuration parsing occurs before that error handler.
     *
     * @async
     * @returns {Promise<void>} Resolves after setup or a handled loading error.
     * @throws {SyntaxError} Rejects if the player configuration is invalid JSON.
     */
    /** Launch a trusted local HTML5 game and reuse the token-bound IBPro adapter. */
    function startHtmlGame(host, config, base) {
        const gameUrl = new URL(config.url, base);
        const gamesRoot = new URL('modules/Arcade/games/', base);
        if (gameUrl.origin !== base.origin || !gameUrl.pathname.startsWith(gamesRoot.pathname)) {
            throw new Error('HTML5 games must be installed in the Arcade games directory.');
        }
        const frame = document.createElement('iframe');
        frame.title = 'Arcade HTML5 game';
        frame.style.cssText = 'width:100%;height:100%;border:0;display:block';
        frame.setAttribute('allow', 'fullscreen; autoplay');
        frame.setAttribute('sandbox', 'allow-scripts allow-same-origin');

        // Existing SWF records keep their old dimensions; size this rebuild independently.
        const zooKeeper   = /(?:^|\/)zoo-keeper\.html$/i.test(gameUrl.pathname);
        const hideTheFart = /(?:^|\/)(?:hide-the-fart(?:-html5)?)\.html$/i.test(gameUrl.pathname);
        const mahjong     = /(?:^|\/)mahjong\.html$/i.test(gameUrl.pathname);
        const yetiBubbles = /(?:^|\/)yetibub\.html$/i.test(gameUrl.pathname);
        const width       = hideTheFart ? 1100 : mahjong ? 1100 : zooKeeper ? 660 : yetiBubbles ? 770 : Math.max(1, Number(config.width) || 660);
        const height      = hideTheFart ? 950 : mahjong ? 850 : zooKeeper ? 960 : yetiBubbles ? 740 : Math.max(1, Number(config.height) || 960);
        const sizeControl = document.getElementById('arcade-size');
        const modes       = ['fit', 'larger', 'original'];
        const key         = 'arcade-player-size:' + base.href + ':' + config.gid;

        let mode           = 'fit';
        try { const saved = localStorage.getItem(key); if (modes.includes(saved)) mode = saved; } catch (error) {}
        if (sizeControl) sizeControl.value = mode;
        const fit = () => {
            if (document.fullscreenElement === host) {
                host.style.width = '100%'; host.style.height = '100%'; host.style.aspectRatio = 'auto'; return;
            }
            host.style.width = '100%';
            const available = host.clientWidth || width;
            const viewport = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            const scale = mode === 'larger' ? available / width : mode === 'original'
                ? Math.min(1, available / width) : Math.min(1, available / width, viewport * .8 / height);
            host.style.width = width * scale + 'px'; host.style.height = height * scale + 'px';
            host.style.minHeight = '0'; host.style.aspectRatio = width + '/' + height;
        };
        sizeControl?.addEventListener('change', () => {
            mode = modes.includes(sizeControl.value) ? sizeControl.value : 'fit';
            try { localStorage.setItem(key, mode); } catch (error) {} fit();
        });
        window.addEventListener('resize', fit);
        document.addEventListener('fullscreenchange', fit);
        document.getElementById('arcade-fullscreen')?.addEventListener('click', () => host.requestFullscreen?.().catch(() => {}));
        let submitting = false;
        let restarting = false;
        async function restartRun() {
            if (restarting || submitting) return;
            restarting = true;
            try {
                // Request the existing play route to issue a new authenticated run,
                // without navigating or replacing the fullscreen iframe.
                const response = await fetch(window.location.href, {credentials: 'same-origin', cache: 'no-store'});
                if (!response.ok) throw new Error('Could not refresh Arcade run.');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const next = JSON.parse(page.getElementById('arcade-player')?.dataset.player || 'null');
                if (!next || Number(next.gid) !== Number(config.gid) || Number(next.uid) !== Number(config.uid)
                    || Number(next.type) !== 7 || !next.token || next.token === config.token
                    || new URL(next.url, base).href !== gameUrl.href) {
                    throw new Error('Invalid refreshed Arcade run.');
                }
                config.token = next.token;
                host.dataset.player = JSON.stringify(config);
                frame.contentWindow.postMessage({type: 'arcade:restarted'}, base.origin);
            } catch (error) {
                frame.contentWindow.postMessage({type: 'arcade:restart-error'}, base.origin);
            } finally {
                restarting = false;
            }
        }
        window.addEventListener('message', event => {
            if (event.origin !== base.origin || event.source !== frame.contentWindow) return;
            const data = event.data;
            if (!data || typeof data !== 'object') return;
            if (['zoo:ready', 'arcade:ready'].includes(data.type) && Number(config.uid) > 0 && config.token && Number(config.type) === 7) {
                frame.contentWindow.postMessage({type: 'arcade:init'}, base.origin);
            } else if (['zoo:restart', 'arcade:restart'].includes(data.type) && !submitting) {
                if (zooKeeper || mahjong || hideTheFart) restartRun();
                else window.location.reload();
            } else if (['zoo:submit', 'arcade:submit'].includes(data.type) && !submitting && !restarting && Number(config.uid) > 0 && Number(config.type) === 7
                && config.token && Number.isSafeInteger(data.score) && data.score > 0) {
                submitting = true;
                const form = document.createElement('form');
                form.method = 'post';
                form.action = new URL('modules.php?name=Arcade&action=score', base).href;
                const fields = {
                    gid: String(config.gid), arcade_token: config.token, arcade_protocol: 'ibpro',
                    gname: decodeURIComponent(gameUrl.pathname.split('/').pop()).replace(/\.html?$/i, ''),
                    gscore: String(data.score)
                };
                Object.entries(fields).forEach(([name, value]) => {
                    const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input);
                });
                document.body.appendChild(form); form.submit();
            }
        });
        frame.src = gameUrl.href;
        host.appendChild(frame);
        fit();
    }

    async function start() {
        const host = document.getElementById('arcade-player');

        if (!host) {
            return;
        }

        const config = JSON.parse(host.dataset.player);
        const base = new URL('.', document.baseURI);

        try {
            if (/\.html?$/i.test(new URL(config.url, base).pathname)) {
                startHtmlGame(host, config, base);
                return;
            }
            if (!window.RufflePlayer || !window.RufflePlayer.newest) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');

                    script.src = 'https://unpkg.com/@ruffle-rs/ruffle';
                    script.onload = resolve;
                    script.onerror = reject;

                    document.head.appendChild(script);
                });
            }

            const player = window.RufflePlayer.newest().createPlayer();

            player.style.width = '100%';
            player.style.height = '100%';
            host.appendChild(player);

            const nativeWidth = Math.max(1, Number(config.width) || 550);
            const nativeHeight = Math.max(1, Number(config.height) || 380);

            const sizeControl = document.getElementById('arcade-size');
            const sizeModes = ['fit', 'larger', 'original'];
            const sizeKey = 'arcade-player-size:' + base.href + ':' + config.gid;
            let sizeMode = 'fit';
            try {
                const savedMode = window.localStorage.getItem(sizeKey);
                if (sizeModes.includes(savedMode)) {
                    sizeMode = savedMode;
                }
            } catch (error) {
                // Sizing still works when browser storage is disabled.
            }
            if (sizeControl) {
                sizeControl.value = sizeMode;
            }

            /**
             * Apply the selected stage size while preserving the game aspect ratio.
             * Keep the SWF's proportions; fullscreen fills its own containing box.
             */
            const fitPlayer = () => {
                if (document.fullscreenElement === host) {
                    host.style.width = '100%';
                    host.style.height = '100%';
                    host.style.aspectRatio = 'auto';
                    return;
                }

                // Measure the column's actual content width, including theme padding.
                host.style.width = '100%';
                const availableWidth = host.clientWidth || nativeWidth;
                const viewportHeight = window.visualViewport
                    ? window.visualViewport.height
                    : window.innerHeight;
                const availableHeight = Math.max(1, viewportHeight * 0.75);
                let scale;
                if (sizeMode === 'larger') {
                    // Fill the content width, allowing upscaling and vertical scrolling.
                    scale = availableWidth / nativeWidth;
                } else if (sizeMode === 'original') {
                    // Native dimensions, reduced only when the column is narrower.
                    scale = Math.min(1, availableWidth / nativeWidth);
                } else {
                    scale = Math.min(1, availableWidth / nativeWidth, availableHeight / nativeHeight);
                }

                host.style.width = (nativeWidth * scale) + 'px';
                host.style.height = (nativeHeight * scale) + 'px';
                host.style.minHeight = '0';
                host.style.aspectRatio = nativeWidth + '/' + nativeHeight;
            };

            if (sizeControl) {
                sizeControl.addEventListener('change', () => {
                    sizeMode = sizeModes.includes(sizeControl.value) ? sizeControl.value : 'fit';
                    try {
                        window.localStorage.setItem(sizeKey, sizeMode);
                    } catch (error) {
                        // The selection remains usable for this page without persistence.
                    }
                    fitPlayer();
                });
            }
            fitPlayer();
            window.addEventListener('resize', fitPlayer);
            document.addEventListener('fullscreenchange', fitPlayer);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', fitPlayer);
            }
            if (window.ResizeObserver && host.parentElement) {
                let columnWidth;
                const observer = new window.ResizeObserver(entries => {
                    const width = entries[0].contentRect.width;
                    // Parent height changes are caused by fitting and need no repeat.
                    if (width !== columnWidth) {
                        columnWidth = width;
                        fitPlayer();
                    }
                });
                observer.observe(host.parentElement);
            }

            const callback = new URL('modules.php?name=Arcade&action=score', base).href;

            /**
             * Escape regular-expression metacharacters in a literal URL prefix.
             *
             * @param {string} value Literal text to escape.
             * @returns {string} Text safe to insert into a RegExp pattern.
             */
            const escapeRegex = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const escapedBase = escapeRegex(base.href);
            const handshakeUrl = new URL('modules.php?name=Arcade&action=handshake', base);

            handshakeUrl.searchParams.set('gid', String(config.gid));

            const handshake = handshakeUrl.href;
            const ibproUrl = new URL('modules.php?name=Arcade&action=score', base);

            ibproUrl.searchParams.set('gid', String(config.gid));
            ibproUrl.searchParams.set('arcade_token', config.token);
            ibproUrl.searchParams.set('arcade_protocol', 'ibpro');

            const gameData = new URL('modules/Arcade/games/gamedata/', base).href;

            // Keep companion assets inside Arcade and route legacy score callbacks.
            const rules = [
                // Yeti Tour loads its score, language and game assets from this folder.
                [
                    new RegExp('^' + escapedBase + '(?:(?:modules/Arcade/games/)?games/yetitour_data/|modules/Arcade/games/yetitour_data/|(?:modules/Forums/|modules/Arcade/games/)?arcade/gamedata/yetitoursmTh/yetitour_data/)', 'i'),
                    gameData + 'yetitour_data/'
                ],
                // Yeti 5 requests these companion SWFs from a legacy games folder.
                [
                    new RegExp('^' + escapedBase + '(?:modules/Arcade/games/)?games/(?=yeti5(?:sc_bg|snd)\\.swf(?:[?#]|$))', 'i'),
                    gameData
                ],
                [
                    new RegExp('^' + escapedBase + '(?:modules/Forums/|modules/Arcade/games/)?arcade/gamedata/', 'i'),
                    gameData
                ],
                [
                    new RegExp('^' + escapedBase + '(?:modules/Arcade/games/)?index\\.php\\?(?:act=Arcade(?:&amp;|&)do=newscore|do=newscore(?:&amp;|&)act=Arcade)(?:&amp;|&|$)', 'i'),
                    ibproUrl.href + '&'
                ],
                [
                    new RegExp('^' + escapedBase + '(?:modules/Forums/games/|modules/Arcade/games/)?arcade\\.php(?:\\?)?', 'i'),
                    handshake + '&'
                ],
                [
                    new RegExp('^' + escapedBase + 'modules\\.php\\?name=Forums(?:&amp;|&)file=proarcade(?=&|$)', 'i'),
                    callback
                ],
                [
                    new RegExp('^' + escapedBase + '(?:modules/Forums/|modules/Arcade/games/)?proarcade\\.php(?:\\?)?', 'i'),
                    callback + '&'
                ]
            ];

            // Only pnFlashGames titles may redirect their bare index.php requests.
            if (Number(config.type) === 8) {
                const pnflashUrl = new URL('modules.php?name=Arcade&action=pnflash', base);
                pnflashUrl.searchParams.set('arcade_gid', String(config.gid));
                pnflashUrl.searchParams.set('arcade_token', config.token);
                rules.push([
                    new RegExp('^' + escapedBase + '(?:modules/Arcade/games/)?index\\.php(?:\\?|$)', 'i'),
                    pnflashUrl.href + '&'
                ]);
            }

            await player.ruffle().load({
                url: new URL(config.url, base).href,
                base: base.href,
                // Legacy LoadVars.send() navigates the current tab to submit scores.
                // Ruffle requires script access to permit this navigation.
                allowScriptAccess : true,
                allowNetworking : 'all',
                openUrlMode : 'allow',
                parameters: {
                    pn_gid : String(config.gid),
                    pn_uname : String(config.uid),
                    gamehash : config.gamehash,
                    gamehash_id : config.gamehash,
                    arcade_hash : config.gamehash,
                    gid : String(config.gid),
                    arcade_token : config.token
                },
                urlRewriteRules: rules,
                autoplay: 'on',
                allowFullscreen: true
            });

            // pnFlashGames saves through LoadVars without navigating the browser.
            // Redirect only after the server confirms this player's exact run saved.
            if (Number(config.type) === 8 && Number(config.uid) > 0) {
                const statusUrl = new URL('modules.php?name=Arcade&action=pnflash', base);
                statusUrl.searchParams.set('arcade_gid', String(config.gid));
                statusUrl.searchParams.set('arcade_token', config.token);
                statusUrl.searchParams.set('arcade_status', '1');
                const scoresUrl = new URL('modules.php?name=Arcade&action=scores', base);
                scoresUrl.searchParams.set('gid', String(config.gid));
                scoresUrl.searchParams.set('notice', 'score_saved');
                const expires = Date.now() + 7200000;
                let stopped = false;
                let timer;
                window.addEventListener('pagehide', () => {
                    stopped = true;
                    window.clearTimeout(timer);
                }, { once: true });

                const checkScore = async () => {
                    if (stopped || Date.now() >= expires) {
                        return;
                    }
                    try {
                        const response = await fetch(statusUrl.href, {
                            credentials: 'same-origin',
                            cache: 'no-store'
                        });
                        if (response.ok) {
                            const result = new URLSearchParams(await response.text());
                            if (!stopped && result.get('opSuccess') === 'true') {
                                window.location.assign(scoresUrl.href);
                                return;
                            }
                        }
                    } catch (error) {
                        // A temporary network failure must not interrupt the game.
                    }
                    if (!stopped) {
                        timer = window.setTimeout(checkScore, 3000);
                    }
                };
                timer = window.setTimeout(checkScore, 3000);
            }

            document.getElementById('arcade-fullscreen').addEventListener('click', () => {
                if (host.requestFullscreen) {
                    host.requestFullscreen().catch(() => {});
                }
            });
        } catch (error) {
            host.textContent = window.RufflePlayer ? config.error : config.missing;
            console.error('Arcade player:', error);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
