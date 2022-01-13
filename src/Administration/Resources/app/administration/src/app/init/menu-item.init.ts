export default function initMenuItems(): void {
    Shopware.ExtensionAPI.handle('menuItemAdd', (menuItemConfig, additionalInformation) => {
        // eslint-disable-next-line max-len
        const extension = Object.values(Shopware.State.get('extensions'))
            .find(ext => ext.baseUrl.startsWith(additionalInformation._event_.origin));

        if (!extension) {
            return;
        }

        Shopware.State.commit('menuItem/addMenuItem', { ...menuItemConfig, baseUrl: extension.baseUrl });
    });
}
