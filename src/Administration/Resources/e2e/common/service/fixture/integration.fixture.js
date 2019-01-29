const FixtureService = require('./../fixture.service.js').default;

export default class IntegrationFixtureService extends FixtureService {
    constructor() {
        super();
        this.integrationFixture = this.loadJson('integration.json');
    }

    setIntegrationBaseFixture(json) {
        this.integrationFixture = json;
    }

    setIntegrationFixtures(userData) {
        const startTime = new Date();
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

global.IntegrationFixtureService = new IntegrationFixtureService();