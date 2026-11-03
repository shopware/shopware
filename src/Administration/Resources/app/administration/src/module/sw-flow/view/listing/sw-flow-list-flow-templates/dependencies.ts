/**
 * @sw-package after-sales
 *
 * Everything sw-flow-list-flow-templates needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export type { default as CriteriaType } from '../../../../../core/data/criteria.data';
/** @private */
export type { default as Repository } from '../../../../../core/data/repository.data';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
