const FixtureService = require('administration/service/fixtures.service');

export default class LanguageFixtureService extends FixtureService {
    constructor() {
        super();
    }

    setLanguageFixtures(languageData, done) {
        console.log('### Set language fixtures');
        let localeId = '';

        return this.apiClient.post('/v1/search/locale', {
            filter: [{
                field: "code",
                type: "equals",
                value: "en_PH",
            }]
        }).then((data) => {
            localeId = data.id;
            return data.id;
        }).then(() => {
            return Object.assign({}, {
                localeId: localeId,
            }, languageData);
        }).then((finalLanguageData) => {
            return this.apiClient.post('/v1/language?response=true', finalLanguageData);
        }).catch((err) => {
            console.log('• ✖ - Error: ', err);
        }).then(() => {
            console.log('• ✓ - Created language: ', languageData.name);
            done();
        });
    }
}

global.LanguageFixtureService = new LanguageFixtureService();