/**
 * @sw-package framework
 *
 * Everything listing mixin needs from outside its mini-module.
 */
/** @private */
export type { default as Criteria } from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
/** @private */
export { defineComponent } from 'vue';
/** @private */
export type { LocationQuery, RouteLocationNamedRaw } from 'vue-router';
/** @private */
export { feature as featureService, searchRankingService } from 'src/app/service-names';
/** @private */
export { useShopwareAppsStore } from 'src/app/store/shopware-apps';
/** @private */
export { useSwBulkEditStore as useBulkEditStore } from 'src/app/store/sw-bulk-edit';

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
