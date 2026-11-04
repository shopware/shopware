/**
 * @sw-package framework
 *
 * The `extensionComponentSections` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/extensionComponentSections`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export {
    extensionComponentSectionsStoreId,
    useExtensionComponentSectionsStore,
} from './src/extension-component-sections.store';

/** @public */
export type { ComponentSectionEntry, ExtensionComponentSectionsStore } from './src/extension-component-sections.store';
