const AdminApiService = require('./../service/admin-api.service');
const _ = require('lodash');
const glob = require('glob');
const path = require('path');
const uuid = require('uuid/v4');

export default class FixtureService {
    constructor() {
        this.apiClient = new AdminApiService(process.env.APP_URL);
        this.basicFixture = "";

        // Automatic loading of fixtures
        glob.sync(path.join(__dirname, './fixture/*.js')).forEach((fileName) => {
            require(path.resolve(fileName));
        });
    }

    setBasicFixture(json) {
        this.basicFixture = this.loadJson(json);
    }

    create(type, userData = {}) {
        console.log(`### Set ${type} fixtures...`);

        this.setBasicFixture(`${type}.json`);
        const finalRawData = this.mergeFixtureWithData(this.basicFixture, userData);

        return this.apiClient.post(`/v1/${type}?response=true`, finalRawData)
            .then(() => {
                return this.apiClient.post(`/v1/search/${type}?response=true`, {
                    filter: [{
                        field: "name",
                        type: "equals",
                        value: finalRawData.name,
                    }]
                });
            }).catch((err) => {
                console.log('• ✖ - ', err);
            }).then((data) => {
                console.log(`• ✓ - ${data.id}`);
                console.log();
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
        } catch (err) {
            console.log('• ✖ - Error: ', err);
        }

    }
}

global.FixtureService = new FixtureService();
