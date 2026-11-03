/**
 * @sw-package framework
 *
 * Everything sw-dismissible-notices needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { PropType } from 'vue';
/** @private */
export { userConfigService } from 'src/app/service-names';

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
