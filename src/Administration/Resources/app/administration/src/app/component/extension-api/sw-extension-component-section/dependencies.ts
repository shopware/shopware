/**
 * @sw-package framework
 *
 * Everything sw-extension-component-section needs from outside its mini-module.
 */
import { extensionComponentSectionsStoreId } from 'src/app/store/extension-component-sections.store';
import { extensionsStoreId } from 'src/app/store/extensions.store';

/** @private */
export type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export type { ComponentSectionEntry } from 'src/app/store/extension-component-sections.store';

/** @private */
export const useExtensionComponentSectionsStore = () => Shopware.Store.get(extensionComponentSectionsStoreId);

/** @private */
export const useExtensionsStore = () => Shopware.Store.get(extensionsStoreId);

/** @private */
export const error = (...args: Parameters<typeof Shopware.Utils.debug.error>) => Shopware.Utils.debug.error(...args);

/** @private */
export const warn = (...args: Parameters<typeof Shopware.Utils.debug.warn>) => Shopware.Utils.debug.warn(...args);
