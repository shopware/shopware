/**
 * @sw-package framework
 *
 * Imports the `global.d.ts` of every mini-module, which adds its components to `PublicComponents`.
 * `admin-types.d.ts` imports this file, and `type-surface.spec.ts` fails when a `global.d.ts` is missing here.
 */

export {};
