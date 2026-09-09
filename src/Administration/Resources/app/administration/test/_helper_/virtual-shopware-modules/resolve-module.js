/**
 * @sw-package framework
 *
 * Jest counterpart of the `shopware:*` modules that `build/vite-plugins/virtual-shopware-modules`
 * generates for Vite.
 *
 * Jest resolves a request to a file, so a one-line stub per specifier calls this.
 *
 * Where a specifier publishes named exports, each one is a getter that reads the global object at access
 * time. Specs routinely replace `window.Shopware` wholesale with a partial mock, and that happens after
 * the module graph is built, so a value captured when the stub loaded would be the real one rather than
 * the mock. A store is a function for the same reason. A mixin or a DAL class has nothing to late-bind
 * and is read once, which keeps `toBe` identity intact for those.
 *
 * CommonJS, and without an `__esModule` marker: that is what makes a compiled
 * `import { createId } from 'shopware:utils'` read the property off this object.
 */

const path = require('path');
const { parseSpecifier, resolveVirtualExport } = require('../../../build/vite-plugins/virtual-shopware-modules/definitions');
const { exportNames, readRegistry } = require('../../../build/vite-plugins/virtual-shopware-modules/index');

const registry = readRegistry(path.resolve(__dirname, '../../..'));

/**
 * One member's value, or `undefined` when the global cannot answer for it.
 *
 * Enumerable getters mean a copying interop resolves every member at once, and a spec that replaced
 * `window.Shopware` with a partial mock has no answer for most of them. `undefined` is what reading
 * `Shopware.Utils.<member>` off that same mock would have produced.
 */
function read(specifier, member) {
    try {
        return resolveVirtualExport(specifier, member, global.Shopware);
    } catch {
        return undefined;
    }
}

/** The value one `shopware:*` specifier publishes, read off the global object the test env set up. */
module.exports = function resolveShopwareModule(specifier) {
    const parsed = parseSpecifier(specifier);
    const members = parsed ? exportNames(registry, parsed) : undefined;

    if (!parsed || !members) {
        throw new Error(`"${specifier}" is not a Shopware virtual module.`);
    }

    if (members.length > 0) {
        const stub = {};

        members.forEach((member) =>
            Object.defineProperty(stub, member, {
                get: () => read(specifier, member),
                // Enumerable, because a mixed `import debug, { warn }` compiles to an interop that copies
                // the module's own enumerable properties. A non-enumerable member would arrive undefined.
                enumerable: true,
            }),
        );

        return stub;
    }

    if (parsed.family === 'shopware:stores') {
        return (...args) => resolveVirtualExport(specifier, 'default', global.Shopware)(...args);
    }

    return resolveVirtualExport(specifier, 'default', global.Shopware);
};
