/**
 * @sw-package framework
 *
 * Everything sw-extension-component-section needs from outside its mini-module.
 */
import { extensionComponentSectionsStoreId } from 'src/app/store/extension-component-sections';
import { extensionsStoreId } from 'src/app/store/extensions';

/** @private */
export type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export type { ComponentSectionEntry } from 'src/app/store/extension-component-sections';

/** @private */
export const useExtensionComponentSectionsStore = () => Shopware.Store.get(extensionComponentSectionsStoreId);

/** @private */
export const useExtensionsStore = () => Shopware.Store.get(extensionsStoreId);

/** @private */
export const error = ((...args: unknown[]) =>
    (Shopware.Utils.debug.error as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.error;

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
