/**
 * @sw-package after-sales
 *
 * Everything sw-order-document-settings-modal needs from outside its mini-module.
 */
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { numberRangeService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
