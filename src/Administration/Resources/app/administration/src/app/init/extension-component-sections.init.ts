/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeExtensionComponentSections(): void {
    // Handle incoming ExtensionComponentRenderer requests from the ExtensionAPI
    Shopware.ExtensionAPI.handle('uiComponentSectionRenderer', (componentConfig) => {
        Shopware.State.commit('extensionComponentSections/addSection', componentConfig);
    });
}
