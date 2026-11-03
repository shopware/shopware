/**
 * @sw-package inventory
 *
 * Everything sw-settings-listing-default-sales-channel needs from outside its mini-module.
 */
/** @private */
export { default as isEmpty } from 'lodash-es/isEmpty';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { cloneDeep } from 'src/core/service/utils/object.utils';
/** @private */
export { repositoryFactory as repositoryFactoryService, systemConfigApiService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
