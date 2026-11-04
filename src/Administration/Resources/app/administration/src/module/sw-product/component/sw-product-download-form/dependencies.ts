/**
 * @sw-package inventory
 *
 * Everything sw-product-download-form needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error.store';

/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    configService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';

/** @private */
export const fileSize = ((...args: unknown[]) =>
    (Shopware.Utils.format.fileSize as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.fileSize;
