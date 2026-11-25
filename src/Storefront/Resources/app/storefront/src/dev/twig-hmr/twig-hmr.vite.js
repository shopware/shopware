/**
 * Entry of the Twig hot reload in the Vite dev server.
 * Listens for `sw:twig-update` events of `build/vite/twig-hmr-plugin.ts` and refreshes the page in place.
 * Also swaps the class of changed components for `build/vite/component-hmr-plugin.ts`.
 *
 * Jest cannot load `import.meta`, so this file only connects the Vite client to `start()`.
 *
 * @sw-package framework
 * @private
 */
import { start } from './start';
import { replace } from './component-hmr';

window.swComponentHmr = { replace };

// The dev flag file lists this module before the theme scripts, and module scripts run in document order.
start((onUpdate) => import.meta.hot?.on('sw:twig-update', onUpdate));
