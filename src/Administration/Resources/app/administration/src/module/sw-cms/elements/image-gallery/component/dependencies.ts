/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-image-gallery needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as CMS } from '../../../constant/sw-cms.constant';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
