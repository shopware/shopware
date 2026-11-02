/**
 * @sw-package framework
 *
 * Everything `src/` needs from outside the module. The Vite config and every Jest test file load it.
 */

/** @private */
export { default as fs } from 'node:fs';

/** @private */
export { default as path } from 'node:path';

/** @private */
export type { Plugin } from 'vite';

/** @private Type only: the Vite config cannot load Administration source. */
export type { ShopwareClass } from 'src/core/shopware';

/**
 * @private
 *
 * The global `Shopware` object of the running Jest environment, read on every call because specs replace it.
 */
export function getShopwareGlobal(): unknown {
    return (globalThis as { Shopware?: unknown }).Shopware;
}
