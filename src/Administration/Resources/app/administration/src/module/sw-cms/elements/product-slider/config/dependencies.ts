/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-config-product-slider needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getApiContext = () => Shopware.Context.api;
