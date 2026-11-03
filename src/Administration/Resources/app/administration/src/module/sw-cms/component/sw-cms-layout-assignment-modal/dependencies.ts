/**
 * @sw-package discovery
 *
 * Everything sw-cms-layout-assignment-modal needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as EntityCollection } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
/** @private */
export { default as difference } from 'lodash-es/difference';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
