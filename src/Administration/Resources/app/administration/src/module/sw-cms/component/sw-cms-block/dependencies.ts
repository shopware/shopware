/**
 * @sw-package discovery
 *
 * Everything sw-cms-block needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { cmsPageStoreId } from '../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { default as CmsVisibility } from '../../shared/CmsVisibility';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
