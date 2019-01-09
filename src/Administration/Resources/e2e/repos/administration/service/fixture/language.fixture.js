const FixtureService = require('administration/service/fixture.service.js').default;

export default class LanguageFixtureService extends FixtureService {
    constructor() {
        super();
        this.languageFixture = this.loadJson('language.json');
    }

    setLanguageBaseFixture(json) {
        this.languageFixture = json;
    }

    setLanguageFixtures(userData) {
        this.loggingHelper.createCliEntry('Set language fixtures...', 'title');
        let localeId = '';

        const languageData = this.languageFixture;

        return this.apiClient.post('/v1/search/locale', {
            filter: [{
                field: "code",
                type: "equals",
                value: "en_PH",
            }]
        }).then((data) => {
            localeId = data.id;
        }).then(() => {
            return Object.assign({}, {
                localeId: localeId,
            }, languageData);
        }).then((finalLanguageData) => {
            return this.apiClient.post('/v1/language?_response=true', finalLanguageData, userData);
        }).catch((err) => {
            this.loggingHelper.createCliEntry(err, 'error');
        }).then((data) => {
            this.loggingHelper.createCliEntry(data.id, 'success');
        });
    }
}

global.LanguageFixtureService = new LanguageFixtureService();