/**
 * @sw-package after-sales
 *
 * Everything sw-flow-trigger needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { businessEventService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);

/** @private */
export const camelCase = ((...args: unknown[]) =>
    (Shopware.Utils.string.camelCase as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.camelCase;

/** @private */
export const capitalizeString = ((...args: unknown[]) =>
    (Shopware.Utils.string.capitalizeString as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.string.capitalizeString;
