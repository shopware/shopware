/**
 * @package admin
 *
 * @private
 */
export default function initializeExtensionComponentSections(): void {
    // Handle incoming ExtensionComponentRenderer requests from the ExtensionAPI
    Shopware.ExtensionAPI.handle('uiComponentSectionRenderer', (componentConfig, additionalInformation) => {
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        Shopware.State.commit(
            'extensionComponentSections/addSection',
            { ...componentConfig, extensionName: extension.name },
        );
    });
}
