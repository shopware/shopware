/**
 * @sw-package framework
 *
 * Jest counterpart of the `shopware:*` modules that `build/vite-plugins/virtual-shopware-modules`
 * generates for Vite.
 *
 * Jest resolves a request to a file, so `jest-resolver.js` writes a one-line stub per specifier and the
 * stub calls this. Whatever the specifier's default export is becomes `module.exports`, which is what
 * makes a compiled `import { warn } from 'shopware:utils/debug'` read `warn` off the namespace and
 * `import debug from 'shopware:utils/debug'` receive the namespace itself.
 *
 * CommonJS, and without an `__esModule` marker, for the same reason.
 */

const { resolveVirtualExport } = require('../../../build/vite-plugins/virtual-shopware-modules/definitions');

/** The value one `shopware:*` specifier publishes, read off the global object the test env set up. */
module.exports = function resolveShopwareModule(specifier) {
    return resolveVirtualExport(specifier, 'default', global.Shopware);
};
