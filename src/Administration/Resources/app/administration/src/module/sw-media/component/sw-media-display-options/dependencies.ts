/**
 * @sw-package discovery
 *
 * Everything sw-media-display-options needs from outside its mini-module.
 */
/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
