/**
 * @sw-package framework
 *
 * Everything sw-entity-multi-id-select needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isEqual = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEqual as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEqual;
