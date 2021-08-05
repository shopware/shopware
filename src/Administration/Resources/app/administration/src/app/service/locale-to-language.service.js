const { Criteria } = Shopware.Data;

export default function createLocaleToLanguageService() {
    return {
        localeToLanguage,
    };

    /**
     * @param {string} locale
     * @return {Promise} languageIdPromise
     */
    function localeToLanguage(locale) {
        const apiContext = Shopware.Context.api;
        const repoFactory = Shopware.Service('repositoryFactory');
        const localeRepo = repoFactory.create('locale', '/locale');
        const localeCriteria = new Criteria();

        localeCriteria
            .addFilter(Criteria.equals('code', locale))
            .addAssociation('languages');

        return localeRepo.search(localeCriteria, apiContext)
            .then((data) => {
                return data.first().languages.first().id;
            })
            .catch(() => {
                // Fallback: System default language
                return Shopware.Context.api.systemLanguageId;
            });
    }
}
