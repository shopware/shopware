/**
 * @sw-package framework
 *
 * The public API of every mini-module on the global object, which is how prebuilt extension bundles reach it.
 * The `shopware:<kind>/<name>` modules read from here.
 */

/**
 * @private
 *
 * The kinds a mini-module is published under. Each kind is one `shopware:<kind>` module family and one
 * branch of `Shopware.Public`.
 */
export const PUBLIC_KINDS = [
    'components',
    'framework',
    'handlers',
    'helpers',
] as const;

/** @private One of the `PUBLIC_KINDS`, e.g. `components`. */
export type PublicKind = (typeof PUBLIC_KINDS)[number];

/**
 * @private
 *
 * The exports of a mini-module's `index.ts`, as `import * as` sees them.
 */
export type MiniModuleExports = Readonly<Record<string, unknown>>;

const published = Object.fromEntries(
    PUBLIC_KINDS.map((kind) => [
        kind,
        {},
    ]),
) as Record<PublicKind, Record<string, MiniModuleExports>>;

/**
 * @private
 *
 * Every published mini-module by its kind and name. `Shopware.Public` is this object.
 */
export const publicModules: Readonly<Record<PublicKind, Readonly<Record<string, MiniModuleExports>>>> =
    Object.freeze(published);

/**
 * @private
 *
 * Puts the exports of a mini-module's `index.ts` on the global object. Only its `register.ts` calls this,
 * with its kind, the name of the module directory and the namespace of `./index`.
 * It stores a frozen copy, so nothing can add or replace an export later.
 *
 * @example
 * import * as SwExample from './index';
 *
 * Shopware._private.publish('components', 'sw-example', SwExample);
 */
export function publish(kind: PublicKind, name: string, exports: MiniModuleExports): void {
    if (!Object.hasOwn(published, kind)) {
        throw new Error(`"${kind}" is not a public kind. Use one of: ${PUBLIC_KINDS.join(', ')}.`);
    }

    if (Object.hasOwn(published[kind], name)) {
        throw new Error(`The mini-module "${kind}/${name}" is already published.`);
    }

    published[kind][name] = Object.freeze({ ...exports });
}
