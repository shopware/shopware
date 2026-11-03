/**
 * @sw-package framework
 *
 * Everything sw-file-input needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const fileSize = (...args: Parameters<typeof Shopware.Utils.format.fileSize>) =>
    Shopware.Utils.format.fileSize(...args);
