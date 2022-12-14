/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initMenuItems(): void {
    Shopware.ExtensionAPI.handle('menuItemAdd', async (menuItemConfig, additionalInformation) => {
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            return;
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
