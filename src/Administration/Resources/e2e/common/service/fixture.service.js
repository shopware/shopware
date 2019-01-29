const AdminApiService = require('./admin-api.service');
const _ = require('lodash');
const glob = require('glob');
const path = require('path');
const uuid = require('uuid/v4');

export default class FixtureService {
    constructor() {
        this.apiClient = new AdminApiService(process.env.APP_URL);
        this.basicFixture = '';

        // Automatic loading of fixtures
        glob.sync(path.join(__dirname, './fixture/*.js')).forEach((fileName) => {
            require(fileName);
        });
    }

    setBasicFixture(json) {
        this.basicFixture = this.loadJson(json);
    }

    create(type, userData = {}) {
        global.logger.lineBreak();
        global.logger.title(`Set ${type} fixtures...`);

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
            }).then((data) => {
                global.logger.success(data.id);
                global.logger.lineBreak();
            }).catch((err) => {
                global.logger.error(err);
                global.logger.lineBreak();
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
            return require(`./../@fixtures/${fileName}`);
        } catch (err) {
            global.logger.error(err);
        }
    }
}

global.FixtureService = new FixtureService();
