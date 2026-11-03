/**
 * @sw-package framework
 *
 * Everything sw-iframe-renderer needs from outside its mini-module.
 */
import { extensionsStoreId } from 'src/app/store/extensions.store';
import { sdkLocationStoreId } from 'src/app/store/sdk-location.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { extensionSdkService } from 'src/app/service-names';
/** @private */
export { default as useTheme } from '../../../composables/use-theme';
/** @private */
export type { Extension } from '../../../store/extensions.store';

/** @private */
export const useExtensionsStore = () => Shopware.Store.get(extensionsStoreId);

/** @private */
export const useSdkLocationStore = () => Shopware.Store.get(sdkLocationStoreId);

/** @private */
export const handle = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.handle as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.ExtensionAPI.handle;
