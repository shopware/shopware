const AdminFixtureService = require('../fixture.service.js').default;

export default class SnippetFixtureService extends AdminFixtureService {
    constructor() {
        super();
        this.snippetFixture = this.loadJson('snippet.json');
    }

    setSnippetBaseFixture(json) {
        this.snippetFixture = json;
    }

    setSnippetFixtures(userData) {
        const startTime = new Date();
        global.logger.title('Set snippet fixtures...');

        let languageId = '';
        let setId = '';

        const snippetData = this.snippetFixture;

        return this.apiClient.post('/v1/search/language?response=true', {
            filter: [{
                field: 'name',
                type: 'equals',
                value: 'English'
            }]
        }).then((data) => {
            languageId = data.id;
        }).then(() => {
            return this.apiClient.post('/v1/search/snippet-set?response=true', {
                filter: [{
                    field: 'name',
                    type: 'equals',
                    value: 'BASE en-GB'
                }]
            });
        }).then((data) => {
            setId = data.id;
        })
            .then(() => {
                return this.mergeFixtureWithData({
                    languageId: languageId,
                    setId: setId
                }, snippetData);
            })
            .then((finalSnippetData) => {
                return this.apiClient.post('/v1/snippet?_response=true', finalSnippetData, userData);
            });
    }
}

global.SnippetFixtureService = new SnippetFixtureService();
