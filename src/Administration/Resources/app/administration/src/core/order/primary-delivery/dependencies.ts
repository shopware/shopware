/**
 * @sw-package checkout
 *
 * Everything primary-delivery needs from outside its mini-module.
 */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
