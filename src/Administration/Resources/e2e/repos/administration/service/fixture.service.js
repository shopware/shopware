const AdminApiService = require('./../service/admin-api.service');
const LoggingHelper = require('../../../common/helper/cliOutputHelper');
const _ = require('lodash');
const glob = require('glob');
const path = require('path');
const uuid = require('uuid/v4');

export default class FixtureService {
    constructor() {
        this.apiClient = new AdminApiService(process.env.APP_URL);
        this.loggingHelper = new LoggingHelper();
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
        this.loggingHelper.createCliEntry(`Set ${type} fixtures...`, 'title');

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
                this.loggingHelper.createCliEntry(err, 'error');
            }).then((data) => {
                this.loggingHelper.createCliEntry(data.id, 'success');
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
