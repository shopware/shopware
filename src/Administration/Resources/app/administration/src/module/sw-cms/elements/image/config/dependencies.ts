/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-config-image needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
