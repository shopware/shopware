/**
 * @sw-package inventory
 *
 * Everything the swCategoryDetail store needs from outside its mini-module.
 */

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);

/** @private */
export type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';

/** @private */
export type { default as CriteriaType } from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';

/** @private */
export type { default as Repository } from 'src/core/data/repository.data';

/** @private */
export type { ContextStore } from 'src/app/store/context.store';
