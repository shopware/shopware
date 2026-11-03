/**
 * @sw-package framework
 *
 * Everything sw-entity-single-select needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error.mixin';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const camelCase = (...args: Parameters<typeof Shopware.Utils.string.camelCase>) =>
    Shopware.Utils.string.camelCase(...args);

/** @private */
export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);

/** @private */
export const error = (...args: Parameters<typeof Shopware.Utils.debug.error>) => Shopware.Utils.debug.error(...args);

/** @private */
export const get = (...args: Parameters<typeof Shopware.Utils.get>) => Shopware.Utils.get(...args);
