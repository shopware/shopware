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
}
