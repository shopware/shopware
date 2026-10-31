/* eslint no-console: 0 */

/**
 * Entry of the Twig hot reload in the webpack hot mode.
 * Listens for `sw-twig-update` messages of the webpack dev server and refreshes the page in place.
 *
 * @sw-package framework
 * @private
 */
import { init, refresh, LOG_PREFIX } from './twig-hmr';

function resolveSocketUrl() {
    // The webpack dev server serves this file and hosts the web socket under /ws.
    const script = document.querySelector('script[src*="hot-reloading.js"]');
    const origin = script ? new URL(script.src, window.location.href) : new URL(window.location.href);
    const protocol = origin.protocol === 'https:' ? 'wss:' : 'ws:';

    return `${protocol}//${origin.host}/ws`;
}

function connect() {
    const socket = new WebSocket(resolveSocketUrl());

    socket.addEventListener('message', (event) => {
        let message;
        try {
            message = JSON.parse(event.data);
        } catch {
            return;
        }

        if (message.type === 'sw-twig-update') {
            void refresh(message.data?.files ?? [], { structural: message.data?.structural === true });
        }
    });

    socket.addEventListener('close', () => {
        setTimeout(connect, 1000);
    });
}

// The deferred hot reload script runs before the theme scripts, so the body still holds the server markup.
init();

// Manual trigger for debugging: `await window.swTwigHmr.refresh([])`.
window.swTwigHmr = { refresh };
connect();
console.info(`${LOG_PREFIX} Experimental: Twig changes are applied without a page reload.`);
