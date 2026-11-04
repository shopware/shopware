/**
 * @sw-package inventory
 *
 * Everything sw-settings-search needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { publishData } from 'src/core/service/extension-api-data.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
