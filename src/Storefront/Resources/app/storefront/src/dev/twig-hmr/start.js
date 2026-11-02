/* eslint no-console: 0 */

/**
 * Starts the Twig hot reload for a transport of the webpack or the Vite dev server.
 *
 * @sw-package framework
 * @private
 */
import { init, refresh, LOG_PREFIX } from './twig-hmr';

/**
 * @typedef {Object} TwigUpdate
 * @property {string[]} [files] - the changed Twig files
 * @property {boolean} [structural] - true if a file was added or removed
 */

/**
 * The entry must run before the theme scripts, so the body still holds the server markup.
 *
 * @param {(onUpdate: (update: TwigUpdate | undefined) => void) => void} subscribe - connects to the dev server
 */
export function start(subscribe) {
    init();

    // Manual trigger for debugging: `await window.swTwigHmr.refresh([])`.
    window.swTwigHmr = { refresh };
    subscribe((update) => {
        void refresh(update?.files ?? [], { structural: update?.structural === true });
    });
    console.info(`${LOG_PREFIX} Twig changes are applied without a page reload.`);
}
