import extensionStore from './extensions.store';

export default function initState(Shopware) {
    Shopware.State.registerModule('shopwareExtensions', extensionStore);

    let languageId = Shopware.State.get('session').languageId;
    Shopware.State._store.subscribe(({ type }, state) => {
        if (type === 'setAdminLocale' && state.session.languageId !== '' && languageId !== state.session.languageId) {
            Shopware.State.dispatch('shopwareExtensions/updateMyExtensions');
            languageId = state.session.languageId;
        }
    });
}
