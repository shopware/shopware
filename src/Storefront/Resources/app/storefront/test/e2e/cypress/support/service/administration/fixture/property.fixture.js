const AdminFixtureService = require('../fixture.service.js');

export default class PropertyFixtureService extends AdminFixtureService {
    constructor() {
        super();

        this.propertyFixture = this.loadJson('property-group.json');
    }

    setPropertyFixture(options, userData) {
        const startTime = new Date();
        global.logger.title('Set property fixtures...');

        const propertyJson = {
            ...this.propertyFixture,
            ...options
        };

        const finalData = this.mergeFixtureWithData(propertyJson, userData);

        return this.apiClient.post('/v1/property-group?_response=true', finalData);
    }
}

global.PropertyFixtureService = new PropertyFixtureService();
