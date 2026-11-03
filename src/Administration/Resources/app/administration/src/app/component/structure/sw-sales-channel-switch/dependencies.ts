/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-switch needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';

/** @private */
export const warn = (...args: Parameters<typeof Shopware.Utils.debug.warn>) => Shopware.Utils.debug.warn(...args);
