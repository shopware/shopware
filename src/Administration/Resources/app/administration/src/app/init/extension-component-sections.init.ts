import '../store/extension-component-sections.store';
import useExtensionComponentSectionsStore from 'shopware:stores/extensionComponentSections';
import useExtensionsStore from 'shopware:stores/extensions';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeExtensionComponentSections(): void {
    // Handle incoming ExtensionComponentRenderer requests from the ExtensionAPI
    Shopware.ExtensionAPI.handle('uiComponentSectionRenderer', (componentConfig, additionalInformation) => {
        const extension = Object.values(useExtensionsStore().extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        useExtensionComponentSectionsStore().addSection({
            ...componentConfig,
            extensionName: extension.name,
        });
    });
}
