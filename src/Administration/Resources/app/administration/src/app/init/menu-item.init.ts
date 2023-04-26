/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initMenuItems(): void {
    Shopware.ExtensionAPI.handle('menuItemAdd', async (menuItemConfig, additionalInformation) => {
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        await Shopware.State.dispatch('extensionSdkModules/addModule', {
            heading: menuItemConfig.label,
            locationId: menuItemConfig.locationId,
            displaySearchBar: menuItemConfig.displaySearchBar,
            baseUrl: extension.baseUrl,
        }).then((moduleId) => {
            if (typeof moduleId !== 'string') {
                return;
            }

            Shopware.State.commit('menuItem/addMenuItem', {
                ...menuItemConfig,
                moduleId,
            });
        });
    });
}
