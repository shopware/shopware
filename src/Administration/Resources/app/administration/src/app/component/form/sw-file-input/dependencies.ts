/**
 * @sw-package framework
 *
 * Everything sw-file-input needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const fileSize = ((...args: unknown[]) =>
    (Shopware.Utils.format.fileSize as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.fileSize;
