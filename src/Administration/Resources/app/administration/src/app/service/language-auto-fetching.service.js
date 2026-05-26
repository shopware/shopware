/**
 * @sw-package framework
 */
import { watch } from 'vue';

let isInitialized = false;

/**
 * @private
 */
export default function LanguageAutoFetchingService() {
    if (isInitialized) return;
    isInitialized = true;

    // initial loading of the language
    loadLanguage(Shopware.Context.api.languageId);

    // load the language Entity
    async function loadLanguage(newLanguageId) {
        if (!canReadLanguage()) {
            return;
        }

        const languageRepository = Shopware.Service('repositoryFactory').create('language');
        const newLanguage = await languageRepository.get(newLanguageId, {
            ...Shopware.Context.api,
            inheritance: true,
        });

        Shopware.Store.get('context').api.language = newLanguage;
    }

    watch(() => Shopware.Store.get('context').api.languageId, loadLanguage);
    watch(() => Shopware.Store.get('session').userPrivileges, () => {
        void loadLanguage(Shopware.Store.get('context').api.languageId);
    });
}

function canReadLanguage() {
    const session = Shopware.Store.get('session');

    return session.currentUser?.admin === true || session.userPrivileges?.includes('language:read') === true;
}
