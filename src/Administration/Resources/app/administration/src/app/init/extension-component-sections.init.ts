import { handle } from '@shopware-ag/admin-extension-sdk/es/channel';

export default function initializeExtensionComponentSections(): void {
    // Handle incoming ExtensionComponentRenderer requests from the ExtensionAPI
    handle('uiComponentSectionRenderer', (componentConfig) => {
        Shopware.State.commit('extensionComponentSections/addSection', componentConfig);
    });
}
