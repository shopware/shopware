const AdminFixtureService = require('./../fixture.service.js').default;

export default class LanguageFixtureService extends AdminFixtureService {
    constructor() {
        super();
        this.languageFixture = this.loadJson('language.json');
    }

    setLanguageBaseFixture(json) {
        this.languageFixture = json;
    }

    setLanguageFixtures(userData) {
        const startTime = new Date();

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
        }).then((data) => {
            const endTime = new Date() - startTime;
            global.logger.success(`${data.id} (${endTime / 1000}s)`);
            global.logger.lineBreak();
        }).catch((err) => {
            global.logger.error(err);
            global.logger.lineBreak();
        });
    }
}

global.LanguageFixtureService = new LanguageFixtureService();