/**
 * @sw-package inventory
 *
 * Everything sw-settings-product-feature-sets-values-card needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as FeatureGridTranslationService } from 'src/module/sw-settings-product-feature-sets/service/feature-grid-translation.service';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
