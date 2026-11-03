/**
 * @sw-package framework
 *
 * Everything sw-tabs needs from outside its mini-module.
 */
/** @private */
export type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export { defineComponent as wrapComponentConfig, Fragment, Text } from 'vue';
/** @private */
export type { VNode } from 'vue';
/** @private */
export type { RouteLocationRaw, Router } from 'vue-router';

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
