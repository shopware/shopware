/**
 * @private
 * @package content
 */
export default function initializeCms(): void {
    Shopware.ExtensionAPI.handle('cmsRegisterElement', (element, additionalInformation) => {
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            return;
        }

        Shopware.Service('cmsService').registerCmsElement({
            ...element,
            name: element.name,
            component: 'sw-cms-el-location-renderer',
            previewComponent: 'sw-cms-el-preview-location-renderer',
            configComponent: 'sw-cms-el-config-location-renderer',
            appData: {
                baseUrl: extension.baseUrl,
            },
        });
    });

    Shopware.ExtensionAPI.handle('cmsRegisterBlock', (block, additionalInformation) => {
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            return;
        }

        Shopware.Service('cmsService').registerCmsBlock({
            name: 'app-renderer',
            label: block.label ?? '',
            category: block.category ?? 'app',
            component: 'sw-cms-block-app-renderer',
            previewComponent: 'sw-cms-block-app-preview-renderer',
            previewImage: block.previewImage,
            appName: extension.name,
            slots: block.slots.reduce<{
                [key: string]: {
                    type: string;
                };
            }>((acc, slot, index) => {
                acc[`${slot.element}-${index}`] = {
                    type: slot.element,
                };

                return acc;
            }, {}) ?? {},
            defaultConfig: {
                customFields: {
                    appBlockName: block.name,
                    slotLayout: {
                        grid: block.slotLayout?.grid,
                    },
                },
            },
        });
    });
}
