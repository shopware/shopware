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
        const languageRepository = Shopware.Service('repositoryFactory').create('language');
        const criteria = new Shopware.Data.Criteria();
        criteria.addAssociation('locale');
        const newLanguage = await languageRepository.get(
            newLanguageId,
            { ...Shopware.Context.api, inheritance: true },
            criteria,
        );

        Shopware.Store.get('context').api.language = newLanguage;
    }

    watch(() => Shopware.Store.get('context').api.languageId, loadLanguage);
}
