/**
 * @package admin
 */

let isInitialized = false;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function LanguageAutoFetchingService() {
    if (isInitialized) return;
    isInitialized = true;

    // initial loading of the language
    loadLanguage(Shopware.Context.api.languageId);

    // load the language Entity
    async function loadLanguage(newLanguageId) {
        const languageRepository = Shopware.Service('repositoryFactory').create('language');
        const newLanguage = await languageRepository.get(newLanguageId, {
            ...Shopware.Context.api,
            inheritance: true,
        });

        Shopware.State.commit('context/setApiLanguage', newLanguage);
    }

    // watch for changes of the languageId
    Shopware.State.watch(state => state.context.api.languageId, (newValue, oldValue) => {
        if (newValue === oldValue) return;

        loadLanguage(newValue);
    });
}
