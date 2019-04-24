import { Application } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';

export default function createLocaleToLanguageService() {
    return {
        localeToLanguage
    };

    /**
     * @param {string} locale
     * @return {Promise} languageIdPromise
     */
    function localeToLanguage(locale) {
        const contextService = Application.getContainer('service').context;
        const repoFactory = Application.getContainer('service').repositoryFactory;
        const localeRepo = repoFactory.create('locale', '/locale');
        const languageRepo = repoFactory.create('language', '/language');
        const localeCriteria = new Criteria();

        localeCriteria.setTerm(locale.replace('-', '_'));

        return localeRepo.search(localeCriteria, contextService).then((data) => {
            return data.first().id;
        }).then((id) => {
            const languageCriteria = new Criteria();
            languageCriteria.addFilter(
                Criteria.equals('language.localeId', id)
            );

            return languageRepo.search(languageCriteria, contextService);
        }).then((languageData) => {
            return languageData.first().id;
        });
    }
}
