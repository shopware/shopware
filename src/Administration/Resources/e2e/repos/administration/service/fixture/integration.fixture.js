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
        console.log('### Set integration fixtures...');

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
                console.log(err);
            }).then((data) => {
                console.log(`• ✓ - Created integration: ${data.id}`);
                console.log();
            });
    }
}

global.IntegrationFixtureService = new IntegrationFixtureService();