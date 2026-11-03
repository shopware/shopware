/**
 * @sw-package after-sales
 *
 * Everything sw-flow-sequence-condition needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export {
    feature as featureService,
    flowBuilderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);
