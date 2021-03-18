import extensionStore from './extensions.store';

export default function initState(Shopware) {
    Shopware.State.registerModule('shopwareExtensions', extensionStore);

    let languageId = Shopware.State.get('session').languageId;
    Shopware.State._store.subscribe(({ type }, state) => {
        if (!Shopware.Service('acl').can('system.plugin_maintain')) {
            return;
        }

        if (type === 'setAdminLocale' && state.session.languageId !== '' && languageId !== state.session.languageId) {
            // Always on page load setAdminLocale will be called once. Catch it to not load refresh extensions
            if (languageId === '') {
                languageId = state.session.languageId;
                return;
            }

            Shopware.Service('shopwareExtensionService').updateExtensionData();
            languageId = state.session.languageId;
        }
    });
}
