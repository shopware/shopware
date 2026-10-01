/**
 * Shopware runtime module.
 *
 * This is the entry point for the standalone Vite build (vite.shopware.config.mts).
 * It exports ShopwareComponent and Shopware for ES module consumers and sets both
 * on window as a side effect for backward compatibility with legacy JS plugins.
 *
 * @sw-package framework
 */

// Sets window.ShopwareComponent as side effect.
export { default as ShopwareComponent } from './component-system/component';

// Sets window.Shopware as side effect.
export { Shopware } from './component-system/shopware';
