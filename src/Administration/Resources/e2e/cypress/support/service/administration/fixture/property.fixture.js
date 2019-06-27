const AdminFixtureService = require('../fixture.service.js').default;

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

        return this.apiClient.post('/v1/property-group?_response=true', finalData)
            .then((data) => {
                const endTime = new Date() - startTime;
                global.logger.success(`${data.id} (${endTime / 1000}s)`);
                global.logger.lineBreak();
            }).catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
            });
    }
}

global.PropertyFixtureService = new PropertyFixtureService();
