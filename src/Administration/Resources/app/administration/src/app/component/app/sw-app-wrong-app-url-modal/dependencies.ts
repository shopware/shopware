/**
 * @sw-package framework
 *
 * Everything sw-app-wrong-app-url-modal needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { contextStoreId } from 'src/app/store/context';
import { notificationStoreId } from 'src/app/store/notification';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const copyStringToClipboard = ((...args: unknown[]) =>
    (Shopware.Utils.dom.copyStringToClipboard as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.dom.copyStringToClipboard;
