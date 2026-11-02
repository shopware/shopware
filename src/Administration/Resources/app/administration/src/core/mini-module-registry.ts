/**
 * @sw-package framework
 *
 * The public API of every mini-module on the global object, which is how prebuilt extension bundles reach it.
 * The `shopware:mini-modules/<name>` modules read from here.
 */

/**
 * @private
 *
 * The exports of a mini-module's `index.ts`, as `import * as` sees them.
 */
export type MiniModuleExports = Readonly<Record<string, unknown>>;

const published: Record<string, MiniModuleExports> = {};

/**
 * @private
 *
 * Every published mini-module by its name. `Shopware.MiniModules` is this object.
 */
export const miniModules: Readonly<Record<string, MiniModuleExports>> = published;

/**
 * @private
 *
 * Puts the exports of a mini-module's `index.ts` on the global object. Only its `register.ts` calls this,
 * with the namespace of `./index` and the name of the module directory.
 * It stores a frozen copy, so nothing can add or replace an export later.
 *
 * @example
 * import * as SwExample from './index';
 *
 * Shopware._private.publishMiniModule('sw-example', SwExample);
 */
export function publishMiniModule(name: string, exports: MiniModuleExports): void {
    if (Object.hasOwn(published, name)) {
        throw new Error(`The mini-module "${name}" is already published.`);
    }

    published[name] = Object.freeze({ ...exports });
}
