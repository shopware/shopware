import useAdminMenuStore from 'shopware:stores/adminMenu';
import useExtensionsStore from 'shopware:stores/extensions';
import useExtensionSdkModulesStore from 'shopware:stores/extensionSdkModules';
import useMenuItemStore from 'shopware:stores/menuItem';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initMenuItems(): void {
    Shopware.ExtensionAPI.handle('menuItemAdd', async (menuItemConfig, additionalInformation) => {
        const extension = Object.values(useExtensionsStore().extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        await useExtensionSdkModulesStore()
            .addModule({
                heading: menuItemConfig.label,
                locationId: menuItemConfig.locationId,
                displaySearchBar: menuItemConfig.displaySearchBar!,
                displaySmartBar: menuItemConfig.displaySmartBar,
                baseUrl: extension.baseUrl,
            })
            .then((moduleId) => {
                if (typeof moduleId !== 'string') {
                    return;
                }

                useMenuItemStore().addMenuItem({
                    ...menuItemConfig,
                    moduleId,
                });
            });
    });

    Shopware.ExtensionAPI.handle('menuCollapse', () => {
        useAdminMenuStore().collapseSidebar();
    });

    Shopware.ExtensionAPI.handle('menuExpand', () => {
        useAdminMenuStore().expandSidebar();
    });
}
