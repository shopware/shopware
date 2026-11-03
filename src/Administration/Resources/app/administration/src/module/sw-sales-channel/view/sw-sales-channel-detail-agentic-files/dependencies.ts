/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail-agentic-files needs from outside its mini-module.
 */
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
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const kebabCase = ((...args: unknown[]) =>
    (Shopware.Utils.string.kebabCase as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.kebabCase;
