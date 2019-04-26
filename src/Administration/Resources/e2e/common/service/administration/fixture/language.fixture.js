const AdminFixtureService = require('./../fixture.service.js').default;

export default class LanguageFixtureService extends AdminFixtureService {
    getLanguageName() {
        return 'Philippine English';
    }

    setLanguageFixtures(userData) {
        const startTime = new Date();

        global.logger.title('Set language fixtures...');
        return this.apiClient.post('/v1/search/locale', {
            filter: [{
                field: 'code',
                type: 'equals',
                value: 'en-PH'
            }]
        }).then((data) => {
            return {
                name: this.getLanguageName(),
                localeId: data.id,
                parentId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b'
            };
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