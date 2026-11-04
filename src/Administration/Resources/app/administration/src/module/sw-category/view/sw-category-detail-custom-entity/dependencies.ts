/**
 * @sw-package inventory
 *
 * Everything sw-category-detail-custom-entity needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as Criteria } from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const camelCase = ((...args: unknown[]) =>
    (Shopware.Utils.string.camelCase as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.camelCase;
