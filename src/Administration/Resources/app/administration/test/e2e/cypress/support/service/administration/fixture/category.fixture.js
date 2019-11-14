const AdminFixtureService = require('../fixture.service.js');

export default class CategoryFixtureService extends AdminFixtureService {
    setCategoryFixture(userData) {
        return this.apiClient.post('/v1/category?_response=true', userData);
    }
}

global.CategoryFixtureService = new CategoryFixtureService();
