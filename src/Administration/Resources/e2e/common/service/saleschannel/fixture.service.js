const _ = require('lodash');
const glob = require('glob');
const path = require('path');
const uuid = require('uuid/v4');
const SalesChannelApiService = require('./sales-channel-api.service');
const AdminApiService = require('./../administration/admin-api.service');

export default class SalesChannelFixtureService {
    constructor() {
        this.apiClient = new SalesChannelApiService(process.env.APP_URL);
        this.adminApiClient = new AdminApiService(process.env.APP_URL);
        this.basicFixture = '';

        // Automatic loading of fixtures
        glob.sync(path.join(__dirname, './fixture/*.js')).forEach((fileName) => {
            require(fileName);
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
            return require(`./../../@fixtures/${fileName}`);
        } catch (err) {
            global.logger.error(err);
        }
    }
}

global.SalesChannelFixtureService = new SalesChannelFixtureService();
