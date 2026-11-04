/**
 * @sw-package discovery
 *
 * Everything sw-media-media-item needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { fileSizeFilterName } from 'src/app/filter/file-size.filter';
import { mediaNameFilterName } from 'src/app/filter/media-name.filter';
import { repositoryFactory } from 'src/app/service-names';
import { actionButtonsStoreId } from 'src/app/store/action-buttons';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { acl as aclService, mediaService } from 'src/app/service-names';
/** @private */
export { default as videoCoverMixin } from 'src/module/sw-media/mixin/video-cover/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- the mixin is private, extensions reach it with Mixin.getByName()

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const useActionButtonsStore = () => Shopware.Store.get(actionButtonsStoreId);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getFileSizeFilter = () => Shopware.Filter.getByName(fileSizeFilterName);

/** @private */
export const getMediaNameFilter = () => Shopware.Filter.getByName(mediaNameFilterName);

/** @private */
export const copyStringToClipboard = ((...args: unknown[]) =>
    (Shopware.Utils.dom.copyStringToClipboard as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.dom.copyStringToClipboard;
