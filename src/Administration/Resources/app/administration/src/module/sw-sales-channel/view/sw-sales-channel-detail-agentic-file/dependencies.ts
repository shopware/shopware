/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail-agentic-file needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    repositoryFactory as repositoryFactoryService,
    salesChannelFileApiService,
} from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isEqual = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEqual as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEqual;

/** @private */
export const kebabCase = ((...args: unknown[]) =>
    (Shopware.Utils.string.kebabCase as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.kebabCase;
