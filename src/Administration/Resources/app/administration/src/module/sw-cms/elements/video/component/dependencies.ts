/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-video needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { default as RepositoryType } from 'src/core/data/repository.data';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
