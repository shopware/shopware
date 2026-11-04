/**
 * @sw-package after-sales
 *
 * Everything sw-flow-rule-modal needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';
import { sessionStoreId } from 'src/app/store/session';
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    ruleConditionDataProviderService,
    ruleConditionsConfigApiService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
