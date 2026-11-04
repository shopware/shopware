/**
 * @sw-package framework
 *
 * Declares a component in the index.ts of its mini-module: its name, how to load it, and its public API.
 * A nested private mini-module declares its component in its private.ts the same way.
 * This file is the public API, also for extensions. The files in `./src` are private.
 */
/**
 * @public
 * @export-namespace framework
 */
export { definePrivateComponent, definePublicComponent } from './src/public-component';
/** @public */
export type {
    LoadedComponent,
    PrivateComponent,
    PrivateInstance,
    PublicComponent,
    PublicComponentApi,
    PublicInstance,
    PublicOverride,
} from './src/public-component';
