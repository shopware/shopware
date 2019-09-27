const AdminFixtureService = require('../fixture.service.js');

export default class CmsFixtureService extends AdminFixtureService {
    setCmsPageFixture(userData) {
        return this.apiClient.post('/v1/cms-page?_response=true', userData);
    }
}

global.CmsFixtureService = new CmsFixtureService();
