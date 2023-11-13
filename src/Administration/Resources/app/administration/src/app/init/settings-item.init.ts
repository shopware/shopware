/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initializeSettingItems(): void {
    Shopware.ExtensionAPI.handle('settingsItemAdd', async (settingsItemConfig, additionalInformation) => {
        const allowedTabs = ['shop', 'system', 'plugins'];
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        let group = 'plugins';

        if (!settingsItemConfig.tab) {
            settingsItemConfig.tab = 'plugins';
        }

        if (allowedTabs.includes(settingsItemConfig.tab)) {
            group = settingsItemConfig.tab;
        }

        await Shopware.State.dispatch('extensionSdkModules/addModule', {
            heading: settingsItemConfig.label,
            locationId: settingsItemConfig.locationId,
            displaySearchBar: settingsItemConfig.displaySearchBar,
            baseUrl: extension.baseUrl,
        }).then(moduleId => {
            if (typeof moduleId !== 'string') {
                return;
            }

            Shopware.State.commit('settingsItems/addItem', {
                group: group,
                icon: settingsItemConfig.icon,
                id: settingsItemConfig.locationId,
                label: {
                    translated: true,
                    label: settingsItemConfig.label,
                },
                name: settingsItemConfig.locationId,
                to: {
                    name: 'sw.extension.sdk.index',
                    params: {
                        id: moduleId,
                    },
                },
            });
        });
    });
}
