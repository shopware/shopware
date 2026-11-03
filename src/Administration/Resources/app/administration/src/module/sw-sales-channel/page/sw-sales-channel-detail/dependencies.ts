/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { object } from 'src/core/service/util.service';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    exportTemplateService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { default as EntityValidationService } from 'src/app/service/entity-validation.service';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
