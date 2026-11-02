/**
 * @sw-package framework
 *
 * The `shopware:*` modules, which expose the global `Shopware` object as ordinary imports.
 * The Vite plugin serves them in the Administration build and in extension builds, and `setup.ts` serves them in Jest.
 * Both read the checked-in registry `shopware-modules.json`, which `build/shopware-modules-generator` writes.
 */

/** @public */
export { default, type Consumer } from './src/vite-plugin';

/** @public The format of `shopware-modules.json`, which the Vite plugin reads from the Administration root. */
export type { ModuleRegistry, ModuleRegistryEntry } from './src/definitions';
