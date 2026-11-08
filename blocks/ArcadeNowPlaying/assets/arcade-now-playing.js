/** Page the single-player panels independently for each block instance. */
(function () {
    'use strict';
    function start() {
        document.querySelectorAll('[data-arcade-now-playing]').forEach(block => {
            if (block.dataset.arcadeReady) return;
            block.dataset.arcadeReady = '1';
            const panels = Array.from(block.querySelectorAll('[data-arcade-panel]'));
            const previous = block.querySelector('[data-arcade-prev]');
            const next = block.querySelector('[data-arcade-next]');
            const nav = block.querySelector('.arcade-now-playing-nav');
            if (!previous || !next || !nav || panels.length < 2) return;
            let page = 0;
            let transitioning = false;
            block.classList.add('arcade-now-playing-animated');
            const render = () => {
                panels.forEach((panel, index) => {
                    const active = index === page;
                    panel.hidden = false;
                    panel.style.visibility = active ? 'visible' : 'hidden';
                    panel.setAttribute('aria-hidden', active ? 'false' : 'true');
                    panel.inert = !active;
                });
                previous.disabled = page === 0;
                next.disabled = page === panels.length - 1;
            };

            /** Fade between overlapping panels; their grid keeps the tallest height. */
            const move = async direction => {
                if (transitioning) return;
                const target = Math.max(0, Math.min(panels.length - 1, page + direction));
                if (target === page) return;
                const reducedMotion = window.matchMedia &&
                    window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (reducedMotion || typeof panels[page].animate !== 'function') {
                    page = target;
                    render();
                    return;
                }
                transitioning = true;
                const outgoing = panels[page];
                const incoming = panels[target];
                incoming.style.visibility = 'visible';
                try {
                    const options = { duration: 220, easing: 'ease-out' };
                    await Promise.all([
                        outgoing.animate([
                            { opacity: 1, transform: 'translateX(0)' },
                            { opacity: 0, transform: 'translateX(' + (-direction * 12) + 'px)' }
                        ], options).finished,
                        incoming.animate([
                            { opacity: 0, transform: 'translateX(' + (direction * 12) + 'px)' },
                            { opacity: 1, transform: 'translateX(0)' }
                        ], options).finished
                    ]);
                } catch (error) {
                    // Finish navigation even if the browser cancels an animation.
                } finally {
                    page = target;
                    render();
                    transitioning = false;
                }
            };
            previous.addEventListener('click', () => { move(-1); });
            next.addEventListener('click', () => { move(1); });
            nav.hidden = false;
            render();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}());
