import pc from 'picocolors';
import type { Plugin } from 'vite';
import { STOREFRONT_DEV_UPDATE_ENV, type StorefrontDevUpdate } from './storefront-dev-update';

const DEV_UPDATE_NOTICES: Record<StorefrontDevUpdate, string> = {
    reload: `Changes reload the page. Set ${STOREFRONT_DEV_UPDATE_ENV}=hmr to apply them in place.`,
    hmr: `Changes are applied to the open page in place (${STOREFRONT_DEV_UPDATE_ENV}=hmr).`,
    manual: `Changes are not sent to the page, reload it yourself (${STOREFRONT_DEV_UPDATE_ENV}=manual).`,
};

/**
 * Vite plugin — replaces the default "Local: http://localhost:…" startup
 * output with a Shopware-specific notice.
 *
 * The default Vite URLs are misleading in the component dev-server workflow:
 * the Vite port is never opened directly in a browser — it only serves JS,
 * CSS, and SCSS assets consumed by the running Shopware storefront. Opening
 * localhost:5175 directly would show a blank page and confuse developers.
 *
 * Vite prints its URL lines by calling `server.printUrls()` — a method on the
 * ViteDevServer instance — rather than going through the logger. Replacing
 * that method in `configureServer` is therefore the correct interception point.
 *
 * The notice also names the active `SHOPWARE_STOREFRONT_DEV_UPDATE` mode.
 */
export function devServerNoticePlugin(devUpdate: StorefrontDevUpdate = 'reload'): Plugin {
    return {
        name: 'sw-dev-server-notice',
        apply: 'serve',

        configureServer(server) {
            server.printUrls = () => {
                const port = server.config.server.port ?? 5175;
                server.config.logger.info(
                    '\n' +
                    '  ' + pc.green('➜') + pc.green('  Storefront dev server ready') + ' (Port: ' + port + ').\n' +
                    '     Open your Shopware Storefront in the browser as usual —\n' +
                    '     JS and CSS are loaded from this server automatically.\n' +
                    '     ' + DEV_UPDATE_NOTICES[devUpdate] + '\n',
                    { timestamp: false },
                );
            };
        },
    };
}
