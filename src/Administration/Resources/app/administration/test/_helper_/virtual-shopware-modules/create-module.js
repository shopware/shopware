/**
 * @sw-package framework
 *
 * Jest counterpart of the `shopware:*` modules that `build/vite-plugins/virtual-shopware-modules`
 * generates for Vite.
 *
 * `moduleNameMapper` can only point at a real file, so the exports are resolved lazily through a proxy
 * instead of being generated. The rules come from the plugin's own definitions, so a spec sees the same
 * values the build produces.
 *
 * CommonJS, and without an `__esModule` marker: that is what makes a compiled
 * `import { createId } from 'shopware:utils'` read the property off this object.
 */

const { resolveVirtualExport } = require('../../../build/vite-plugins/virtual-shopware-modules/definitions');

/** Names a module loader probes for, which are never exports of a `shopware:*` module. */
const LOADER_PROBES = new Set([
    '__esModule',
    'default',
    'then',
]);

/**
 * A stand-in for one `shopware:*` module, e.g. `createShopwareVirtualModule('shopware:utils')`.
 *
 * Resolution happens per property read, so importing the module never depends on the registry entry
 * behind an export already existing - only using it does.
 */
module.exports = function createShopwareVirtualModule(specifier) {
    return new Proxy(Object.create(null), {
        get(_target, property) {
            if (typeof property !== 'string' || LOADER_PROBES.has(property)) {
                return undefined;
            }

            return resolveVirtualExport(specifier, property, global.Shopware);
        },

        has(_target, property) {
            return typeof property === 'string' && !LOADER_PROBES.has(property);
        },
    });
};
