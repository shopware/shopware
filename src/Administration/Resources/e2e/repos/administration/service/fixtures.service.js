const AdminApiService = require('./../service/admin-api.service');
const ProductFixture = require('./../service/admin-api.service');
const _ = require('lodash');

export default class FixtureService {
    constructor() {
        this.apiClient = new AdminApiService(process.env.APP_URL);
    }

    create(url, fixtureData, type, done) {
        console.log(`### Set ${type} fixtures...`);

        this.apiClient.post(url, fixtureData).then(() => {
            console.log(`• ✓ - Created ${type}: ${fixtureData.name}`);
            done();
        });
    }

    mergeFixtureWithData(json, userData) {
        return _.merge(json, userData);
    }

    loadJson(fileName) {
        return require(`administration/@fixtures/${fileName}`);
    }
}

global.FixtureService = new FixtureService();