/* eslint no-console: 0 */

/**
 * Reloads the page on a Twig change in the reload mode of the Vite dev server
 * (`SHOPWARE_STOREFRONT_DEV_UPDATE=reload`).
 *
 * @sw-package framework
 * @private
 */

export const LOG_PREFIX = '[sw-twig-reload]';

// Lets the server drop its compiled templates, because a compiled template keeps the parent it extended when it was compiled.
// `sw-twig-hmr` keeps the HTTP cache out of the request, the same as for the in-place update.
const RESET_HEADERS = {
    Accept: 'text/html',
    'sw-twig-hmr': '1',
    'sw-twig-hmr-reset': '1',
};

/**
 * @typedef {Object} TwigUpdate
 * @property {string[]} [files] - the changed Twig files
 * @property {boolean} [structural] - true if a file was added or removed
 */

/**
 * @param {TwigUpdate | undefined} update
 * @param {{ fetch: typeof window.fetch, reload: () => void }} [env]
 * @returns {Promise<void>}
 */
export async function reloadForTwigUpdate(update, env = { fetch: (...args) => window.fetch(...args), reload: () => window.location.reload() }) {
    // A plain reload would keep a compiled template that extends an override that was just added or removed.
    if (update?.structural === true) {
        try {
            await env.fetch(window.location.href, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: RESET_HEADERS,
            });
        } catch (error) {
            console.warn(`${LOG_PREFIX} Could not reset the compiled templates, reloading anyway.`, error);
        }
    }

    env.reload();
}

/**
 * @param {(onUpdate: (update: TwigUpdate | undefined) => void) => void} subscribe - connects to the dev server
 * @param {{ fetch: typeof window.fetch, reload: () => void }} [env]
 */
export function startTwigReload(subscribe, env) {
    let reloading = false;

    subscribe((update) => {
        // The page is going away, later updates are part of the next render anyway.
        if (reloading) {
            return;
        }

        reloading = true;
        void reloadForTwigUpdate(update, env);
    });
}
