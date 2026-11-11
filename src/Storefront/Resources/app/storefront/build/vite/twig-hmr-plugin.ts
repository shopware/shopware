import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import type { Plugin } from 'vite';

type TwigUpdate = { files: string[], structural: boolean };
type BundleDefinition = { technicalName: string, basePath: string, views?: string[] };

const require = createRequire(import.meta.url);
const getTwigWatchPaths = require('../twig-hmr/watch-paths') as (
    projectRoot: string,
    pluginDefinition: Record<string, BundleDefinition>,
    includeExtensions?: boolean,
) => string[];
const { createTwigUpdateBatcher, describeTwigUpdate } = require('../twig-hmr/update-batcher') as {
    createTwigUpdateBatcher: (send: (update: TwigUpdate) => void) => (event: string, file: string) => void,
    describeTwigUpdate: (update: TwigUpdate) => string,
};

const TWIG_GLOB = '/**/*.twig';

export const TWIG_UPDATE_EVENT = 'sw:twig-update';

/**
 * Vite plugin — the Twig hot reload transport of the Storefront dev server.
 *
 * Watches the Storefront templates of every bundle in var/plugins.json with the
 * Vite watcher and sends the changed files as `sw:twig-update` event.
 * With `SHOPWARE_STOREFRONT_DEV_UPDATE=hmr`, `src/dev/twig-hmr/twig-hmr.vite.js`
 * receives it, renders the page again and morphs the DOM, the same as the
 * webpack transport in `build/webpack/twig-hmr-watcher.js`.
 * In the default reload mode, `src/dev/twig-hmr/twig-reload.vite.js` reloads the page.
 * In manual mode the config does not register this plugin.
 *
 * @param projectRoot  Absolute path to the Shopware project root.
 */
export function twigHmrPlugin(projectRoot: string): Plugin {
    return {
        name: 'sw-twig-hmr',
        apply: 'serve',

        configureServer(server) {
            const pluginsJsonPath = path.join(projectRoot, 'var/plugins.json');
            if (!fs.existsSync(pluginsJsonPath)) {
                return;
            }

            const bundles = JSON.parse(fs.readFileSync(pluginsJsonPath, 'utf-8')) as Record<string, BundleDefinition>;
            const includeExtensions = process.env.SHOPWARE_STOREFRONT_SKIP_EXTENSION_TWIG_WATCH !== '1';
            const watchPaths = getTwigWatchPaths(projectRoot, bundles, includeExtensions);
            // Vite runs chokidar with `disableGlobbing`, so it watches the directories instead of the globs.
            const viewDirectories = watchPaths.map((watchPath) => watchPath.slice(0, -TWIG_GLOB.length));
            const logger = server.config.logger;

            const record = createTwigUpdateBatcher((update) => {
                logger.info(describeTwigUpdate(update), { timestamp: true });
                server.ws.send(TWIG_UPDATE_EVENT, update);
            });

            server.watcher.add(viewDirectories);
            server.watcher.on('all', (event, file) => {
                if (!file.endsWith('.twig') || !viewDirectories.some((directory) => file.startsWith(`${directory}${path.sep}`))) {
                    return;
                }

                record(event, path.relative(projectRoot, file));
            });

            logger.info(`[sw-twig-hmr] watching ${viewDirectories.length} view directories`, { timestamp: true });
        },

        // The Twig client handles the change, so Vite must not turn it into a reload of its own.
        hotUpdate({ file }) {
            if (file.endsWith('.twig')) {
                return [];
            }
        },
    };
}
