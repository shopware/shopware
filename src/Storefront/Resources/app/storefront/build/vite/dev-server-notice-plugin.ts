import pc from 'picocolors';
import type { Plugin } from 'vite';

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
 */
export function devServerNoticePlugin(): Plugin {
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
                    '     JS and CSS are loaded from this server automatically.\n',
                    { timestamp: false },
                );
            };
        },
    };
}
