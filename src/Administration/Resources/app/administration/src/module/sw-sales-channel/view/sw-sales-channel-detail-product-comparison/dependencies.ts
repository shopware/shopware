/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail-product-comparison needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export {
    acl as aclService,
    entityMappingService,
    productExportService,
    repositoryFactory as repositoryFactoryService,
    salesChannelService,
} from 'src/app/service-names';

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
