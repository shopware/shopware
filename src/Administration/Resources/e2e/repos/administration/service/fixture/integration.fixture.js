const FixtureService = require('administration/service/fixture.service.js').default;

export default class IntegrationFixtureService extends FixtureService {
    constructor() {
        super();
        this.integrationFixture = this.loadJson('integration.json');
    }

    setIntegrationBaseFixture(json) {
        this.integrationFixture = json;
    }

    setIntegrationFixtures(userData) {
        global.logger.lineBreak();
        global.logger.title('Set integration fixtures...');

        const finalRawData = this.mergeFixtureWithData(this.integrationFixture, userData);

        return this.apiClient.post(`/v1/integration?response=true`, finalRawData)
            .then(() => {
                return this.apiClient.post(`/v1/search/integration?response=true`, {
                    filter: [{
                        field: "label",
                        type: "equals",
                        value: finalRawData.name,
                    }]
                });
            }).catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            }).then((data) => {
                global.logger.success(data.id);
                global.logger.lineBreak();
            });
    }
}

global.IntegrationFixtureService = new IntegrationFixtureService();