/**
 * @private
 * @sw-package framework
 */
export default function initializeSettingItems(): void {
    Shopware.ExtensionAPI.handle('settingsItemAdd', async (settingsItemConfig, additionalInformation) => {
        const allowedTabs = [
            'general',
            'localization',
            'customer',
            'commerce',
            'content',
            'automation',
            'system',
            'account',
            'plugins',
        ];

        const extension = Object.values(Shopware.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

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

        await Shopware.Store.get('extensionSdkModules')
            .addModule({
                heading: settingsItemConfig.label,
                locationId: settingsItemConfig.locationId,
                displaySearchBar: settingsItemConfig.displaySearchBar!,
                baseUrl: extension.baseUrl,
            })
            .then((moduleId) => {
                if (typeof moduleId !== 'string') {
                    return;
                }

                Shopware.Store.get('settingsItems').addItem({
                    group: group as
                        | 'general'
                        | 'localization'
                        | 'customer'
                        | 'commerce'
                        | 'content'
                        | 'automation'
                        | 'system'
                        | 'account'
                        | 'plugins',
                    icon: settingsItemConfig.icon,
                    id: settingsItemConfig.locationId,
                    label: settingsItemConfig.label,
                    name: settingsItemConfig.locationId,
                    to: {
                        name: 'sw.extension.sdk.index',
                        params: {
                            id: moduleId,
                            back: `sw.settings.index.${group}`,
                        },
                    },
                });
            });
    });
}
