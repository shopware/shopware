/**
 * @sw-package discovery
 *
 * Everything sw-media-quickinfo needs from outside its mini-module.
 */
import { actionButtonsStoreId } from 'src/app/store/action-buttons';

/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { isPlayableMediaFormat, shouldShowUnsupportedFormatWarning } from 'src/app/service/media-format.service';
/** @private */
export { default as mediaSidebarModalMixinMixin } from '../../../mixin/media-sidebar-modal-mixin/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- the mixin is private, extensions reach it with Mixin.getByName()
/** @private */
export { default as videoCoverMixin } from '../../../mixin/video-cover/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- the mixin is private, extensions reach it with Mixin.getByName()

/** @private */
export const useActionButtonsStore = () => Shopware.Store.get(actionButtonsStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const copyStringToClipboard = ((...args: unknown[]) =>
    (Shopware.Utils.dom.copyStringToClipboard as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.dom.copyStringToClipboard;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
