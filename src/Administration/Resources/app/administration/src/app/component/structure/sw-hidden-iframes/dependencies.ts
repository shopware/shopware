/**
 * @sw-package framework
 *
 * Everything sw-hidden-iframes needs from outside its mini-module.
 */
import { extensionsStoreId } from 'src/app/store/extensions';

/** @private */
export { MAIN_HIDDEN } from '@shopware-ag/meteor-admin-sdk/es/location';

/** @private */
export const useExtensionsStore = () => Shopware.Store.get(extensionsStoreId);
