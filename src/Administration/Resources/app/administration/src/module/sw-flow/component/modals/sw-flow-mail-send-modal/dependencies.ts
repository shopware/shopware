/**
 * @sw-package after-sales
 *
 * Everything sw-flow-mail-send-modal needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    documentV2Service,
    repositoryFactory as repositoryFactoryService,
    validationApiService,
} from 'src/app/service-names';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
