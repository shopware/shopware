/**
 * @sw-package framework
 *
 * Everything sw-generic-custom-entity-list needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export type { default as CriteriaType } from 'src/core/data/criteria.data';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';
/** @private */
export {
    acl as aclService,
    customEntityDefinitionService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export type { AdminUiDefinition, CustomEntityDefinition } from 'src/app/service/custom-entity-definition.service';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
