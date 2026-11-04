/**
 * @sw-package discovery
 *
 * Everything sw-media-modal-delete needs from outside its mini-module.
 */
import { mediaNameFilterName } from 'src/app/filter/media-name.filter';
import { notificationStoreId } from 'src/app/store/notification';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getMediaNameFilter = () => Shopware.Filter.getByName(mediaNameFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
