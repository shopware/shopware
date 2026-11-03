/**
 * @sw-package discovery
 *
 * Everything sw-cms-create-wizard needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { cmsPageStoreId } from '../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { cmsPageTypeService, customEntityDefinitionService, feature as featureService } from 'src/app/service-names';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
