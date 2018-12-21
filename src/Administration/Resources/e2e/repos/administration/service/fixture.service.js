const AdminApiService = require('./../service/admin-api.service');
const _ = require('lodash');
const glob = require('glob');
const path = require('path');
const uuid = require('uuid/v4');

export default class FixtureService {
    constructor() {
        this.apiClient = new AdminApiService(process.env.APP_URL);

        // Automatic loading of fixtures
        glob.sync(path.join(__dirname, './fixture/*.js')).forEach((fileName) => {
            require(path.resolve(fileName));
        });
    }

    create(url, fixtureData, type, done) {
        console.log(`### Set ${type} fixtures...`);

        this.apiClient.post(url, fixtureData).then(() => {
            console.log(`• ✓ - Created ${type}: ${fixtureData.name}`);
            done();
        });
    }

    createUuid() {
        return uuid();
    }

    mergeFixtureWithData(...args) {
        const result = _.merge({}, ...args);
        return result;
    }

    loadJson(fileName) {
        try {
            return require(`administration/@fixtures/${fileName}`);
        } catch(err) {
            console.log('• ✖ - Error: ', err);
        }

    }
}

global.FixtureService = new FixtureService();
