/**
 * Entry of the Twig page reload in the Vite dev server (`SHOPWARE_STOREFRONT_DEV_UPDATE=reload`).
 * Listens for `sw:twig-update` events of `build/vite/twig-hmr-plugin.ts` and reloads the page.
 *
 * Jest cannot load `import.meta`, so this file only connects the Vite client to `startTwigReload()`.
 *
 * @sw-package framework
 * @private
 */
import { startTwigReload } from './twig-reload';

startTwigReload((onUpdate) => import.meta.hot?.on('sw:twig-update', onUpdate));
