/**
 * @sw-package discovery
 *
 * Everything sw-media-list-selection-v2 needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { mediaService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
