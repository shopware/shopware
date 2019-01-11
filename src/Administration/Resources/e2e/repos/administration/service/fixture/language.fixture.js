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
        global.logger.lineBreak();
        global.logger.title('Set language fixtures...');
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
            global.logger.error(err);
            global.logger.lineBreak();
        }).then((data) => {
            global.logger.success(data.id);
            global.logger.lineBreak();
        });
    }
}

global.LanguageFixtureService = new LanguageFixtureService();