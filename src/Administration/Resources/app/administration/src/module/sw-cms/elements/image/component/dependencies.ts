/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-image needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as CMS } from '../../../constant/sw-cms.constant';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
