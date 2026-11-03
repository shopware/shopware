/**
 * @sw-package after-sales
 *
 * Everything sw-flow-index needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export type { default as CriteriaType } from '../../../../core/data/criteria.data';
/** @private */
export type { default as Repository } from '../../../../core/data/repository.data';
