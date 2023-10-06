/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initializeExtensionComponentSections(): void {
    // Handle incoming ExtensionComponentRenderer requests from the ExtensionAPI
    Shopware.ExtensionAPI.handle('uiComponentSectionRenderer', (componentConfig) => {
        Shopware.State.commit('extensionComponentSections/addSection', componentConfig);
    });
}
