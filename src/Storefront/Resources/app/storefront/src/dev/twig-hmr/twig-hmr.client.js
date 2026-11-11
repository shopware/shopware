/**
 * Entry of the Twig hot reload in the webpack hot mode.
 * Listens for `sw-twig-update` messages of the webpack dev server and refreshes the page in place.
 *
 * @sw-package framework
 * @private
 */
import { start } from './start';

function resolveSocketUrl() {
    // The webpack dev server serves this file and hosts the web socket under /ws.
    const script = document.querySelector('script[src*="hot-reloading.js"]');
    const origin = script ? new URL(script.src, window.location.href) : new URL(window.location.href);
    const protocol = origin.protocol === 'https:' ? 'wss:' : 'ws:';

    return `${protocol}//${origin.host}/ws`;
}

function connect(onUpdate) {
    const socket = new WebSocket(resolveSocketUrl());

    socket.addEventListener('message', (event) => {
        let message;
        try {
            message = JSON.parse(event.data);
        } catch {
            return;
        }

        if (message.type === 'sw-twig-update') {
            onUpdate(message.data);
        }
    });

    socket.addEventListener('close', () => {
        setTimeout(() => connect(onUpdate), 1000);
    });
}

// The deferred hot reload script runs before the theme scripts.
start(connect);
