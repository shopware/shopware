/**
 * @sw-package framework
 *
 * Declares a component in the index.ts of its mini-module: its name, how to load it, and its public API.
 * This file is the public API, also for extensions. The files in `./src` are private.
 */
/** @public */
export { definePublicComponent } from './src/public-component';
/** @public */
export type { PublicComponent, PublicComponentApi } from './src/public-component';
